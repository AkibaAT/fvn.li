<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rating;
use App\Models\VnList;
use App\Services\DenKitStashPersistenceService;
use App\Services\GameSocialMetaBuilder;
use App\Services\HtmlSanitizerService;
use App\Services\RouteGraphService;
use App\Services\SimilarGamesService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
    public function show(Game $game): Response
    {
        $game->load([
            'tags',
            'gameJams',
        ]);

        $game->append(['tags_list', 'effective_description']);

        $reviews = $game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->with(['rater.user:id,name,avatar', 'user:id,name,avatar'])
            ->orderBy('published_at', 'desc')
            ->paginate(5);

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
            ->orderBy('published_at', 'desc')
            ->paginate(5, ['*'], 'versionsPage');

        $latestVersion = $gameVersions->getCollection()->firstWhere('is_latest', true);
        if ($latestVersion) {
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

        // Compute primary language label from the language stats (already loaded)
        // to avoid loading sourceLanguage relation onto the game (it would get serialized)
        $sourceLanguageId = $game->source_language_id ?? 'eng';
        $primaryLanguageLabel = 'EN';
        if ($sourceLanguageId !== 'eng' && $game->latestVersion) {
            $primaryLangStats = $game->latestVersion->languageStats
                ->where('iso_code', $sourceLanguageId)
                ->first();
            if ($primaryLangStats?->language) {
                $primaryLanguageLabel = strtoupper($primaryLangStats->language->part1 ?? substr($sourceLanguageId, 0, 2));
            } else {
                $primaryLanguageLabel = strtoupper(substr($sourceLanguageId, 0, 2));
            }
        }
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

        $userReview = null;
        if (Auth::check()) {
            $existingReview = $game->ratings()
                ->where('user_id', Auth::id())
                ->first();

            if ($existingReview) {
                $userReview = [
                    'id' => $existingReview->id,
                    'rating' => $existingReview->rating,
                    'review' => $sanitizer->sanitizeFvnReview($existingReview->review),
                    'has_spoilers' => $existingReview->has_spoilers,
                    'published_at' => $existingReview->published_at?->toISOString(),
                    'updated_at' => $existingReview->updated_at?->toISOString(),
                ];
            }
        }

        $userProgress = null;
        if (Auth::check()) {
            $userProgress = DB::table('user_game_progress')
                ->where('user_id', Auth::id())
                ->where('game_id', $game->id)
                ->select('game_id', 'receive_updates')
                ->first();

            // Attach user data to game object (wrap in array to match Eloquent relationship format)
            $game->user_progress = $userProgress ? [$userProgress] : [];
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

        $user = Auth::user();
        $isAdmin = $user && $user->is_admin;
        $isOwner = $user && ! $isAdmin && $user->ownsGame($game);
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
        $similarCacheKey = "game.{$game->id}.similar.v{$recommendationCacheVersion}";
        $similarGames = Cache::get($similarCacheKey);

        if ($similarGames === null) {
            try {
                $similarGames = app(SimilarGamesService::class)->findSimilarGames($game, 6)
                    ->map(fn (Game $g) => [
                        'id' => $g->id,
                        'name' => $g->effective_name,
                        'slug' => $g->slug,
                        'thumb_url' => $g->optimized_thumbnail_url,
                        'authors' => $g->authors ? strip_tags($g->authors) : null,
                        'rating_score' => $g->rating_score,
                        'rating_count' => $g->rating_count,
                        'status' => $g->status,
                        'platform' => $g->platform,
                    ]);

                Cache::put($similarCacheKey, $similarGames, $similarGames->isEmpty() ? 60 : 3600);
            } catch (Exception $e) {
                Log::warning('Similar games lookup failed', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);

                $similarGames = collect();
            }
        }

        $developerCacheKey = "game.{$game->id}.developer." . md5((string) $game->authors) . ".v{$recommendationCacheVersion}";
        $developerGames = Cache::remember($developerCacheKey, 3600, fn () => app(SimilarGamesService::class)->findDeveloperGames($game, 12)
            ->map(fn (Game $g) => [
                'id' => $g->id,
                'name' => $g->effective_name,
                'slug' => $g->slug,
                'thumb_url' => $g->optimized_thumbnail_url,
                'rating_score' => $g->rating_score,
                'rating_count' => $g->rating_count,
                'status' => $g->status,
                'platform' => $g->platform,
            ]));

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

        return Inertia::render('games/show', [
            'game' => $game,
            'reviews' => $reviews,
            'availableRatings' => $availableRatings,
            'gameVersions' => $gameVersions,
            'supportedLanguages' => $supportedLanguages,
            'englishStats' => $englishStats,
            'primaryStats' => $primaryStats,
            'primaryLanguageLabel' => $primaryLanguageLabel,
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
        ]);
    }

    public function details(Game $game): JsonResponse
    {
        $game->load([
            'latestVersion.supportedLanguages.language',
            'tags',
            'gameJams',
        ]);

        $sanitizer = app(HtmlSanitizerService::class);

        return response()->json([
            'id' => $game->id,
            'name' => $game->name,
            'slug' => $game->slug,
            'description' => $sanitizer->sanitizeDescription($game->description),
            'full_description' => $sanitizer->sanitizeDescription($game->full_description),
            'authors' => $sanitizer->sanitizeAuthors($game->authors),
            'status' => $game->status,
            'game_engine' => $game->game_engine,
            'is_nsfw' => $game->is_nsfw,
            'is_paid' => $game->is_paid,
            'has_demo' => $game->has_demo,
            'min_price' => $game->min_price,
            'current_price' => $game->current_price,
            'url' => $game->url,
            'thumb_url' => $game->optimized_thumbnail_url,
            'screenshots' => $game->getScreenshots(),
            'additional_links' => $game->additional_links,
            'platforms' => $game->platforms,
            'supported_languages' => $game->getSupportedLanguages(),
            'tags' => $game->tags->pluck('name'),
            'game_jams' => $game->gameJams->map(fn ($jam) => [
                'id' => $jam->id,
                'name' => $jam->name,
                'slug' => $jam->slug,
            ]),
            'rating' => [
                'score' => $game->rating_score,
                'count' => $game->rating_count,
            ],
            'created_at' => $game->created_at,
            'initially_published_at' => $game->initially_published_at,
            'first_visible_at' => $game->first_visible_at,
        ]);
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
