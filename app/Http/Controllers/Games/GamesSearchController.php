<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameJam;
use App\Models\Tag;
use App\Services\GameFilterService;
use App\Services\GamesSearchMetaBuilder;
use App\Services\GamesSearchResultHydrator;
use App\Services\MeilisearchService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $excludedTags = $request->get('excludedTags');
        $readingTime = $request->get('readingTime');

        // Smart default sorting: relevance for search, date for browsing
        $isSearching = ! empty(trim($search ?? ''));
        $defaultSort = $isSearching ? 'relevance' : 'first_visible_at';
        $sortField = $request->get('sort', $defaultSort);
        $sortDirection = $request->get('direction', 'desc');
        $perPage = min(32, max(8, (int) $request->get('perPage', 8)));

        // Apply default language preferences if no explicit language filter is set
        $usingDefaultLanguages = false;
        if (! $request->has('selectedLanguages') && ! $request->has('noDefaults') && Auth::check()) {
            $userPreferences = Auth::user()->preferences;
            if ($userPreferences && ! empty($userPreferences->preferred_languages)) {
                $selectedLanguages = $userPreferences->preferred_languages;
                $usingDefaultLanguages = true;
            }
        }

        // Apply default excluded tags if no explicit tag exclusion is set
        $usingDefaultExcludedTags = false;
        if (! $request->has('excludedTags') && ! $request->has('noDefaults') && Auth::check()) {
            $userPreferences = $userPreferences ?? Auth::user()->preferences;
            if ($userPreferences && ! empty($userPreferences->excluded_tags)) {
                $excludedTags = array_map('strval', $userPreferences->excluded_tags);
                $usingDefaultExcludedTags = true;
            }
        }

        // Build filters
        $filters = $this->buildFilters($request, $selectedStatuses, $selectedEngines, $selectedPlatforms, $selectedStorePlatforms, $selectedLanguages, $selectedGameJams, $selectedTags, $excludedTags, $readingTime);

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
        $requestedPage = (int) $request->get('page', 1);

        try {
            $searchQuery = trim($search ?? '') ?: '*';
            $games = $service->searchGames($searchQuery, $filters, $perPage, $requestedPage, $sortField, $sortDirection, $ignoredGameIds);

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
            $games = $this->fallbackSearch($search, $perPage, $requestedPage, $ignoredGameIds, $request->boolean('delisted'));
        }

        // If requested page exceeds available pages, re-query for page 1
        if ($requestedPage > 1 && $games->lastPage() > 0 && $requestedPage > $games->lastPage()) {
            try {
                $searchQuery = trim($search ?? '') ?: '*';
                $games = $service->searchGames($searchQuery, $filters, $perPage, 1, $sortField, $sortDirection, $ignoredGameIds);
            } catch (Exception $e) {
                $games = $this->fallbackSearch($search, $perPage, 1, $ignoredGameIds, $request->boolean('delisted'));
            }
        }

        $hydrator = app(GamesSearchResultHydrator::class);
        $hydrator->hydrate($games);

        if (Auth::check()) {
            $hydrator->attachUserData($games, Auth::id());
        }

        // Build meta tags with filter information
        $filterOptions = GameFilterService::getOptions();
        $metaTags = app(GamesSearchMetaBuilder::class)->build(
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
                'excludedTags' => is_array($excludedTags) ? $excludedTags : [],
                'readingTime' => $readingTime ?: '',
                'nsfw' => $request->boolean('nsfw'),
                'sfw' => $request->boolean('sfw'),
                'showPaid' => $request->boolean('showPaid'),
                'showFree' => $request->boolean('showFree'),
                'showDemo' => $request->boolean('showDemo'),
                'showSale' => $request->boolean('showSale'),
                'showIgnored' => $request->boolean('showIgnored'),
                'delisted' => $request->boolean('delisted'),
                'sort' => $sortField,
                'direction' => $sortDirection,
                'perPage' => $perPage,
                'page' => $games->currentPage(),
                'noDefaults' => $request->boolean('noDefaults'),
                'usingDefaultLanguages' => $usingDefaultLanguages,
                'usingDefaultExcludedTags' => $usingDefaultExcludedTags,
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

        $games = $query->limit(10)->get(['id', 'name', 'slug', 'thumb_url']);

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
            $tagIds = array_filter(array_map('intval', is_array($tags) ? $tags : explode(',', $tags)));
            $tagNames = ! empty($tagIds) ? Tag::whereIn('id', $tagIds)
                ->pluck('name')
                ->toArray() : [];
            $tags = ! empty($tagNames) ? $tagNames : null;
        }

        // Convert game jam IDs to game jam names if game jams are provided
        $gameJams = $request->input('game_jams');
        if ($gameJams) {
            $gameJamIds = array_filter(array_map('intval', is_array($gameJams) ? $gameJams : explode(',', $gameJams)));
            $gameJamNames = ! empty($gameJamIds) ? GameJam::whereIn('id', $gameJamIds)
                ->pluck('name')
                ->toArray() : [];
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
     * Return a random visible game slug for the "I'm Feeling Lucky" feature
     */
    public function randomGame(): JsonResponse
    {
        $game = Game::query()
            ->where('is_visible', true)
            ->where('is_delisted', false)
            ->whereNotNull('slug')
            ->inRandomOrder()
            ->first(['slug']);

        if (! $game) {
            return response()->json(['error' => 'No games found'], 404);
        }

        return response()->json(['slug' => $game->slug]);
    }

    /**
     * Build search filters from request parameters
     */
    private function buildFilters(Request $request, $selectedStatuses, $selectedEngines, $selectedPlatforms, $selectedStorePlatforms, $selectedLanguages, $selectedGameJams, $selectedTags, $excludedTags = null, $readingTime = null): array
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
            $tagIds = array_map('intval', is_array($selectedTags) ? $selectedTags : explode(',', $selectedTags));
            $tagIds = array_filter($tagIds);
            if (! empty($tagIds)) {
                // Convert tag IDs to tag names since search index stores names, not IDs
                $tagNames = Tag::whereIn('id', $tagIds)
                    ->pluck('name')
                    ->toArray();
                if (! empty($tagNames)) {
                    $filters['tags'] = $tagNames;
                }
            }
        }

        // Excluded tags filter - convert tag IDs to tag names for search
        if ($excludedTags) {
            $excludedTagIds = array_map('intval', is_array($excludedTags) ? $excludedTags : explode(',', $excludedTags));
            $excludedTagIds = array_filter($excludedTagIds);
            if (! empty($excludedTagIds)) {
                $excludedTagNames = Tag::whereIn('id', $excludedTagIds)
                    ->pluck('name')
                    ->toArray();
                if (! empty($excludedTagNames)) {
                    $filters['excluded_tags'] = $excludedTagNames;
                }
            }
        }

        // Reading time filter based on english_word_count
        if ($readingTime) {
            match ($readingTime) {
                'short' => $filters['reading_time'] = 'short',     // < 10,000 words
                'medium' => $filters['reading_time'] = 'medium',   // 10,000 - 50,000 words
                'long' => $filters['reading_time'] = 'long',       // > 50,000 words
                default => null,
            };
        }

        // Game jams filter - convert game jam IDs to game jam names for search
        if ($selectedGameJams) {
            $gameJamIds = array_map('intval', is_array($selectedGameJams) ? $selectedGameJams : explode(',', $selectedGameJams));
            $gameJamIds = array_filter($gameJamIds);
            if (! empty($gameJamIds)) {
                // Convert game jam IDs to game jam names since search index stores names, not IDs
                $gameJamNames = GameJam::whereIn('id', $gameJamIds)
                    ->pluck('name')
                    ->toArray();
                if (! empty($gameJamNames)) {
                    $filters['game_jams'] = $gameJamNames;
                }
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

        // Delisted filter - when checked, show only delisted games
        if ($request->boolean('delisted')) {
            $filters['is_delisted'] = true;
        }

        return $filters;
    }

    /**
     * Fallback database search when Meilisearch fails
     */
    private function fallbackSearch(?string $search, int $perPage, int $page, array $ignoredGameIds = [], bool $delistedOnly = false)
    {
        $query = Game::query()
            ->fromItchio()
            ->where('is_visible', true);

        // Filter to only delisted games if requested
        if ($delistedOnly) {
            $query->where('is_delisted', true);
        }

        $query->with([
            'tags',
            'sourceLanguage',
            'latestVersion.supportedLanguages.language',
            'latestVersion.languageStats',
        ])
            ->withCount('ratings');

        // Exclude ignored games
        if (! empty($ignoredGameIds)) {
            $query->whereNotIn('games.id', $ignoredGameIds);
        }

        if (! empty(trim((string) $search))) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', $searchTerm)
                    ->orWhere('authors', 'ilike', $searchTerm)
                    ->orWhere('custom_tags', 'ilike', $searchTerm);
            });
        }

        $games = $query->orderBy('first_visible_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        app(GamesSearchResultHydrator::class)->hydrate($games);

        return $games;
    }
}
