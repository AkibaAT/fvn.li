<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\DialogueSearchService;
use App\Services\DialogueWordFrequencyService;
use App\Services\MeilisearchService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DialogueController extends Controller
{
    public function dialogueBrowser(Request $request, Game $game): Response
    {
        $versionId = $request->route('versionId') ?? $request->input('versionId');

        if ($versionId !== null) {
            $version = $game->gameVersions()
                ->whereKey((int) $versionId)
                ->firstOrFail();
        } else {
            $version = $game->gameVersions()
                ->whereExists(function ($query) {
                    $query->select('id')
                        ->from('version_dialogue_lines')
                        ->whereColumn('version_dialogue_lines.game_version_id', 'game_versions.id')
                        ->limit(1);
                })
                ->orderByDesc('is_latest')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->firstOrFail();
        }

        $initial = [
            'gameId' => $game->id,
            'gameName' => $game->name,
            'gameSlug' => $game->slug,
            'versionId' => $version->id,
            'versionName' => $version->version,
            'versionPublishedAt' => $version->published_at?->toDateString(),
        ];

        return Inertia::render('dialogue/browser', [
            'initial' => $initial,
            'metaTags' => ['title' => 'Dialogue Browser - ' . $game->name],
        ]);
    }

    public function getDialogueData(Request $request): JsonResponse
    {
        $request->validate([
            'gameId' => 'nullable|integer|exists:games,id',
            'versionId' => 'nullable|integer|exists:game_versions,id',
            'q' => 'nullable|string|max:200',
            'page' => 'nullable|integer|min:1',
            'perPage' => 'nullable|integer|min:1|max:100',
            'selectedLanguages' => 'nullable',
        ]);

        $gameId = $request->integer('gameId');
        $versionId = $request->integer('versionId');
        $q = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('perPage', 25)));

        $selectedLanguagesParam = $request->input('selectedLanguages');
        $selectedLanguages = [];
        if (is_array($selectedLanguagesParam)) {
            $selectedLanguages = array_values(array_filter(array_map('strval', $selectedLanguagesParam)));
        } elseif (is_string($selectedLanguagesParam) && $selectedLanguagesParam !== '') {
            $selectedLanguages = array_values(array_filter(array_map('trim', explode(',', $selectedLanguagesParam))));
        }

        // Base query for items/summary - only include languages marked as available
        $base = DB::table('version_character_stats as vcs')
            ->join('game_versions as gv', 'gv.id', '=', 'vcs.game_version_id')
            ->join('characters as c', 'c.id', '=', 'vcs.character_id')
            ->join('iso_639_3_languages as l', 'l.id', '=', 'vcs.iso_code')
            ->join('version_supported_languages as vsl', function ($join) {
                $join->on('vsl.game_version_id', '=', 'gv.id')
                    ->on('vsl.iso_code', '=', 'vcs.iso_code');
            })
            ->where('vcs.iso_code', 'not like', 'q%')
            ->where('vsl.is_available', '=', true);

        if ($gameId) {
            $base->where('gv.game_id', '=', $gameId);
        }
        if ($versionId) {
            $base->where('gv.id', '=', $versionId);
        }
        if (! empty($selectedLanguages)) {
            $base->whereIn('l.id', $selectedLanguages);
        }

        $summaryQuery = clone $base;
        $summaryRow = $summaryQuery
            ->selectRaw('COALESCE(SUM(vcs.words), 0) as total_words')
            ->selectRaw('COUNT(DISTINCT vcs.character_id) as unique_characters')
            ->selectRaw('COUNT(DISTINCT vcs.iso_code) as languages_count')
            ->first();

        $languagesQuery = clone $base;
        $languages = $languagesQuery
            ->select('l.id as iso_code', 'l.ref_name', 'l.flag_code')
            ->distinct()
            ->orderBy('l.ref_name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->iso_code,
                'name' => $row->ref_name,
                'flag' => $row->flag_code,
            ])
            ->values();

        // Options: games with dialogue data
        $games = Cache::remember('dialogue.games_list', 3600, fn () => DB::table('games as g')
            ->join('game_versions as gv2', 'gv2.game_id', '=', 'g.id')
            ->join('version_character_stats as vcs2', 'vcs2.game_version_id', '=', 'gv2.id')
            ->select('g.id', 'g.name', 'g.slug')
            ->distinct()
            ->orderBy('g.name')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
            ])
            ->values());

        // Options: versions for a selected game (that have dialogue)
        $versions = collect();
        if ($gameId) {
            $versions = DB::table('game_versions as gv3')
                ->join('version_character_stats as vcs3', 'vcs3.game_version_id', '=', 'gv3.id')
                ->where('gv3.game_id', '=', $gameId)
                ->select('gv3.id', 'gv3.version', 'gv3.published_at')
                ->distinct()
                ->orderBy('gv3.published_at', 'desc')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'version' => (string) $row->version,
                    'published_at' => $row->published_at,
                ])
                ->values();
        }

        $itemsQuery = clone $base;
        if ($q !== '') {
            $like = '%' . str_replace('%', '\\%', $q) . '%';
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                // Search within JSON display_names and character_id for Postgres
                $itemsQuery->where(function ($w) use ($like) {
                    $w->whereRaw('c.display_names::text ilike ?', [$like])
                        ->orWhere('c.character_id', 'ilike', $like);
                });
            } else {
                // Generic fallback: LIKE on JSON text and character_id
                $itemsQuery->where(function ($w) use ($like) {
                    $w->where('c.display_names', 'like', $like)
                        ->orWhere('c.character_id', 'like', $like);
                });
            }
        }

        $itemsQuery
            ->selectRaw('vcs.game_version_id')
            ->selectRaw('c.id as character_id')
            ->selectRaw('c.character_id as character_key')
            ->selectRaw('c.display_names as character_display_names')
            ->selectRaw('l.id as language_id')
            ->selectRaw('l.ref_name as language_name')
            ->selectRaw('l.flag_code as language_flag')
            ->selectRaw('SUM(vcs.words) as words')
            ->groupBy('vcs.game_version_id', 'c.id', 'c.character_id', 'c.display_names', 'l.id', 'l.ref_name',
                'l.flag_code')
            ->orderBy('words', 'desc');

        $countQuery = DB::query()->fromSub($itemsQuery, 'items_count');
        $total = (int) ($countQuery->count());
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $data = DB::query()
            ->fromSub($itemsQuery, 'items')
            ->when($perPage > 0, fn ($q) => $q->offset($offset)->limit($perPage))
            ->get()
            ->map(function ($row) {
                // Compute character display name from JSON by language, fallback to English, then character key
                $displayNames = [];
                if (! empty($row->character_display_names)) {
                    if (is_array($row->character_display_names)) {
                        $displayNames = $row->character_display_names;
                    } else {
                        $decoded = json_decode((string) $row->character_display_names, true);
                        if (is_array($decoded)) {
                            $displayNames = $decoded;
                        }
                    }
                }
                $lang = (string) $row->language_id;
                $characterName = $displayNames[$lang] ?? ($displayNames['eng'] ?? (string) $row->character_key);

                return [
                    'game_version_id' => (int) $row->game_version_id,
                    'character' => [
                        'id' => (int) $row->character_id,
                        'name' => $characterName,
                    ],
                    'language' => [
                        'id' => $row->language_id,
                        'name' => $row->language_name,
                        'flag' => $row->language_flag,
                    ],
                    'words' => (int) $row->words,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'filters' => [
                'gameId' => $gameId,
                'versionId' => $versionId,
                'q' => $q,
                'page' => $page,
                'perPage' => $perPage,
                'selectedLanguages' => $selectedLanguages,
            ],
            'games' => $games,
            'versions' => $versions,
            'summary' => [
                'totalLines' => (int) ($summaryRow->total_words ?? 0),
                'uniqueCharacters' => (int) ($summaryRow->unique_characters ?? 0),
                'languages' => $languages,
            ],
            'items' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ]);
    }

    /**
     * Options for the Dialogue Browser (versions, characters, contexts, languages)
     * gameId is now required - we only fetch data for a specific game
     */
    public function getDialogueOptions(Request $request): JsonResponse
    {
        $request->validate([
            'gameId' => 'required|integer|exists:games,id',
            'versionId' => 'nullable|integer|exists:game_versions,id',
            'language' => 'nullable|string|size:3',
        ]);

        $gameId = $request->integer('gameId');
        $versionId = $request->integer('versionId');
        $language = $request->input('language', 'eng');

        // Versions with dialogue for the specific game - much faster query
        $versions = DB::table('game_versions as gv')
            ->where('gv.game_id', '=', $gameId)
            ->whereExists(function ($q) {
                $q->select('id')
                    ->from('version_dialogue_lines as vdl')
                    ->whereColumn('vdl.game_version_id', 'gv.id')
                    ->limit(1);
            })
            ->select('gv.id', 'gv.version', 'gv.published_at')
            ->orderBy('gv.published_at', 'desc')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'version' => (string) $r->version,
                'published_at' => $r->published_at,
            ])
            ->values();

        // Languages available for this specific game - only show languages marked as available
        $languages = DB::table('iso_639_3_languages as l')
            ->whereExists(function ($q) use ($gameId, $versionId) {
                $q->select('vdl.id')
                    ->from('version_dialogue_lines as vdl')
                    ->join('game_versions as gv', 'gv.id', '=', 'vdl.game_version_id')
                    ->join('version_supported_languages as vsl', function ($join) {
                        $join->on('vsl.game_version_id', '=', 'gv.id')
                            ->on('vsl.iso_code', '=', 'vdl.iso_code');
                    })
                    ->where('gv.game_id', '=', $gameId)
                    ->where('vsl.is_available', '=', true)
                    ->when($versionId, fn ($q) => $q->where('gv.id', '=', $versionId))
                    ->whereColumn('vdl.iso_code', 'l.id')
                    ->limit(1);
            })
            ->orderBy('l.ref_name')
            ->get()
            ->map(fn ($r) => ['id' => (string) $r->id, 'name' => (string) $r->ref_name, 'flag' => $r->flag_code])
            ->values();

        // Characters and contexts for selected version+language
        $characters = collect();
        $contexts = collect();
        if ($versionId) {
            $characters = DB::table('version_dialogue_lines as vdl')
                ->join('characters as c', 'c.id', '=', 'vdl.character_id')
                ->where('vdl.game_version_id', '=', $versionId)
                ->when($language, fn ($q) => $q->where('vdl.iso_code', '=', $language))
                ->select('c.id', 'c.character_id', 'c.display_names')
                ->distinct()
                ->get()
                ->map(function ($r) use ($language) {
                    $displayNames = [];
                    if ($r->display_names) {
                        $decoded = json_decode((string) $r->display_names, true);
                        if (is_array($decoded)) {
                            $displayNames = $decoded;
                        }
                    }
                    $name = $displayNames[$language] ?? ($displayNames['eng'] ?? (string) $r->character_id);

                    return [
                        'id' => (int) $r->id,
                        'character_id' => (string) $r->character_id,
                        'name' => $name,
                    ];
                })
                ->sortBy('name')
                ->values();

            $contexts = DB::table('version_dialogue_lines as vdl')
                ->where('vdl.game_version_id', '=', $versionId)
                ->when($language, fn ($q) => $q->where('vdl.iso_code', '=', $language))
                ->whereNotNull('vdl.context')
                ->distinct()
                ->orderBy('vdl.context')
                ->pluck('vdl.context')
                ->values();
        }

        return response()->json([
            'success' => true,
            'versions' => $versions,
            'languages' => $languages,
            'characters' => $characters,
            'contexts' => $contexts,
        ]);
    }

    /**
     * Search dialogue texts using Meilisearch, then fetch actual dialogue lines.
     * Returns dialogue lines with full context information.
     */
    public function searchDialogue(Request $request, DialogueSearchService $service): JsonResponse
    {
        $request->validate([
            'q' => 'required|string',
            'language' => 'nullable|string|size:3',
            'gameId' => 'nullable|integer|exists:games,id',
            'versionId' => 'nullable|integer|exists:game_versions,id',
            'characterId' => 'nullable|string', // character_id key
            'context' => 'nullable|string',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'exactMatch' => 'nullable|boolean',
        ]);

        $filters = [
            'language' => $request->input('language', 'eng'),
            'game_id' => $request->integer('gameId') ?: null,
            'version_id' => $request->integer('versionId') ?: null,
            'character_id' => $request->input('characterId') ?: null,
            'context' => $request->input('context') ?: null,
            'exact_match' => $request->boolean('exactMatch', false),
        ];

        $perPage = min(100, max(1, (int) $request->input('perPage', 20)));
        $page = max(1, (int) $request->input('page', 1));

        $paginator = $service->search($request->input('q'), $filters, $perPage, $page);

        // Transform DialogueLine results to include full context information
        $transformedData = collect($paginator->items())->map(function ($line) use ($filters) {
            // Get character display name
            $characterName = null;
            if ($line->character) {
                $language = $filters['language'] ?? 'eng';
                $displayNames = $line->character->display_names ?? [];
                $characterName = $displayNames[$language] ?? ($displayNames['eng'] ?? $line->character->character_id);
            }

            return [
                'id' => $line->id,
                'text_content' => $line->text_content,
                'highlighted_text' => $line->highlighted_text ?? $line->text_content,
                'context' => $line->context,
                'file_path' => $line->file_path,
                'line_number' => $line->line_number,
                'character_id' => $line->character?->character_id,
                'character_name' => $characterName,
                'iso_code' => $line->iso_code,
                'game_version_id' => $line->game_version_id,
                'game' => $line->gameVersion?->game ? [
                    'id' => $line->gameVersion->game->id,
                    'name' => $line->gameVersion->game->name,
                ] : null,
                'version' => $line->gameVersion ? [
                    'id' => $line->gameVersion->id,
                    'version' => $line->gameVersion->version,
                ] : null,
                'first_seen_version' => ! empty($line->first_seen_version['id']) ? [
                    'id' => (int) $line->first_seen_version['id'],
                    'version' => (string) $line->first_seen_version['version'],
                    'published_at' => $line->first_seen_version['published_at'],
                ] : null,
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => $transformedData,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get top duplicate lines similar to production behavior.
     */
    public function duplicateDialogue(Request $request, DialogueSearchService $service): JsonResponse
    {
        $request->validate([
            'language' => 'nullable|string|size:3',
            'gameId' => 'nullable|integer|exists:games,id',
            'versionId' => 'nullable|integer|exists:game_versions,id',
            'characterId' => 'nullable|string', // character_id key
            'minLineLength' => 'nullable|integer|min:0|max:2000',
            'minDuplicateCount' => 'nullable|integer|min:2|max:1000',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $filters = [
            'language' => $request->input('language', 'eng'),
            'game_id' => $request->integer('gameId') ?: null,
            'version_id' => $request->integer('versionId') ?: null,
            'character_id' => $request->input('characterId') ?: null,
            'min_length' => $request->integer('minLineLength') ?: 10,
            'min_count' => $request->integer('minDuplicateCount') ?: 3,
        ];

        $limit = min(200, max(1, (int) $request->input('limit', 10)));
        $data = $service->getTopDuplicates($filters, $limit);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Version statistics summary, mirroring production statistics panel
     */
    public function versionStats(Request $request, DialogueSearchService $service): JsonResponse
    {
        $request->validate(['versionId' => 'required|integer|exists:game_versions,id']);
        /** @var GameVersion $version */
        $version = GameVersion::find($request->integer('versionId'));
        $stats = $service->getVersionStatistics($version);

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Enhanced search using Meilisearch for better performance and features.
     */
    public function searchDialogueEnhanced(Request $request, MeilisearchService $service): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1',
            'language' => 'nullable|string|size:3',
            'gameNames' => 'nullable|array',
            'gameNames.*' => 'string',
            'characterNames' => 'nullable|array',
            'characterNames.*' => 'string',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $filters = array_filter([
            'language' => $request->input('language'),
            'game_names' => $request->input('gameNames'),
            'character_names' => $request->input('characterNames'),
        ]);

        $perPage = min(100, max(1, (int) $request->input('perPage', 20)));
        $page = max(1, (int) $request->input('page', 1));

        try {
            $paginator = $service->searchDialogue($request->input('q'), $filters, $perPage, $page);

            return response()->json([
                'success' => true,
                'data' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'search_engine' => 'meilisearch',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while searching',
            ], 500);
        }
    }

    /**
     * Get word frequency data for a word cloud visualization.
     * Returns the most common words and phrases used in dialogue.
     */
    public function getWordFrequency(Request $request, DialogueWordFrequencyService $service): JsonResponse
    {
        $request->validate([
            'versionId' => 'required|integer|exists:game_versions,id',
            'language' => 'nullable|string|size:3',
            'limit' => 'nullable|integer|min:10|max:200',
            'includePhrases' => 'nullable|in:true,false,1,0',
            'minWordLength' => 'nullable|integer|min:1|max:10',
        ]);

        $versionId = $request->integer('versionId');
        $language = $request->input('language', 'eng');
        $limit = min(200, max(10, (int) $request->input('limit', 100)));
        $includePhrases = $request->boolean('includePhrases', true);
        $minWordLength = max(1, min(10, (int) $request->input('minWordLength', 3)));

        $result = $service->calculate($versionId, $language, $limit, $includePhrases, $minWordLength);
        $status = $result['status'] ?? 200;
        unset($result['status']);

        return response()->json($result, $status);
    }
}
