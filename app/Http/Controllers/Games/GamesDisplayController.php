<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\User;
use App\Models\VnList;
use App\Services\HtmlSanitizerService;
use App\Services\SimilarGamesService;
use App\Traits\HasSocialMetaTags;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GamesDisplayController extends Controller
{
    use HasSocialMetaTags;

    /**
     * Display a single game page
     */
    public function show(Game $game): Response
    {
        $game->load([
            'latestVersion.supportedLanguages.language',
            'latestVersion.languageStats.language',
            'tags',
            'gameJams',
        ]);

        // Add detail-only attributes for frontend display.
        $game->append(['tags_list', 'effective_description']);

        $reviews = $game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->with(['rater', 'user:id,name,avatar'])
            ->orderByDesc('published_at')
            ->paginate(5);

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

        $gameVersions->getCollection()->transform(function ($version) {
            $version->supportedLanguages = $version->supportedLanguages
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

            // Transform languageStats to include language data
            // Filter out placeholder 'q' codes and null language relationships
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

        // Get English word count from latest version for game detail section
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
                    'words' => $englishLanguageStats->words,
                    'language' => [
                        'id' => $englishLanguageStats->language->id,
                        'iso_code' => $englishLanguageStats->language->id,
                        'ref_name' => $englishLanguageStats->language->ref_name,
                        'flag_code' => $englishLanguageStats->language->flag_code,
                    ],
                ];
            }

            // Get primary language stats (source language or English fallback)
            $sourceLanguageId = $game->source_language_id ?? 'eng';
            if ($sourceLanguageId !== 'eng') {
                $primaryLanguageStats = $game->latestVersion->languageStats
                    ->where('iso_code', $sourceLanguageId)
                    ->first();

                if ($primaryLanguageStats && $primaryLanguageStats->language !== null) {
                    $primaryStats = [
                        'words' => $primaryLanguageStats->words,
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

        // Get authenticated user's own review for this game
        $userReview = null;
        if (Auth::check()) {
            $existingReview = $game->ratings()
                ->where('user_id', Auth::id())
                ->first();

            if ($existingReview) {
                $userReview = [
                    'id' => $existingReview->id,
                    'rating' => $existingReview->rating,
                    'review' => $existingReview->review,
                    'has_spoilers' => $existingReview->has_spoilers,
                    'published_at' => $existingReview->published_at?->toISOString(),
                    'updated_at' => $existingReview->updated_at?->toISOString(),
                ];
            }
        }

        // Get user's current VN lists to show list membership status
        $userVnLists = [];
        $gameListMembership = [];
        $userProgress = null;
        if (Auth::check()) {
            $userVnLists = VnList::where('user_id', Auth::id())
                ->where('type', 'custom')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'is_public']);

            // Check which lists this game is already in
            $gameListMembership = DB::table('vn_list_entries')
                ->where('game_id', $game->id)
                ->whereIn('vn_list_id', $userVnLists->pluck('id'))
                ->pluck('vn_list_id')
                ->toArray();

            // Load user progress for this game
            $userProgress = DB::table('user_game_progress')
                ->where('user_id', Auth::id())
                ->where('game_id', $game->id)
                ->select('game_id', 'receive_updates')
                ->first();

            // Attach user data to game object (wrap in array to match Eloquent relationship format)
            $game->user_progress = $userProgress ? [$userProgress] : [];

            // Also load list memberships in the format expected by the frontend
            $userListMemberships = DB::table('vn_list_entries')
                ->join('vn_lists', 'vn_list_entries.vn_list_id', '=', 'vn_lists.id')
                ->where('vn_lists.user_id', Auth::id())
                ->where('vn_list_entries.game_id', $game->id)
                ->select('vn_lists.id as list_id', 'vn_lists.name', 'vn_lists.type', 'vn_lists.is_default')
                ->get()
                ->toArray();

            $game->user_list_memberships = $userListMemberships;
        }

        // Prepare social meta tags
        $this->prepareSocialMetaTags($game, $reviews, $englishStats);

        // Track page view
        ClickStat::recordClick(
            gameId: $game->id,
            type: ClickStat::TYPE_PAGE_VIEW,
            sessionId: session()->getId(),
            userId: Auth::id(),
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            referrer: request()->header('referer')
        );

        // Calculate character counts for each version (for dialogue browser links)
        // Optimized: Use batch query instead of N+1
        $latestVersion = $game->latestVersion;
        $versionCharacterCounts = [];

        // Collect all version IDs to query
        $versionIds = [];
        if ($latestVersion) {
            $versionIds[] = $latestVersion->id;
        }
        foreach ($gameVersions as $version) {
            if (! $latestVersion || $version->id !== $latestVersion->id) {
                $versionIds[] = $version->id;
            }
        }

        // Batch query for character counts for all versions at once
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
                ->selectRaw("COUNT(DISTINCT characters.display_names->>'eng') as count")
                ->groupBy('version_character_stats.game_version_id')
                ->get()
                ->pluck('count', 'game_version_id')
                ->toArray();

            $versionCharacterCounts = $characterCounts;
        }

        // Check if file stats exist for each version (to show/hide file stats button)
        // Optimized: Use batch query instead of N+1
        $versionHasFileStats = [];
        if (! empty($versionIds)) {
            $versionsWithFileStats = DB::table('version_file_categories')
                ->whereIn('game_version_id', $versionIds)
                ->distinct()
                ->pluck('game_version_id')
                ->toArray();

            foreach ($versionIds as $versionId) {
                $versionHasFileStats[$versionId] = in_array($versionId, $versionsWithFileStats);
            }
        }

        // Check if dialogue lines exist for each version (to show/hide browse dialogue button)
        // Optimized: Use batch query instead of N+1
        $versionHasDialogueLines = [];
        if (! empty($versionIds)) {
            $versionsWithDialogueLines = DB::table('version_dialogue_lines')
                ->whereIn('game_version_id', $versionIds)
                ->distinct()
                ->pluck('game_version_id')
                ->toArray();

            foreach ($versionIds as $versionId) {
                $versionHasDialogueLines[$versionId] = in_array($versionId, $versionsWithDialogueLines);
            }
        }

        // Check if route data exists for each version (to show/hide route map links)
        // A route map can be built either from cached graph data or raw route labels.
        $versionHasRouteData = [];
        if (! empty($versionIds)) {
            $versionsWithCachedRouteGraphs = DB::table('game_versions')
                ->whereIn('id', $versionIds)
                ->whereNotNull('route_graph_data')
                ->pluck('id')
                ->toArray();

            $versionsWithRouteLabels = DB::table('version_route_labels')
                ->whereIn('game_version_id', $versionIds)
                ->distinct()
                ->pluck('game_version_id')
                ->toArray();

            $routeDataVersionIds = array_flip(array_merge($versionsWithCachedRouteGraphs, $versionsWithRouteLabels));
            foreach ($versionIds as $versionId) {
                $versionHasRouteData[$versionId] = isset($routeDataVersionIds[$versionId]);
            }
        }

        // Determine edit permissions
        $user = Auth::user();
        $isOwner = $user && $user->ownsGame($game);
        $isAdmin = $user && $user->is_admin;
        $canEdit = $isOwner || $isAdmin;

        // Get analytics data if user can see it
        $canSeeAnalytics = $isOwner || $isAdmin;
        $clickStats = null;
        $dailyStats = null;

        if ($canSeeAnalytics) {
            try {
                // Get click statistics for the last 30 days
                $since = now()->subDays(30);
                $rawClickStats = ClickStat::getGameStats($game->id, $since);
                $dailyStats = ClickStat::getDailyStats($game->id, 30);

                // Transform custom_links from object to array with link names
                $additionalLinks = $game->getAllAdditionalLinks();
                $customLinksArray = [];

                foreach ($rawClickStats['custom_links'] ?? [] as $linkId => $linkData) {
                    // Find the link name from the game's additional links
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
                // If analytics fail, just don't show them
                $clickStats = null;
                $dailyStats = null;
            }
        }

        // Find similar games and developer's other games
        $recommendationCacheVersion = (int) Cache::get('games.recommendations.version', 1);
        try {
            $similarGames = Cache::remember("game.{$game->id}.similar.v{$recommendationCacheVersion}", 3600, fn () => app(SimilarGamesService::class)->findSimilarGames($game, 6)
                ->map(fn (Game $g) => [
                    'id' => $g->id,
                    'name' => $g->effective_name,
                    'slug' => $g->slug,
                    'thumb_url' => $g->optimized_thumbnail_url ?? $g->thumb_url,
                    'authors' => $g->authors ? strip_tags($g->authors) : null,
                    'rating_score' => $g->rating_score,
                    'rating_count' => $g->rating_count,
                    'status' => $g->status,
                    'platform' => $g->platform,
                ]));
        } catch (Exception $e) {
            $similarGames = collect();
        }

        $developerCacheKey = "game.{$game->id}.developer." . md5((string) $game->authors) . ".v{$recommendationCacheVersion}";
        $developerGames = Cache::remember($developerCacheKey, 3600, fn () => app(SimilarGamesService::class)->findDeveloperGames($game, 12)
            ->map(fn (Game $g) => [
                'id' => $g->id,
                'name' => $g->effective_name,
                'slug' => $g->slug,
                'thumb_url' => $g->optimized_thumbnail_url ?? $g->thumb_url,
                'rating_score' => $g->rating_score,
                'rating_count' => $g->rating_count,
                'status' => $g->status,
                'platform' => $g->platform,
            ]));

        // Calculate estimated reading time from primary word count
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

        // Fetch public lists that contain this game
        $publicListsQuery = VnList::where('is_public', true)
            ->whereHas('entries', function ($query) use ($game) {
                $query->where('game_id', $game->id);
            });

        $publicListsCount = $publicListsQuery->count();

        $publicLists = (clone $publicListsQuery)
            ->withCount('entries')
            ->with(['user:id,name,avatar'])
            ->orderByDesc('entries_count')
            ->limit(9)
            ->get()
            ->map(fn ($list) => $list->only(['id', 'user_id', 'name', 'description', 'type', 'created_at', 'entries_count', 'user']));

        $sanitizer = app(HtmlSanitizerService::class);
        $sanitizer->sanitizeGameModel($game);

        $game->screenshots = $game->getScreenshots();
        if ($game->custom_screenshots) {
            $game->custom_screenshots = $game->resolveScreenshots($game->custom_screenshots);
        }

        return Inertia::render('games/show', [
            'game' => $game,
            'reviews' => $reviews,
            'availableRatings' => $availableRatings,
            'gameVersions' => $gameVersions,
            'englishStats' => $englishStats,
            'primaryStats' => $primaryStats,
            'primaryLanguageLabel' => $primaryLanguageLabel,
            'versionCharacterCounts' => $versionCharacterCounts,
            'versionHasFileStats' => $versionHasFileStats,
            'versionHasDialogueLines' => $versionHasDialogueLines,
            'versionHasRouteData' => $versionHasRouteData,
            'userVnLists' => $userVnLists,
            'gameListMembership' => $gameListMembership,
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
            'metaTags' => $this->getMetaTags(),
        ]);
    }

    /**
     * Get game details for API consumption
     */
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
            'thumb_url' => $game->thumb_url,
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

    /**
     * Prepare social meta tags for game page
     */
    private function prepareSocialMetaTags(Game $game, $reviews, ?array $englishStats = null): void
    {
        $title = $game->effective_name;
        $description = $game->description ?: "Discover {$game->effective_name} on fvn.li - Visual Novel Database and Analytics";

        // Use optimized thumbnail for social sharing, fallback to default social image
        $image = $game->getThumbnailUrl('default') ?? asset(config('social.images.default'));

        // Add game-specific info to description
        $metaDescription = $description;
        if ($game->authors) {
            $authors = strip_tags($game->authors);
            $metaDescription .= " by {$authors}";
        }
        if ($game->status) {
            $metaDescription .= " ({$game->status})";
        }

        // Add word count information
        if ($englishStats && isset($englishStats['words']) && $englishStats['words']) {
            $wordCount = number_format($englishStats['words']);
            $metaDescription .= " - {$wordCount} words";
        }

        // Add platform information
        $platforms = [];
        if ($game->latestVersion) {
            if ($game->latestVersion->is_windows) {
                $platforms[] = 'Windows';
            }
            if ($game->latestVersion->is_mac) {
                $platforms[] = 'macOS';
            }
            if ($game->latestVersion->is_linux) {
                $platforms[] = 'Linux';
            }
            if ($game->latestVersion->is_android) {
                $platforms[] = 'Android';
            }
            if ($game->latestVersion->is_web) {
                $platforms[] = 'Web';
            }
        }
        if (! empty($platforms)) {
            $metaDescription .= ' - Available on: ' . implode(', ', $platforms);
        }

        if ($reviews->total() > 0) {
            $metaDescription .= " - {$reviews->total()} reviews";
        }

        // Prepare tags for Open Graph
        $tags = $game->tags->pluck('name')->toArray();

        $this->setMetaTags([
            'title' => $title,
            'browserTitle' => $title,
            'socialTitle' => $title,
            'description' => $metaDescription,
            'image' => $image,
            'url' => route('games.show', $game),
            'type' => 'article',
            'siteName' => 'FVN.li',
            'locale' => 'en_US',
            'twitterCard' => 'summary_large_image',
            'author' => $game->authors ? strip_tags($game->authors) : null,
            'publishedTime' => $game->initially_published_at?->toIso8601String(),
            'modifiedTime' => $game->latest_version_published_at?->toIso8601String() ?? $game->updated_at->toIso8601String(),
            'section' => 'Visual Novels',
            'tags' => $tags,
            'noindex' => ! $game->is_visible,
            'structuredData' => array_filter([
                '@type' => 'VideoGame',
                'name' => $game->name,
                'description' => $game->description,
                'image' => $image,
                'url' => route('games.show', $game),
                'author' => $game->authors ? [
                    '@type' => 'Organization',
                    'name' => strip_tags($game->authors),
                ] : null,
                'datePublished' => $game->initially_published_at?->toIso8601String(),
                'dateModified' => $game->latest_version_published_at?->toIso8601String() ?? $game->updated_at->toIso8601String(),
                'genre' => $tags,
                'gamePlatform' => $platforms,
                'offers' => $game->is_paid ? [
                    '@type' => 'Offer',
                    'price' => $game->current_price ?? $game->min_price,
                    'priceCurrency' => 'USD',
                    'availability' => 'https://schema.org/InStock',
                ] : null,
                'aggregateRating' => $game->rating_score ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => round($game->rating_score, 2),
                    'ratingCount' => $game->rating_count,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ] : null,
            ], fn ($value) => $value !== null),
        ]);
    }
}
