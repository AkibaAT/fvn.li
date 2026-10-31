<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rating;
use App\Models\Tag;
use App\Models\User;
use App\Models\VnList;
use App\Services\DenKitStashPersistenceService;
use App\Services\GamePageCacheService;
use App\Services\GameSocialMetaBuilder;
use App\Services\GamesSearchResultHydrator;
use App\Services\HtmlSanitizerService;
use App\Services\RouteGraphService;
use App\Services\SimilarGamesService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GamesDisplayController extends Controller
{
    /**
     * Display a single game page
     */
    public function show(Game $game, Request $request): Response
    {
        $viewer = $request->user();

        $reviewsPage = max(1, (int) $request->query('page', 1));
        $versionsPage = max(1, (int) $request->query('versionsPage', 1));

        if ($viewer && ($viewer->is_admin || $viewer->ownsGame($game))) {
            return Inertia::render('games/show', $this->pageProps($game, $viewer, $reviewsPage, $versionsPage));
        }

        $props = GamePageCacheService::remember(
            $game,
            $reviewsPage,
            $versionsPage,
            fn (int $reviewsPage, int $versionsPage): array => json_decode(
                json_encode($this->pageProps(clone $game, null, $reviewsPage, $versionsPage), JSON_THROW_ON_ERROR),
                true,
                flags: JSON_THROW_ON_ERROR,
            ),
        );

        if ($viewer) {
            $props['game'] = [
                ...$props['game'],
                ...app(GamesSearchResultHydrator::class)->userData([$game->id], $viewer->id)[$game->id],
            ];
            $props['userReview'] = $this->viewerReview($game, $viewer);
        }

        return Inertia::render('games/show', $props);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function viewerReview(Game $game, User $viewer): ?array
    {
        $review = $game->ratings()->where('user_id', $viewer->id)->first();

        if (! $review) {
            return null;
        }

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'review' => app(HtmlSanitizerService::class)->sanitizeFvnReview($review->review),
            'has_spoilers' => $review->has_spoilers,
            'published_at' => $review->published_at?->toISOString(),
            'updated_at' => $review->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(Game $game, ?User $viewer, int $reviewsPage, int $versionsPage): array
    {
        if ($game->is_visible) {
            $game->load([
                'tags' => Tag::constrainToPopular(),
                'gameJams',
            ]);
        } else {
            $game->forceFill(Game::CONTENT_ATTRIBUTES)
                ->setRelation('tags', $game->tags()->getRelated()->newCollection())
                ->setRelation('gameJams', $game->gameJams()->getRelated()->newCollection());
        }

        $game->append(['tags_list', 'effective_description']);
        $game->makeHidden(['full_description', 'screenshots', 'uploads', 'error']);

        $reviews = $game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->with(['rater.user:id,name,avatar', 'user:id,name,avatar'])
            ->orderBy('published_at', 'desc')
            ->paginate(5, ['*'], 'page', $reviewsPage);

        $sanitizer = app(HtmlSanitizerService::class);
        $previousCounts = Rating::previousRatingCountsForGame($game->id, $reviews->getCollection()->pluck('rater_id'));
        $reviews->getCollection()->transform(function ($rating) use ($sanitizer, $previousCounts) {
            $rating->review = $rating->source_platform === 'fvn_li'
                ? $sanitizer->sanitizeFvnReview($rating->review)
                : $sanitizer->sanitizeReview($rating->review);
            $rating->previous_ratings_count = $rating->rater_id !== null ? (int) ($previousCounts[$rating->rater_id] ?? 0) : 0;
            $rating->assignAuthorUser();

            return $rating;
        });

        $availableRatings = $game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->distinct()
            ->pluck('rating')
            ->sort()
            ->values()
            ->toArray();

        $gameVersions = $game->gameVersions()
            ->with([
                'supportedLanguages.language',
                'languageStats.language',
            ])
            ->when(! $game->is_visible, fn ($query) => $query->whereRaw('false'))
            ->orderBy('published_at', 'desc')
            ->paginate(5, ['*'], 'versionsPage', $versionsPage);

        $latestVersion = $gameVersions->getCollection()->firstWhere('is_latest', true);
        if ($latestVersion || ! $game->is_visible) {
            $game->setRelation('latestVersion', $latestVersion);
        } else {
            $game->load([
                'latestVersion.supportedLanguages.language',
                'latestVersion.languageStats.language',
            ]);
            $latestVersion = $game->latestVersion;
        }

        $englishStats = null;
        $primaryStats = null;

        if ($game->latestVersion) {
            $englishLanguageStats = $game->latestVersion->languageStats
                ->where('iso_code', 'eng')
                ->first();

            if ($englishLanguageStats && $englishLanguageStats->language !== null) {
                $englishStats = [
                    'words' => (int) $englishLanguageStats->words,
                    'language' => [
                        'id' => $englishLanguageStats->language->id,
                        'iso_code' => $englishLanguageStats->language->id,
                        'ref_name' => $englishLanguageStats->language->ref_name,
                        'flag_code' => $englishLanguageStats->language->flag_code,
                    ],
                ];
            }

            $sourceLanguageId = $game->source_language_id ?? 'eng';
            if ($sourceLanguageId !== 'eng') {
                $primaryLanguageStats = $game->latestVersion->languageStats
                    ->where('iso_code', $sourceLanguageId)
                    ->first();

                if ($primaryLanguageStats && $primaryLanguageStats->language !== null) {
                    $primaryStats = [
                        'words' => (int) $primaryLanguageStats->words,
                        'language' => [
                            'id' => $primaryLanguageStats->language->id,
                            'iso_code' => $primaryLanguageStats->language->id,
                            'ref_name' => $primaryLanguageStats->language->ref_name,
                            'flag_code' => $primaryLanguageStats->language->flag_code,
                        ],
                    ];
                }
            } else {
                $primaryStats = $englishStats;
            }
        }

        $userReview = $viewer ? $this->viewerReview($game, $viewer) : null;

        if ($viewer) {
            app(GamesSearchResultHydrator::class)->attachUserData(collect([$game]), $viewer->id);
        }

        $metaTags = app(GameSocialMetaBuilder::class)->build($game, $reviews, $englishStats);

        // Character counts are fetched together to avoid per-version queries.
        $versionCharacterCounts = [];

        $versionIds = [];
        if ($latestVersion) {
            $versionIds[] = $latestVersion->id;
        }
        foreach ($gameVersions as $version) {
            if (! $latestVersion || $version->id !== $latestVersion->id) {
                $versionIds[] = $version->id;
            }
        }

        // Count distinct display names (not character_ids) to match modal grouping
        if (! empty($versionIds)) {
            $characterCounts = DB::table('version_character_stats')
                ->join('characters', 'characters.id', '=', 'version_character_stats.character_id')
                ->whereIn('version_character_stats.game_version_id', $versionIds)
                ->where('characters.game_id', $game->id)
                ->where('characters.character_id', '!=', 'narrator')
                ->where('characters.character_id', '!=', 'menu_choice')
                ->where('characters.character_id', '!=', 'alt')
                ->when($game->source_language_id, function ($query) use ($game) {
                    $query->where('version_character_stats.iso_code', $game->source_language_id);
                })
                ->select('version_character_stats.game_version_id')
                ->selectRaw(
                    "COUNT(DISTINCT COALESCE(characters.display_name_corrections->>'eng', characters.display_names->>'eng', characters.character_id)) as count"
                )
                ->groupBy('version_character_stats.game_version_id')
                ->get()
                ->pluck('count', 'game_version_id')
                ->toArray();

            $versionCharacterCounts = $characterCounts;
        }

        $versionHasFileStats = [];
        $versionHasDialogueLines = [];
        $versionHasRouteData = [];
        if (! empty($versionIds)) {
            $versionCapabilities = DB::table('game_versions')
                ->whereIn('id', $versionIds)
                ->select('id')
                ->selectRaw('EXISTS (SELECT 1 FROM version_file_categories WHERE version_file_categories.game_version_id = game_versions.id) as has_file_stats')
                ->selectRaw('EXISTS (SELECT 1 FROM version_dialogue_lines WHERE version_dialogue_lines.game_version_id = game_versions.id) as has_dialogue_lines')
                ->selectRaw("(game_versions.route_graph_data->>'graph_revision' = ?) as has_route_data", [(string) RouteGraphService::GRAPH_REVISION])
                ->get();

            foreach ($versionCapabilities as $version) {
                $versionHasFileStats[$version->id] = (bool) $version->has_file_stats;
                $versionHasDialogueLines[$version->id] = (bool) $version->has_dialogue_lines;
                $versionHasRouteData[$version->id] = (bool) $version->has_route_data;
            }
        }

        $isAdmin = $viewer && $viewer->is_admin;
        $isOwner = $viewer && ! $isAdmin && $viewer->ownsGame($game);
        $canEdit = $isOwner || $isAdmin;
        $versionOptimizedArchiveAvailability = [];
        if ($canEdit && $gameVersions->getCollection()->isNotEmpty()) {
            try {
                $versionOptimizedArchiveAvailability = app(DenKitStashPersistenceService::class)
                    ->persistedArchiveAvailability($game, $gameVersions->getCollection());
            } catch (Throwable $throwable) {
                Log::warning('Could not resolve optimized archive availability', [
                    'game_id' => $game->id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        $canSeeAnalytics = $isOwner || $isAdmin;
        $clickStats = null;
        $dailyStats = null;

        if ($canSeeAnalytics) {
            try {
                $since = now()->subDays(30);
                $rawClickStats = ClickStat::getGameStats($game->id, $since);
                $dailyStats = ClickStat::getDailyStats($game->id, 30);

                // Transform custom_links from object to array with link names
                $additionalLinks = $game->getAllAdditionalLinks();
                $customLinksArray = [];

                foreach ($rawClickStats['custom_links'] ?? [] as $linkId => $linkData) {
                    $linkName = 'Unknown Link';
                    foreach ($additionalLinks as $link) {
                        if (($link['id'] ?? null) === $linkId) {
                            $linkName = $link['name'] ?? 'Unknown Link';
                            break;
                        }
                    }

                    $customLinksArray[] = [
                        'link_id' => $linkId,
                        'link_name' => $linkName,
                        'total_clicks' => $linkData['total_clicks'] ?? 0,
                        'unique_clicks' => $linkData['unique_clicks'] ?? 0,
                        'last_click' => $linkData['last_click'] ?? null,
                    ];
                }

                $clickStats = [
                    'page_views_total' => $rawClickStats['page_views_total'] ?? 0,
                    'page_views_unique' => $rawClickStats['page_views_unique'] ?? 0,
                    'last_page_view' => $rawClickStats['last_page_view'] ?? null,
                    'external_project_total' => $rawClickStats['external_project_total'] ?? 0,
                    'external_project_unique' => $rawClickStats['external_project_unique'] ?? 0,
                    'last_external_project' => $rawClickStats['last_external_project'] ?? null,
                    'custom_links' => $customLinksArray,
                ];
            } catch (Exception $e) {
                report($e);

                // Analytics are optional; the page renders without them.
                $clickStats = null;
                $dailyStats = null;
            }
        }

        $recommendationCacheVersion = (int) Cache::get('games.recommendations.version', 1);
        $similarCacheKey = "game.{$game->id}.similar-cards.v{$recommendationCacheVersion}";
        $similarGames = $game->is_visible ? Cache::get($similarCacheKey) : collect();

        if ($similarGames === null) {
            try {
                $similarGames = $this->recommendationCards(app(SimilarGamesService::class)->findSimilarGames($game, 6));

                Cache::put($similarCacheKey, $similarGames, $similarGames->isEmpty() ? 60 : 3600);
            } catch (Exception $e) {
                Log::warning('Similar games lookup failed', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);

                $similarGames = collect();
            }
        }

        $developerCacheKey = "game.{$game->id}.developer-cards." . md5((string) $game->authors) . ".v{$recommendationCacheVersion}";
        $developerGames = $game->is_visible
            ? Cache::remember($developerCacheKey, 3600, fn () => $this->recommendationCards(app(SimilarGamesService::class)->findDeveloperGames($game, 12)))
            : collect();

        $estimatedReadingTime = null;
        $primaryWordCount = $primaryStats['words'] ?? null;
        if ($primaryWordCount && $primaryWordCount > 0) {
            $totalMinutes = (int) ceil($primaryWordCount / 200);
            $hours = intdiv($totalMinutes, 60);
            $minutes = $totalMinutes % 60;
            $estimatedReadingTime = [
                'hours' => $hours,
                'minutes' => $minutes,
                'total_minutes' => $totalMinutes,
                'word_count' => $primaryWordCount,
            ];
        }

        $publicListsQuery = VnList::where('is_public', true)
            ->whereHas('entries', function ($query) use ($game) {
                $query->where('game_id', $game->id);
            });

        $publicLists = (clone $publicListsQuery)
            ->select('vn_lists.*')
            ->selectRaw('COUNT(*) OVER() as total_count')
            ->withCount('entries')
            ->with(['user:id,name,avatar'])
            ->orderBy('entries_count', 'desc')
            ->limit(9)
            ->get();

        $publicListsCount = (int) ($publicLists->first()?->total_count ?? 0);

        $publicLists = $publicLists
            ->map(fn ($list) => $list->only(['id', 'user_id', 'name', 'description', 'type', 'created_at', 'entries_count', 'user']));

        $supportedLanguages = $latestVersion
            ? $this->formatSupportedLanguages($latestVersion)
            : collect();

        $gameVersions->getCollection()->transform(function ($version) {
            $version->supportedLanguages = $this->formatSupportedLanguages($version);

            // Transform languageStats to include language data
            $version->languageStats = $version->languageStats
                ->filter(fn ($ls) => $ls->language !== null
                    && ! str_starts_with($ls->iso_code, 'q'))
                ->map(fn ($ls) => [
                    'words' => $ls->words,
                    'language' => [
                        'id' => $ls->language->id,
                        'iso_code' => $ls->language->id,
                        'ref_name' => $ls->language->ref_name,
                        'flag_code' => $ls->language->flag_code,
                    ],
                ])
                ->values();

            return $version;
        });

        $sanitizer->sanitizeGameModel($game);

        $originalScreenshots = $game->getScreenshots();
        $customScreenshots = is_array($game->custom_screenshots)
            ? $game->resolveScreenshots($game->custom_screenshots)
            : null;
        $effectiveScreenshots = $game->getEffectiveScreenshots();

        $game->screenshots = $originalScreenshots;
        $game->custom_screenshots = $customScreenshots;
        $game->effective_screenshots = $effectiveScreenshots;
        $game->thumb_url = $game->optimized_thumbnail_url;

        return [
            'game' => $game,
            'reviews' => $reviews,
            'availableRatings' => $availableRatings,
            'gameVersions' => $gameVersions,
            'supportedLanguages' => $supportedLanguages,
            'englishStats' => $englishStats,
            'primaryStats' => $primaryStats,
            'versionCharacterCounts' => $versionCharacterCounts,
            'versionHasFileStats' => $versionHasFileStats,
            'versionHasDialogueLines' => $versionHasDialogueLines,
            'versionHasRouteData' => $versionHasRouteData,
            'versionOptimizedArchiveAvailability' => $versionOptimizedArchiveAvailability,
            'editPermissions' => [
                'canEdit' => $canEdit,
                'hasCustomPage' => (bool) $game->has_custom_page,
                'isOwner' => $isOwner,
                'isAdmin' => $isAdmin,
            ],
            'canSeeAnalytics' => $canSeeAnalytics,
            'clickStats' => $clickStats,
            'dailyStats' => $dailyStats,
            'userReview' => $userReview,
            'publicLists' => $publicLists,
            'publicListsCount' => $publicListsCount,
            'similarGames' => $similarGames,
            'developerGames' => $developerGames,
            'estimatedReadingTime' => $estimatedReadingTime,
            'metaTags' => $metaTags->toArray(),
        ];
    }

    /**
     * @param  Collection<int, Game>  $games
     * @return Collection<int, array<string, mixed>>
     */
    private function recommendationCards(Collection $games): Collection
    {
        $models = (new Game)->newCollection($games->all());
        app(GamesSearchResultHydrator::class)->hydrateModels($models);

        return collect($models->toArray());
    }

    private function formatSupportedLanguages(GameVersion $version)
    {
        return $version->supportedLanguages
            ->filter(fn ($sl) => $sl->is_available
                && $sl->language !== null
                && ! str_starts_with($sl->iso_code, 'q'))
            ->map(fn ($sl) => [
                'iso_code' => $sl->iso_code,
                'language' => [
                    'id' => $sl->language->id,
                    'iso_code' => $sl->language->id,
                    'ref_name' => $sl->language->ref_name,
                    'flag_code' => $sl->language->flag_code,
                ],
                'is_available' => $sl->is_available,
            ])
            ->values();
    }
}
