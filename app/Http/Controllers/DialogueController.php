<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\DialogueSearchService;
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
    private const WORD_FREQUENCY_MAX_ROWS = 10000;

    private const WORD_FREQUENCY_MAX_CHARACTERS = 2000000;

    public function dialogueBrowser(Request $request, Game $game): Response
    {
        $versionId = $request->route('versionId') ?? $request->input('versionId');

        $versionQuery = $game->gameVersions()
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('version_dialogue_lines')
                    ->whereColumn('version_dialogue_lines.game_version_id', 'game_versions.id')
                    ->limit(1);
            });

        if ($versionId !== null) {
            $versionQuery->whereKey((int) $versionId);
        } else {
            $versionQuery
                ->orderByDesc('is_latest')
                ->orderByDesc('published_at')
                ->orderByDesc('id');
        }

        $version = $versionQuery->firstOrFail();

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
            'metaTags' => ['title' => 'Dialogue Browser - '.$game->name],
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
            $like = '%'.str_replace('%', '\\%', $q).'%';
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
    public function getWordFrequency(Request $request): JsonResponse
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

        // Try to read from pre-calculated cache first (default parameters: limit=100, includePhrases=true, minWordLength=3)
        if ($limit === 100 && $includePhrases === true && $minWordLength === 3) {
            $cached = DB::table('version_word_frequencies')
                ->where('game_version_id', '=', $versionId)
                ->where('iso_code', '=', $language)
                ->first();

            if ($cached) {
                $wordData = json_decode($cached->word_data, true);

                return response()->json([
                    'success' => true,
                    'data' => $wordData ?? [],
                    'cached' => true,
                    'calculated_at' => $cached->calculated_at,
                ]);
            }
        }

        $baseQuery = DB::table('version_dialogue_lines as vdl')
            ->join('unique_dialogue_texts as udt', 'udt.id', '=', 'vdl.text_id')
            ->where('vdl.game_version_id', '=', $versionId)
            ->where('vdl.iso_code', '=', $language)
            ->whereNotNull('udt.text_content');

        $corpusStats = (clone $baseQuery)
            ->selectRaw('COUNT(*) as row_count, COALESCE(SUM(CHAR_LENGTH(udt.text_content)), 0) as total_characters')
            ->first();

        $rowCount = (int) ($corpusStats?->row_count ?? 0);
        $totalCharacters = (int) ($corpusStats?->total_characters ?? 0);

        if ($rowCount === 0) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        if ($rowCount > self::WORD_FREQUENCY_MAX_ROWS || $totalCharacters > self::WORD_FREQUENCY_MAX_CHARACTERS) {
            return response()->json([
                'success' => false,
                'message' => 'Requested dialogue corpus is too large to process on demand.',
            ], 422);
        }

        $dialogueTexts = (clone $baseQuery)
            ->select('udt.text_content')
            ->orderBy('vdl.id')
            ->cursor();

        // Common English stop words to filter out
        $stopWords = [
            // Articles, pronouns, possessives
            'the', 'a', 'an', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'them',
            'me', 'him', 'her', 'us', 'my', 'your', 'his', 'her', 'its', 'our', 'their',
            'myself', 'yourself', 'himself', 'herself', 'itself', 'ourselves', 'themselves',

            // Common verbs and contractions
            'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'may', 'might', 'must',
            'can', 'could', 'ought', 'i\'m', 'you\'re', 'he\'s', 'she\'s', 'it\'s', 'we\'re',
            'they\'re', 'i\'ve', 'you\'ve', 'we\'ve', 'they\'ve', 'i\'d', 'you\'d', 'he\'d',
            'she\'d', 'we\'d', 'they\'d', 'i\'ll', 'you\'ll', 'he\'ll', 'she\'ll', 'we\'ll',
            'they\'ll', 'isn\'t', 'aren\'t', 'wasn\'t', 'weren\'t', 'hasn\'t', 'haven\'t',
            'hadn\'t', 'doesn\'t', 'don\'t', 'didn\'t', 'won\'t', 'wouldn\'t', 'shan\'t',
            'shouldn\'t', 'can\'t', 'cannot', 'couldn\'t', 'mustn\'t', 'let\'s', 'that\'s',
            'who\'s', 'what\'s', 'here\'s', 'there\'s', 'when\'s', 'where\'s', 'why\'s', 'how\'s',

            // Prepositions and conjunctions
            'in', 'on', 'at', 'to', 'for', 'of', 'with', 'from', 'by', 'about', 'as',
            'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between',
            'under', 'around', 'among', 'and', 'but', 'or', 'nor', 'so', 'yet', 'because',
            'although', 'though', 'while', 'if', 'than', 'that', 'whether', 'till', 'until',
            'not', 'over',

            // Question words and demonstratives
            'what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how',
            'this', 'that', 'these', 'those', 'here', 'there',

            // Common adverbs and intensifiers
            'very', 'really', 'quite', 'too', 'so', 'just', 'only', 'even', 'also', 'still',
            'already', 'always', 'never', 'often', 'sometimes', 'usually', 'perhaps', 'maybe',
            'probably', 'certainly', 'definitely', 'surely', 'absolutely', 'completely',
            'totally', 'entirely', 'exactly', 'nearly', 'almost', 'hardly', 'barely',
            'right', 'well', 'now', 'then', 'again', 'away', 'off', 'down', 'up', 'out',

            // Common verbs (conversational)
            'go', 'went', 'gone', 'going', 'come', 'came', 'get', 'got', 'getting', 'make',
            'made', 'making', 'take', 'took', 'taken', 'taking', 'give', 'gave', 'given',
            'say', 'said', 'saying', 'know', 'knew', 'known', 'knowing', 'think', 'thought',
            'see', 'saw', 'seen', 'want', 'wanted', 'look', 'looked', 'looking', 'need',
            'use', 'find', 'tell', 'ask', 'work', 'seem', 'feel', 'try', 'leave', 'call',
            'keep', 'let', 'begin', 'help', 'show', 'hear', 'play', 'run', 'move', 'live',
            'believe', 'bring', 'happen', 'write', 'sit', 'stand', 'lose', 'pay', 'meet',
            'include', 'continue', 'set', 'learn', 'change', 'lead', 'understand', 'watch',

            // Common adjectives and quantities
            'good', 'new', 'first', 'last', 'long', 'great', 'little', 'own', 'other', 'old',
            'right', 'big', 'high', 'different', 'small', 'large', 'next', 'early', 'young',
            'important', 'few', 'public', 'bad', 'same', 'able', 'nice', 'sure', 'okay',
            'fine', 'better', 'best', 'worse', 'worst', 'much', 'many', 'more', 'most', 'less',
            'least', 'some', 'any', 'every', 'all', 'both', 'each', 'few', 'more', 'other',
            'another', 'such', 'one', 'two', 'three', 'four', 'five',

            // Conversational filler words
            'yeah', 'yes', 'yep', 'nope', 'nah', 'okay', 'ok', 'hey', 'oh', 'ah', 'um', 'uh',
            'hmm', 'huh', 'wow', 'well', 'like', 'guess', 'suppose', 'mean', 'actually',
            'something', 'anything', 'everything', 'nothing', 'someone', 'anyone', 'everyone',
            'nobody', 'somewhere', 'anywhere', 'everywhere', 'nowhere', 'gonna', 'wanna', 'gotta',

            // Common nouns (too generic)
            'time', 'year', 'day', 'thing', 'things', 'way', 'man', 'people', 'world',
            'life', 'hand', 'part', 'place', 'case', 'week', 'company', 'system', 'program',
            'question', 'work', 'government', 'number', 'night', 'point', 'home', 'water',
            'room', 'mother', 'area', 'money', 'story', 'fact', 'month', 'lot', 'moment',
            'side', 'kind', 'head', 'house', 'service', 'friend', 'father', 'power', 'hour',
            'game', 'line', 'end', 'member', 'law', 'car', 'city', 'community', 'name',
            'president', 'team', 'minute', 'idea', 'kid', 'body', 'information', 'back',
            'parent', 'face', 'others', 'level', 'office', 'door', 'health', 'person',
            'art', 'war', 'history', 'party', 'result', 'change', 'morning', 'reason',
            'research', 'girl', 'guy', 'guys', 'moment', 'air', 'teacher', 'force', 'education',
        ];

        $wordCounts = [];
        $phraseCounts = [];

        // Process each dialogue text
        foreach ($dialogueTexts as $row) {
            // Convert to lowercase and remove special characters, keeping spaces
            $cleaned = strtolower((string) $row->text_content);
            $cleaned = preg_replace('/[^\p{L}\p{N}\s\-\']/u', ' ', $cleaned);
            $cleaned = preg_replace('/\s+/', ' ', $cleaned);
            $cleaned = trim($cleaned);

            // Split into words
            $words = explode(' ', $cleaned);
            $words = array_values(array_filter($words, fn ($w) => strlen($w) >= $minWordLength));

            // Count individual words
            foreach ($words as $word) {
                $word = trim($word);
                if (! in_array($word, $stopWords, true) && strlen($word) >= $minWordLength) {
                    $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
                }
            }

            // Count 2-word and 3-word phrases if requested
            if ($includePhrases && count($words) >= 2) {
                // Bigrams (2-word phrases)
                for ($i = 0; $i < count($words) - 1; $i++) {
                    $phrase = $words[$i].' '.$words[$i + 1];
                    // Only count if phrase is meaningful (not all stop words)
                    if (! (in_array($words[$i], $stopWords, true) && in_array($words[$i + 1], $stopWords, true))) {
                        $phraseCounts[$phrase] = ($phraseCounts[$phrase] ?? 0) + 1;
                    }
                }

                // Trigrams (3-word phrases)
                for ($i = 0; $i < count($words) - 2; $i++) {
                    $phrase = $words[$i].' '.$words[$i + 1].' '.$words[$i + 2];
                    $phraseCounts[$phrase] = ($phraseCounts[$phrase] ?? 0) + 1;
                }
            }
        }

        // Sort by frequency and combine words and phrases
        arsort($wordCounts);
        arsort($phraseCounts);

        // Take top words and phrases, then combine them
        $topWords = array_slice($wordCounts, 0, (int) ($limit * 0.7), true);
        $topPhrases = $includePhrases ? array_slice($phraseCounts, 0, (int) ($limit * 0.3), true) : [];

        $combined = [];
        foreach ($topWords as $text => $count) {
            $combined[] = ['text' => $text, 'value' => $count];
        }
        foreach ($topPhrases as $text => $count) {
            $combined[] = ['text' => $text, 'value' => $count];
        }

        // Sort combined by value descending
        usort($combined, fn ($a, $b) => $b['value'] <=> $a['value']);

        // Limit to requested count
        $result = array_slice($combined, 0, $limit);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
