<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameJam;
use App\Models\Tag;
use App\Services\GameFilterService;
use App\Services\MeilisearchService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class GamesSearchController extends Controller
{
    /**
     * Display games with search and filtering capabilities
     */
    public function index(Request $request, MeilisearchService $service): Response
    {
        $search = $request->get('search', '');
        $selectedStatuses = $request->get('selectedStatuses');
        $selectedEngines = $request->get('selectedEngines');
        $selectedPlatforms = $request->get('selectedPlatforms');
        $selectedStorePlatforms = $request->get('selectedStorePlatforms');
        $selectedLanguages = $request->get('selectedLanguages');
        $selectedGameJams = $request->get('selectedGameJams');
        $selectedTags = $request->get('selectedTags');

        // Smart default sorting: relevance for search, date for browsing
        $isSearching = ! empty(trim($search ?? ''));
        $defaultSort = $isSearching ? 'relevance' : 'first_visible_at';
        $sortField = $request->get('sort', $defaultSort);
        $sortDirection = $request->get('direction', 'desc');
        $perPage = min(32, max(8, (int) $request->get('perPage', 8)));

        // Build filters
        $filters = $this->buildFilters($request, $selectedStatuses, $selectedEngines, $selectedPlatforms, $selectedStorePlatforms, $selectedLanguages, $selectedGameJams, $selectedTags);

        // Get ignored game IDs for authenticated users (unless they want to show ignored)
        $allIgnoredGameIds = [];
        $ignoredGameIds = [];
        $ignoredCount = 0;
        if (Auth::check()) {
            $allIgnoredGameIds = Auth::user()->ignoredGames()->pluck('games.id')->toArray();

            // Only filter out ignored games if showIgnored is false
            if (! $request->boolean('showIgnored')) {
                // Count how many ignored games match the current filters
                // This will be shown in the info bar
                $ignoredCount = count($allIgnoredGameIds);
                $ignoredGameIds = $allIgnoredGameIds;
            }
        }

        // Use Meilisearch for search and filtering
        try {
            $searchQuery = trim($search ?? '') ?: '*';
            $games = $service->searchGames($searchQuery, $filters, $perPage, (int) $request->get('page', 1), $sortField, $sortDirection, $ignoredGameIds);

            Log::info('Meilisearch success', [
                'query' => $searchQuery,
                'total' => $games->total(),
                'items' => count($games->items()),
            ]);
        } catch (Exception $e) {
            Log::error('Meilisearch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to basic database search
            $games = $this->fallbackSearch($search, $perPage, (int) $request->get('page', 1), $ignoredGameIds);
        }

        // Load essential relationships for the frontend
        if ($games->count() > 0) {
            // Load relationships to prevent N+1 queries
            $games->load([
                'tags',
                'latestVersion.supportedLanguages.language',
                'latestVersion.languageStats',
            ]);

            // Enhance models with data from loaded relationships only (no additional queries)
            foreach ($games as $game) {
                // Set platform flags from the latest version relationship
                if ($game->latestVersion) {
                    $game->is_windows = $game->latestVersion->is_windows ?? false;
                    $game->is_linux = $game->latestVersion->is_linux ?? false;
                    $game->is_mac = $game->latestVersion->is_mac ?? false;
                    $game->is_android = $game->latestVersion->is_android ?? false;
                    $game->is_web = $game->latestVersion->is_web ?? false;
                    $game->latest_version_id = $game->latestVersion->id;
                    $game->latest_version_published_at = $game->latestVersion->published_at;
                } else {
                    $game->is_windows = false;
                    $game->is_linux = false;
                    $game->is_mac = false;
                    $game->is_android = false;
                    $game->is_web = false;
                    $game->latest_version_id = null;
                    $game->latest_version_published_at = null;
                }

                // Set supported languages using the relationship data (with underscore for frontend)
                if ($game->latestVersion && $game->latestVersion->supportedLanguages) {
                    $game->supported_languages = $game->latestVersion->supportedLanguages
                        ->where('is_available', true)
                        ->map(function ($supportedLanguage) {
                            return [
                                'iso_code' => $supportedLanguage->iso_code,
                                'is_available' => $supportedLanguage->is_available,
                                'ref_name' => $supportedLanguage->language?->ref_name,
                                'flag_code' => $supportedLanguage->language?->flag_code,
                            ];
                        })
                        ->values();
                } else {
                    $game->supported_languages = collect();
                }

                // Set english_word_count from the latest version (same pattern as supported_languages)
                if ($game->latestVersion) {
                    $englishStats = $game->latestVersion->languageStats
                        ->where('iso_code', 'eng')
                        ->first();
                    $game->english_word_count = $englishStats?->words;
                } else {
                    $game->english_word_count = null;
                }
            }
        }

        // Load user-specific data if authenticated and we have games
        if (Auth::check() && $games->count() > 0) {
            $gameIds = collect($games->items())->pluck('id')->toArray();

            if (! empty($gameIds)) {
                // Load user progress
                $userProgress = DB::table('user_game_progress')
                    ->where('user_id', Auth::id())
                    ->whereIn('game_id', $gameIds)
                    ->select('game_id', 'receive_updates')
                    ->get()
                    ->keyBy('game_id');

                // Load list memberships
                $userListMemberships = DB::table('vn_list_entries')
                    ->join('vn_lists', 'vn_list_entries.vn_list_id', '=', 'vn_lists.id')
                    ->where('vn_lists.user_id', Auth::id())
                    ->whereIn('vn_list_entries.game_id', $gameIds)
                    ->select('vn_list_entries.game_id', 'vn_lists.id as list_id', 'vn_lists.name', 'vn_lists.type', 'vn_lists.is_default')
                    ->get()
                    ->groupBy('game_id');

                // Attach user data to each game object
                foreach ($games->items() as $game) {
                    // Wrap user_progress in array to match Eloquent relationship format
                    $progress = $userProgress->get($game->id);
                    $game->user_progress = $progress ? [$progress] : [];
                    $game->user_list_memberships = $userListMemberships->get($game->id, collect())->toArray();
                }
            }
        }

        // Build meta tags with filter information
        $filterOptions = GameFilterService::getOptions();
        $metaTags = $this->buildMetaTags(
            $request,
            $search,
            $selectedStatuses,
            $selectedEngines,
            $selectedPlatforms,
            $selectedLanguages,
            $selectedTags,
            $games->total(),
            $filterOptions,
            $games
        );

        return Inertia::render('games/index', [
            'games' => $games,
            'currentFilters' => [
                'search' => $search,
                'selectedStatuses' => is_array($selectedStatuses) ? $selectedStatuses : [],
                'selectedEngines' => is_array($selectedEngines) ? $selectedEngines : [],
                'selectedPlatforms' => is_array($selectedPlatforms) ? $selectedPlatforms : [],
                'selectedStorePlatforms' => is_array($selectedStorePlatforms) ? $selectedStorePlatforms : [],
                'selectedLanguages' => is_array($selectedLanguages) ? $selectedLanguages : [],
                'selectedGameJams' => is_array($selectedGameJams) ? $selectedGameJams : [],
                'selectedTags' => is_array($selectedTags) ? $selectedTags : [],
                'nsfw' => $request->boolean('nsfw'),
                'sfw' => $request->boolean('sfw'),
                'showPaid' => $request->boolean('showPaid'),
                'showFree' => $request->boolean('showFree'),
                'showDemo' => $request->boolean('showDemo'),
                'showSale' => $request->boolean('showSale'),
                'showIgnored' => $request->boolean('showIgnored'),
                'sort' => $sortField,
                'direction' => $sortDirection,
                'perPage' => $perPage,
                'page' => (int) $request->get('page', 1),
            ],
            'filters' => $filterOptions,
            'metaTags' => $metaTags,
            'ignoredCount' => $ignoredCount,
            'ignoredGameIds' => $allIgnoredGameIds,
        ]);
    }

    /**
     * Simple API search for autocomplete
     */
    public function searchGames(Request $request): JsonResponse
    {
        $query = Game::query()
            ->select(['games.*'])
            ->fromItchio()
            ->where('is_visible', true);

        if ($search = $request->get('q')) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('games.name', 'ilike', $searchTerm)
                    ->orWhere('games.authors', 'ilike', $searchTerm)
                    ->orWhere('games.custom_tags', 'ilike', $searchTerm);
            });
        }

        $games = $query->limit(10)->get(['id', 'name', 'slug', 'cover_image']);

        return response()->json($games);
    }

    /**
     * Enhanced game search using Meilisearch
     */
    public function searchGamesEnhanced(Request $request, MeilisearchService $service): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1',
            'status' => 'nullable|array',
            'status.*' => 'string|in:released,in_development,prototype,canceled',
            'is_nsfw' => 'nullable|boolean',
            'is_paid' => 'nullable|boolean',
            'has_demo' => 'nullable|boolean',
            'game_engine' => 'nullable|array',
            'game_engine.*' => 'string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'game_jams' => 'nullable|array',
            'game_jams.*' => 'string',
            'supported_languages' => 'nullable|array',
            'supported_languages.*' => 'string',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        // Convert tag IDs to tag names if tags are provided
        $tags = $request->input('tags');
        if ($tags) {
            $tagIds = is_array($tags) ? $tags : explode(',', $tags);
            $tagNames = Tag::whereIn('id', $tagIds)
                ->pluck('name')
                ->toArray();
            $tags = ! empty($tagNames) ? $tagNames : null;
        }

        // Convert game jam IDs to game jam names if game jams are provided
        $gameJams = $request->input('game_jams');
        if ($gameJams) {
            $gameJamIds = is_array($gameJams) ? $gameJams : explode(',', $gameJams);
            $gameJamNames = GameJam::whereIn('id', $gameJamIds)
                ->pluck('name')
                ->toArray();
            $gameJams = ! empty($gameJamNames) ? $gameJamNames : null;
        }

        $filters = array_filter([
            'status' => $request->input('status'),
            'is_nsfw' => $request->boolean('is_nsfw', null),
            'is_paid' => $request->boolean('is_paid', null),
            'has_demo' => $request->boolean('has_demo', null),
            'game_engine' => $request->input('game_engine'),
            'tags' => $tags,
            'game_jams' => $gameJams,
            'supported_languages' => $request->input('supported_languages'),
        ], fn ($value) => $value !== null);

        $filters['is_visible'] = true;

        try {
            $perPage = $request->integer('perPage', 20);
            $page = $request->integer('page', 1);
            $games = $service->searchGames($request->input('q'), $filters, $perPage, $page);

            return response()->json($games);
        } catch (Exception $e) {
            Log::error('Enhanced search failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Search unavailable'], 503);
        }
    }

    /**
     * Global search across multiple content types
     */
    public function globalSearch(Request $request, MeilisearchService $service): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
        ]);

        try {
            $results = $service->globalSearch($request->input('q'));

            return response()->json($results);
        } catch (Exception $e) {
            Log::error('Global search failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Search unavailable'], 503);
        }
    }

    /**
     * Build search filters from request parameters
     */
    private function buildFilters(Request $request, $selectedStatuses, $selectedEngines, $selectedPlatforms, $selectedStorePlatforms, $selectedLanguages, $selectedGameJams, $selectedTags): array
    {
        $filters = [];

        // Always filter to visible games only
        $filters['is_visible'] = true;

        // Status filter
        if ($selectedStatuses) {
            $statuses = is_array($selectedStatuses) ? $selectedStatuses : explode(',', $selectedStatuses);
            $filters['status'] = $statuses;
        }

        // Engine filter
        if ($selectedEngines) {
            $engines = is_array($selectedEngines) ? $selectedEngines : explode(',', $selectedEngines);
            $filters['game_engine'] = $engines;
        }

        // Platform filters (game OS support)
        if ($selectedPlatforms) {
            $platforms = is_array($selectedPlatforms) ? $selectedPlatforms : explode(',', $selectedPlatforms);
            foreach ($platforms as $platform) {
                $filters["is_{$platform}"] = true;
            }
        }

        // Store platform filters (where the game is hosted)
        if ($selectedStorePlatforms) {
            $storePlatforms = is_array($selectedStorePlatforms) ? $selectedStorePlatforms : explode(',', $selectedStorePlatforms);
            $filters['platform'] = $storePlatforms;
        }

        // Language filter
        if ($selectedLanguages) {
            $languages = is_array($selectedLanguages) ? $selectedLanguages : explode(',', $selectedLanguages);
            $filters['supported_languages'] = $languages;
        }

        // Tags filter - convert tag IDs to tag names for search
        if ($selectedTags) {
            $tagIds = is_array($selectedTags) ? $selectedTags : explode(',', $selectedTags);
            // Convert tag IDs to tag names since search index stores names, not IDs
            $tagNames = Tag::whereIn('id', $tagIds)
                ->pluck('name')
                ->toArray();
            if (! empty($tagNames)) {
                $filters['tags'] = $tagNames;
            }
        }

        // Game jams filter - convert game jam IDs to game jam names for search
        if ($selectedGameJams) {
            $gameJamIds = is_array($selectedGameJams) ? $selectedGameJams : explode(',', $selectedGameJams);
            // Convert game jam IDs to game jam names since search index stores names, not IDs
            $gameJamNames = GameJam::whereIn('id', $gameJamIds)
                ->pluck('name')
                ->toArray();
            if (! empty($gameJamNames)) {
                $filters['game_jams'] = $gameJamNames;
            }
        }

        // NSFW/SFW filters
        $nsfw = $request->boolean('nsfw');
        $sfw = $request->boolean('sfw');
        if ($nsfw && ! $sfw) {
            $filters['is_nsfw'] = true;
        } elseif ($sfw && ! $nsfw) {
            $filters['is_nsfw'] = false;
        }

        // Paid/Free filters
        $showPaid = $request->boolean('showPaid');
        $showFree = $request->boolean('showFree');
        if ($showPaid && ! $showFree) {
            $filters['is_paid'] = true;
        } elseif ($showFree && ! $showPaid) {
            $filters['is_paid'] = false;
        }

        // Demo filter
        if ($request->boolean('showDemo')) {
            $filters['has_demo'] = true;
        }

        // Sale filter
        if ($request->boolean('showSale')) {
            $filters['is_on_sale'] = true;
        }

        return $filters;
    }

    /**
     * Fallback database search when Meilisearch fails
     */
    private function fallbackSearch(?string $search, int $perPage, int $page, array $ignoredGameIds = [])
    {
        $query = Game::query()
            ->fromItchio()
            ->where('is_visible', true)
            ->with([
                'tags',
                'latestVersion.supportedLanguages.language',
                'latestVersion.languageStats',
            ])
            ->withCount('ratings');

        // Exclude ignored games
        if (! empty($ignoredGameIds)) {
            $query->whereNotIn('games.id', $ignoredGameIds);
        }

        if (! empty(trim($search))) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', $searchTerm)
                    ->orWhere('authors', 'ilike', $searchTerm)
                    ->orWhere('custom_tags', 'ilike', $searchTerm);
            });
        }

        $games = $query->orderBy('first_visible_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // Apply the same data transformation as the main search
        if ($games->count() > 0) {
            foreach ($games as $game) {
                // Set platform flags from the latest version relationship
                if ($game->latestVersion) {
                    $game->is_windows = $game->latestVersion->is_windows ?? false;
                    $game->is_linux = $game->latestVersion->is_linux ?? false;
                    $game->is_mac = $game->latestVersion->is_mac ?? false;
                    $game->is_android = $game->latestVersion->is_android ?? false;
                    $game->is_web = $game->latestVersion->is_web ?? false;
                    $game->latest_version_id = $game->latestVersion->id;
                    $game->latest_version_published_at = $game->latestVersion->published_at;
                } else {
                    $game->is_windows = false;
                    $game->is_linux = false;
                    $game->is_mac = false;
                    $game->is_android = false;
                    $game->is_web = false;
                    $game->latest_version_id = null;
                    $game->latest_version_published_at = null;
                }

                // Set supported languages using the relationship data
                if ($game->latestVersion && $game->latestVersion->supportedLanguages) {
                    $game->supported_languages = $game->latestVersion->supportedLanguages
                        ->where('is_available', true)
                        ->map(function ($supportedLanguage) {
                            return [
                                'iso_code' => $supportedLanguage->iso_code,
                                'is_available' => $supportedLanguage->is_available,
                                'ref_name' => $supportedLanguage->language?->ref_name,
                                'flag_code' => $supportedLanguage->language?->flag_code,
                            ];
                        })
                        ->values();
                } else {
                    $game->supported_languages = collect();
                }

                // Set english_word_count from the latest version
                if ($game->latestVersion) {
                    $englishStats = $game->latestVersion->languageStats
                        ->where('iso_code', 'eng')
                        ->first();
                    $game->english_word_count = $englishStats?->words;
                } else {
                    $game->english_word_count = null;
                }
            }
        }

        return $games;
    }

    /**
     * Build comprehensive meta tags with filter information for social media
     */
    private function buildMetaTags(
        Request $request,
        ?string $search,
        $selectedStatuses,
        $selectedEngines,
        $selectedPlatforms,
        $selectedLanguages,
        $selectedTags,
        int $totalGames,
        array $filterOptions,
        $games
    ): array {
        $titleParts = [];
        $descriptionParts = [];

        // Add search query if present
        if (! empty($search)) {
            $titleParts[] = "Search: {$search}";
            $descriptionParts[] = "Search results for '{$search}'";
        }

        // Add platform filters
        if ($selectedPlatforms) {
            $platforms = is_array($selectedPlatforms) ? $selectedPlatforms : explode(',', $selectedPlatforms);
            $platformLabels = array_map(function ($p) use ($filterOptions) {
                return $filterOptions['platforms'][$p] ?? ucfirst($p);
            }, $platforms);
            if (count($platformLabels) > 0) {
                $titleParts[] = implode(', ', $platformLabels);
                $descriptionParts[] = 'Available on ' . implode(', ', $platformLabels);
            }
        }

        // Add language filters
        if ($selectedLanguages) {
            $languages = is_array($selectedLanguages) ? $selectedLanguages : explode(',', $selectedLanguages);
            $languageLabels = array_map(function ($l) use ($filterOptions) {
                return $filterOptions['languages'][$l]['ref_name'] ?? $l;
            }, $languages);
            if (count($languageLabels) > 0 && count($languageLabels) <= 3) {
                $titleParts[] = implode(', ', $languageLabels);
                $descriptionParts[] = 'In ' . implode(', ', $languageLabels);
            }
        }

        // Add status filters
        if ($selectedStatuses) {
            $statuses = is_array($selectedStatuses) ? $selectedStatuses : explode(',', $selectedStatuses);
            if (count($statuses) === 1) {
                $status = ucwords(str_replace('_', ' ', $statuses[0]));
                $titleParts[] = $status;
                $descriptionParts[] = "Status: {$status}";
            }
        }

        // Add engine filters
        if ($selectedEngines) {
            $engines = is_array($selectedEngines) ? $selectedEngines : explode(',', $selectedEngines);
            if (count($engines) === 1) {
                $titleParts[] = $engines[0];
                $descriptionParts[] = "Made with {$engines[0]}";
            }
        }

        // Add tag filters (limit to 2 for brevity)
        if ($selectedTags) {
            $tags = is_array($selectedTags) ? $selectedTags : explode(',', $selectedTags);
            $tagIds = array_slice($tags, 0, 2);
            $tagLabels = [];
            foreach ($tagIds as $tagId) {
                $tagLabel = $filterOptions['tags'][$tagId] ?? null;
                if ($tagLabel) {
                    // Remove count from tag label (e.g., "Romance (42)" -> "Romance")
                    $tagLabels[] = preg_replace('/\s*\(\d+\)$/', '', $tagLabel);
                }
            }
            if (count($tagLabels) > 0) {
                $titleParts[] = implode(', ', $tagLabels);
                $descriptionParts[] = 'Tagged: ' . implode(', ', $tagLabels);
            }
        }

        // Add NSFW/SFW filter
        $nsfw = $request->boolean('nsfw');
        $sfw = $request->boolean('sfw');
        if ($nsfw && ! $sfw) {
            $titleParts[] = 'NSFW';
            $descriptionParts[] = 'NSFW content';
        } elseif ($sfw && ! $nsfw) {
            $titleParts[] = 'SFW';
            $descriptionParts[] = 'SFW content only';
        }

        // Add paid/free filter
        $showPaid = $request->boolean('showPaid');
        $showFree = $request->boolean('showFree');
        if ($showPaid && ! $showFree) {
            $titleParts[] = 'Paid';
            $descriptionParts[] = 'Paid games';
        } elseif ($showFree && ! $showPaid) {
            $titleParts[] = 'Free';
            $descriptionParts[] = 'Free games';
        }

        // Add demo filter
        if ($request->boolean('showDemo')) {
            $titleParts[] = 'Demos';
            $descriptionParts[] = 'Games with demos available';
        }

        // Build social media title with detailed filter information
        $socialTitle = count($titleParts) > 0
            ? implode(' - ', array_slice($titleParts, 0, 3)) . ' Visual Novels'
            : 'Visual Novels';

        // Browser title is always simple and static
        $browserTitle = 'Visual Novels';

        // Build description
        $description = count($descriptionParts) > 0
            ? implode(' • ', $descriptionParts) . ' - Browse and discover visual novels on FVN.li'
            : 'Browse and discover visual novels on FVN.li';

        // Add total count to description
        if ($totalGames > 0) {
            $description .= sprintf(' - %d games found', $totalGames);
        }

        // Add first few game names to give users an idea of what to expect
        if ($games && $games->count() > 0) {
            $gameNames = collect($games->items())
                ->take(3)
                ->pluck('name')
                ->toArray();

            if (count($gameNames) > 0) {
                $description .= '. Including: ' . implode(', ', $gameNames);
                if ($totalGames > count($gameNames)) {
                    $description .= ', and more';
                }
            }
        }

        return [
            'browserTitle' => $browserTitle,
            'socialTitle' => $socialTitle,
            'description' => $description,
            'image' => asset(config('social.images.games_list', config('social.images.default'))),
            'url' => $request->url(),
        ];
    }
}
