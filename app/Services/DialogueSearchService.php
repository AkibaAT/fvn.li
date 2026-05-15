<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DialogueLine;
use App\Models\GameVersion;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;

class DialogueSearchService
{
    /**
     * Search for dialogue texts using Meilisearch, then fetch actual dialogue lines from PostgreSQL.
     * Returns dialogue lines with full context information (file path, line number, context, etc.)
     */
    public function search(
        string $searchTerm,
        array $filters = [],
        int $perPage = 20,
        int $page = 1
    ): LengthAwarePaginator {
        $language = $filters['language'] ?? null;
        $exactMatch = $filters['exact_match'] ?? false;

        // Get raw Meilisearch results with all metadata
        $client = app(Client::class);
        $index = $client->index('game_dialogue_texts');

        // Build filter array (Meilisearch filter syntax)
        $filterParts = [];
        if (! empty($language)) {
            $safeLanguage = preg_replace('/[^a-zA-Z0-9]/', '', $language);
            $filterParts[] = "language = '{$safeLanguage}'";
        }
        if (! empty($filters['game_id'])) {
            $filterParts[] = 'game_id = ' . (int) $filters['game_id'];
        }
        if (! empty($filters['version_id'])) {
            // Filter by version_ids array
            $filterParts[] = 'version_ids = ' . (int) $filters['version_id'];
        }
        if (! empty($filters['character_id'])) {
            $characterDatabaseId = $this->resolveCharacterDatabaseId($filters);
            if ($characterDatabaseId !== null) {
                // Filter by character_ids array
                $filterParts[] = 'character_ids = ' . $characterDatabaseId;
            }
        }

        // If exact match is requested, wrap the search term in quotes for phrase matching
        // This ensures whole word matching rather than substring matching
        $actualSearchTerm = $exactMatch ? '"' . addslashes($searchTerm) . '"' : $searchTerm;

        // Execute search with highlighting
        $searchParams = [
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'attributesToHighlight' => ['text_content'],
            'highlightPreTag' => '<mark>',
            'highlightPostTag' => '</mark>',
        ];
        if (! empty($filterParts)) {
            $searchParams['filter'] = implode(' AND ', $filterParts);
        }

        $results = $index->search($actualSearchTerm, $searchParams);
        $hits = $results->getHits();
        $total = $results->getEstimatedTotalHits();

        // Get the text IDs and highlighted text from search results
        $textIds = collect($hits)->pluck('text_id')->toArray();
        $highlightedTexts = collect($hits)->mapWithKeys(function ($hit) {
            return [$hit['text_id'] => $hit['_formatted']['text_content'] ?? $hit['text_content']];
        });
        $firstSeenVersions = collect($hits)->mapWithKeys(function ($hit) {
            return [
                $hit['text_id'] => [
                    'id' => $hit['first_seen_version_id'] ?? null,
                    'version' => $hit['first_seen_version'] ?? null,
                    'published_at' => $hit['first_seen_published_at'] ?? null,
                ],
            ];
        });

        if (empty($textIds)) {
            return new LengthAwarePaginator(
                [],
                0,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
        }

        // Fetch actual dialogue lines from PostgreSQL with full context
        $query = DialogueLine::whereIn('text_id', $textIds)
            ->with(['gameVersion.game', 'gameVersion', 'text', 'character']);

        // Apply additional filters to dialogue lines
        if (! empty($filters['game_id'])) {
            $query->whereHas('gameVersion', function ($q) use ($filters) {
                $q->where('game_id', $filters['game_id']);
            });
        }
        if (! empty($filters['version_id'])) {
            $query->where('game_version_id', $filters['version_id']);
        }
        if (! empty($filters['language'])) {
            $query->where('iso_code', $filters['language']);
        }
        if (! empty($filters['context'])) {
            $query->where('context', $filters['context']);
        }
        if (! empty($filters['character_id'])) {
            $query->whereHas('character', function ($q) use ($filters) {
                $q->where('character_id', $filters['character_id']);
            });
        }

        // Get all matching dialogue lines
        $dialogueLines = $query->get();

        // Group by text_id to maintain search result order
        $linesByTextId = $dialogueLines->groupBy('text_id');

        // Build final results in the order returned by Meilisearch
        // Attach highlighted text from Meilisearch to each line
        $items = collect($textIds)->flatMap(function ($textId) use ($linesByTextId, $highlightedTexts, $firstSeenVersions) {
            $lines = $linesByTextId->get($textId, collect());
            $highlightedText = $highlightedTexts->get($textId);
            $firstSeenVersion = $firstSeenVersions->get($textId);

            // Add highlighted text to each line
            return $lines->map(function ($line) use ($highlightedText, $firstSeenVersion) {
                $line->highlighted_text = $highlightedText;
                $line->first_seen_version = $firstSeenVersion;

                return $line;
            });
        });

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Get the most duplicated dialogue lines.
     *
     * @param  array  $filters  Filters to apply (game_id, version_id, language, etc.)
     * @param  int  $limit  Maximum number of duplicates to return
     * @return Collection Collection of duplicate lines with count and examples
     */
    public function getTopDuplicates(array $filters = [], int $limit = 10): Collection
    {
        $language = $filters['language'] ?? 'eng';
        $minLength = $filters['min_length'] ?? 10; // Minimum text length to consider
        $minCount = $filters['min_count'] ?? 3;    // Minimum duplication count

        // Base query to get the most duplicated texts
        $query = DB::table('unique_dialogue_texts')
            ->join('version_dialogue_lines', 'unique_dialogue_texts.id', '=', 'version_dialogue_lines.text_id')
            ->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
            ->select([
                'unique_dialogue_texts.id as text_id',
                'unique_dialogue_texts.text_content',
                DB::raw('COUNT(version_dialogue_lines.id) as usage_count'),
            ])
            ->whereRaw('LENGTH(unique_dialogue_texts.text_content) >= ?', [$minLength])
            ->groupBy('unique_dialogue_texts.id', 'unique_dialogue_texts.text_content')
            ->having(DB::raw('COUNT(version_dialogue_lines.id)'), '>=', $minCount)
            ->orderByDesc('usage_count');

        // Apply filters
        if (! empty($filters['game_id'])) {
            $query->where('game_versions.game_id', '=', $filters['game_id']);
        }

        if (! empty($filters['version_id'])) {
            $query->where('version_dialogue_lines.game_version_id', '=', $filters['version_id']);
        }

        if (! empty($filters['character_id'])) {
            $query->join('characters', 'version_dialogue_lines.character_id', '=', 'characters.id')
                ->where('characters.character_id', '=', $filters['character_id']);
        }

        $query->where('version_dialogue_lines.iso_code', '=', $language);

        // Get the top duplicates
        $topDuplicates = $query->limit($limit)->get();

        // For each duplicate, get examples of its usage
        foreach ($topDuplicates as $duplicate) {
            // Get a few example usages with context
            $examples = DB::table('version_dialogue_lines')
                ->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
                ->join('games', 'game_versions.game_id', '=', 'games.id')
                ->leftJoin('characters', 'version_dialogue_lines.character_id', '=', 'characters.id')
                ->select([
                    'games.name as game_name',
                    'game_versions.version',
                    'characters.character_id',
                    'characters.display_names',
                    'version_dialogue_lines.iso_code',
                    'version_dialogue_lines.context',
                    'version_dialogue_lines.file_path',
                    'version_dialogue_lines.line_number',
                ])
                ->where('version_dialogue_lines.text_id', '=', $duplicate->text_id)
                ->where('version_dialogue_lines.iso_code', '=', $language)
                ->limit(5)
                ->get();

            // Add display name to each example
            foreach ($examples as $example) {
                if ($example->character_id && $example->display_names) {
                    $displayNames = json_decode($example->display_names, true);
                    $example->character_display_name = $displayNames[$language]
                        ?? $displayNames['eng']
                        ?? $example->character_id;
                } else {
                    $example->character_display_name = $example->character_id;
                }

                // Clean up display_names as we no longer need the raw JSON
                unset($example->display_names);
            }

            $duplicate->examples = $examples;
        }

        return collect($topDuplicates);
    }

    /**
     * Get dialogue statistics for a game version.
     *
     * @param  GameVersion  $version  The game version
     * @return array Stats including unique text count, total lines, etc.
     */
    public function getVersionStatistics(GameVersion $version): array
    {
        // Cache version statistics for 1 hour since game versions rarely change
        return Cache::remember("dialogue.version_stats.{$version->id}", 3600, function () use ($version) {
            $totalLines = $version->dialogueLines()->count();

            // Calculate total word count from version_character_stats
            $totalWords = DB::table('version_character_stats')
                ->where('game_version_id', $version->id)
                ->sum('words');

            // Count unique characters (excluding narrator and menu_choice)
            $uniqueCharacters = DB::table('version_dialogue_lines as vdl')
                ->join('characters as c', 'c.id', '=', 'vdl.character_id')
                ->where('vdl.game_version_id', $version->id)
                ->whereNotIn('c.character_id', ['narrator', 'menu_choice'])
                ->distinct('c.id')
                ->count('c.id');

            // Calculate average words per line
            $avgWordsPerLine = $totalLines > 0 ? round($totalWords / $totalLines, 1) : 0;

            // Language breakdown
            $languageStats = DB::table('version_dialogue_lines')
                ->where('game_version_id', $version->id)
                ->select([
                    'iso_code',
                    DB::raw('COUNT(*) as line_count'),
                ])
                ->groupBy('iso_code')
                ->orderByDesc('line_count')
                ->get();

            return [
                'total_lines' => $totalLines,
                'total_words' => (int) $totalWords,
                'unique_characters' => $uniqueCharacters,
                'avg_words_per_line' => $avgWordsPerLine,
                'languages' => $languageStats,
            ];
        });
    }

    /**
     * Get global dialogue statistics across all games.
     *
     * @return array Stats including total unique texts, total lines, etc.
     */
    public function getGlobalStatistics(): array
    {
        $totalLines = DialogueLine::count();
        $uniqueTextsCount = DB::table('unique_dialogue_texts')->count();

        // Calculate total text size
        $totalTextSize = DB::table('unique_dialogue_texts')
            ->sum(DB::raw('LENGTH(text_content)'));

        // Estimate space saved
        $duplicationRatio = $totalLines > 0 && $uniqueTextsCount > 0
            ? ($totalLines / $uniqueTextsCount)
            : 0;

        $spaceEfficiency = $duplicationRatio > 1
            ? (1 - (1 / $duplicationRatio)) * 100
            : 0;

        $estimatedRawSize = $totalTextSize;
        $estimatedSavedSize = $spaceEfficiency > 0
            ? ($totalTextSize * ($duplicationRatio - 1) / $duplicationRatio)
            : 0;

        // Get games with highest text duplication
        $gameStats = $this->getGameDuplicationStats(10);

        return [
            'total_lines' => $totalLines,
            'unique_texts' => $uniqueTextsCount,
            'total_games_with_dialogue' => DB::table('version_dialogue_lines')
                ->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
                ->distinct('game_versions.game_id')
                ->count('game_versions.game_id'),
            'duplication_ratio' => $duplicationRatio,
            'space_efficiency' => $spaceEfficiency,
            'estimated_raw_size_mb' => round($estimatedRawSize / (1024 * 1024), 2),
            'estimated_saved_mb' => round($estimatedSavedSize / (1024 * 1024), 2),
            'most_duplicated_games' => $gameStats,
        ];
    }

    /**
     * Get a list of games with their duplication statistics.
     *
     * @param  int  $limit  Maximum number of games to return
     * @return Collection Collection of game stats
     */
    public function getGameDuplicationStats(int $limit = 20): Collection
    {
        return DB::table('version_dialogue_lines as vdl')
            ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
            ->join('games as g', 'gv.game_id', '=', 'g.id')
            ->select([
                'g.id',
                'g.name',
                DB::raw('COUNT(vdl.id) as total_lines'),
                DB::raw('COUNT(DISTINCT vdl.text_id) as unique_texts'),
                DB::raw('COUNT(vdl.id) / COUNT(DISTINCT vdl.text_id) as duplication_ratio'),
            ])
            ->groupBy('g.id', 'g.name')
            ->having(DB::raw('COUNT(vdl.id)'), '>', 100) // Only consider games with significant dialogue
            ->orderByDesc('duplication_ratio')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the PostgreSQL language configuration name.
     */
    protected function getLanguageConfig(?string $language = null): string
    {
        return 'english';
    }

    /**
     * Get the tsvector column name for the given language.
     */
    protected function getTsvectorColumnForLanguage(?string $language = null): string
    {
        // Default to English
        return 'search_vector';
    }

    private function resolveCharacterDatabaseId(array $filters): ?int
    {
        $characterId = $filters['character_id'] ?? null;
        if (empty($characterId)) {
            return null;
        }

        if (is_numeric($characterId)) {
            return (int) $characterId;
        }

        $query = DB::table('characters')->where('character_id', '=', $characterId);
        if (! empty($filters['game_id'])) {
            $query->where('game_id', '=', $filters['game_id']);
        }

        $databaseId = $query->value('id');

        return $databaseId ? (int) $databaseId : null;
    }
}
