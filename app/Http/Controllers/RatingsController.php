<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\User;
use App\Services\RatingAnalyticsService;
use App\Services\RatingPresenter;
use App\Services\RatingStatsCacheService;
use App\Support\Seo\MetaTags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RatingsController extends Controller
{
    private const array ALLOWED_RATING_PLATFORMS = [
        'itch_io',
        'steam',
    ];

    private const string ALL_RATING_PLATFORMS_CACHE_VALUE = '__all_platforms__';

    public function ratingsIndex(Request $request): Response
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('perPage', 10)));

        // Filters and sorting (similar to rater page)
        $showOnlyReviews = filter_var($request->input('showOnlyReviews', true), FILTER_VALIDATE_BOOLEAN);
        $showOnlyVisibleGames = filter_var($request->input('showOnlyVisibleGames', true), FILTER_VALIDATE_BOOLEAN);
        $platform = $this->normalizeRatingPlatform($request->input('platform'));
        $stars = $request->has('stars') ? (int) $request->input('stars') : null;
        if ($stars !== null && ($stars < 1 || $stars > 5)) {
            $stars = null;
        }
        $sortField = $request->input('sortField', 'published_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['published_at', 'rating'];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'published_at';
        }

        // Detect Inertia partial reloads; keep stats closure-backed during paging/sorting-only reloads
        $isInertia = (bool) $request->headers->get('X-Inertia');
        $partialDataHeader = $request->headers->get('X-Inertia-Partial-Data');
        $partialComponent = $request->headers->get('X-Inertia-Partial-Component');
        $requestedProps = $partialDataHeader ? array_filter(array_map('trim', explode(',', $partialDataHeader))) : [];
        $isPartialForThisPage = $isInertia && $partialComponent === 'ratings/index' && ! empty($requestedProps);

        // Base filters for ratings
        $ratingsFilter = DB::table('ratings')
            ->where('ratings.is_visible', true)
            ->when($showOnlyReviews, fn ($query) => $query->where('ratings.is_reviewed', true))
            ->when($stars !== null, fn ($query) => $query->where('ratings.rating', $stars));

        // Count query: avoid unnecessary joins; only join games if we need to filter by game visibility
        $countQuery = (clone $ratingsFilter);
        if ($showOnlyVisibleGames) {
            $countQuery->join('games', 'games.id', '=', 'ratings.game_id')
                ->where('games.is_visible', true);
        }

        // Join raters if we need to filter by platform
        if ($platform) {
            $countQuery->join('raters', 'raters.id', '=', 'ratings.rater_id')
                ->where('raters.external_platform', $platform);
        }

        $countCacheKey = RatingStatsCacheService::key(sprintf(
            'ratings.count:rev:%d:star:%s:listed:%d:platform:%s',
            (int) $showOnlyReviews,
            $stars ?? 'all',
            (int) $showOnlyVisibleGames,
            $platform ?? self::ALL_RATING_PLATFORMS_CACHE_VALUE
        ));
        $total = cache()->remember($countCacheKey, now()->addHour(), function () use ($countQuery) {
            return (int) ($countQuery->selectRaw('COUNT(*) as aggregate')->value('aggregate') ?? 0);
        });

        // Data query: includes joins needed for rendering
        $rows = (clone $ratingsFilter)
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->join('raters', 'raters.id', '=', 'ratings.rater_id')
            ->when($showOnlyVisibleGames, function ($query) {
                $query->where('games.is_visible', true);
            })
            ->when($platform, function ($query, $platform) {
                $query->where('raters.external_platform', $platform);
            })
            ->select([
                'ratings.id',
                'ratings.rating',
                'ratings.published_at',
                'ratings.is_reviewed',
                'ratings.review',
                'ratings.event_id',
                'games.id as game_id',
                'games.name as game_name',
                'games.slug as game_slug',
                'games.url as game_url',
                'games.platform as game_platform',
                'games.is_visible as game_is_visible',
                'raters.id as rater_id',
                'raters.name as rater_name',
                'raters.external_platform as rater_platform',
            ])
            ->orderBy($sortField === 'rating' ? 'ratings.rating' : 'ratings.published_at', $sortDirection)
            ->forPage($page, $perPage)
            ->get();

        $presenter = app(RatingPresenter::class);
        $ratings = [
            'data' => $rows->map(fn ($row) => $presenter->indexRatingRow($row))->toArray(),
            'current_page' => $page,
            'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'per_page' => $perPage,
            'total' => $total,
        ];

        $statsProp = $isPartialForThisPage
            ? fn () => cache()->rememberForever(RatingStatsCacheService::GLOBAL_STATS_KEY, fn () => $this->getGlobalRatingStats())
            : cache()->rememberForever(RatingStatsCacheService::GLOBAL_STATS_KEY, fn () => $this->getGlobalRatingStats());

        return Inertia::render('ratings/index', [
            'pageTitle' => 'Ratings',
            'stats' => $statsProp,
            'ratings' => $ratings,
            'filters' => [
                'showOnlyReviews' => $showOnlyReviews,
                'showOnlyVisibleGames' => $showOnlyVisibleGames,
                'platform' => $platform,
                'stars' => $stars,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'page' => $page,
                'perPage' => $perPage,
            ],
            'metaTags' => new MetaTags(
                title: 'Game Ratings & Reviews',
                description: 'Browse community ratings and reviews for furry visual novels. ' .
                    "Currently featuring {$total} ratings" .
                    ($total > 0 ? ' with an average rating of ' . round(collect($ratings['data'])->avg('score') ?: 0, 1) . ' stars' : '') .
                    '. Filter by star rating, review status, and sort by date or rating.',
                structuredData: [
                    '@type' => 'CollectionPage',
                    'name' => 'Game Ratings & Reviews',
                    'description' => 'Browse community ratings and reviews for furry visual novels',
                    'url' => route('ratings.index'),
                    'numberOfItems' => $total,
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'name' => 'Game Ratings',
                        'numberOfItems' => $total,
                        'itemListElement' => collect($ratings['data'])->take(10)->map(function ($rating, $index) {
                            return [
                                '@type' => 'Review',
                                'position' => $index + 1,
                                'itemReviewed' => [
                                    '@type' => 'SoftwareApplication',
                                    'name' => $rating['game']['name'],
                                    'url' => route('games.show', $rating['game']['slug']),
                                ],
                                'author' => [
                                    '@type' => 'Person',
                                    'name' => $rating['rater']['name'],
                                ],
                                'reviewRating' => [
                                    '@type' => 'Rating',
                                    'ratingValue' => $rating['score'],
                                    'bestRating' => 5,
                                    'worstRating' => 1,
                                ],
                                'datePublished' => $rating['created_at'],
                            ];
                        })->toArray(),
                    ],
                ],
            )->toArray(),
        ]);
    }

    public function raterShow(Request $request, int $rater): Response
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('perPage', 10)));

        // Filters and sorting (parity with previous Livewire component)
        $showOnlyReviews = filter_var($request->input('showOnlyReviews', true), FILTER_VALIDATE_BOOLEAN);
        $showOnlyVisibleGames = filter_var($request->input('showOnlyVisibleGames', false), FILTER_VALIDATE_BOOLEAN);
        $sortField = $request->input('sortField', 'published_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Whitelist sort fields
        $allowedSorts = ['published_at', 'rating'];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'published_at';
        }

        // Detect Inertia partial reloads; expensive props remain closure-backed below.
        $isInertia = (bool) $request->headers->get('X-Inertia');
        $partialDataHeader = $request->headers->get('X-Inertia-Partial-Data');
        $partialComponent = $request->headers->get('X-Inertia-Partial-Component');
        $requestedProps = $partialDataHeader ? array_filter(array_map('trim', explode(',', $partialDataHeader))) : [];
        $isPartialForThisPage = $isInertia && $partialComponent === 'raters/show' && ! empty($requestedProps);
        $includeRaterProp = ! $isPartialForThisPage || in_array('rater', $requestedProps, true);

        $r = DB::table('raters')
            ->where('id', $rater)
            ->select(['id', 'name', 'created_at'])
            ->first();

        if (! $r) {
            abort(404);
        }

        $raterPayload = [
            'id' => (int) $r->id,
            'name' => $r->name,
            'joined_at' => isset($r->created_at) ? (string) $r->created_at : null,
        ];
        $metaTitle = $r->name . ' - Rater';

        // Ratings list (visible ratings by default)
        $ratingsBase = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.rater_id', $rater)
            ->where('ratings.is_visible', true)
            ->when($showOnlyVisibleGames, function ($query) {
                $query->where('games.is_visible', true);
            })
            ->when($showOnlyReviews, fn ($query) => $query->where('ratings.is_reviewed', true));

        $rows = (clone $ratingsBase)
            ->select([
                'ratings.id',
                'ratings.rating',
                'ratings.published_at',
                'ratings.is_reviewed',
                'ratings.review',
                'ratings.event_id',
                'ratings.is_visible as rating_is_visible',
                'games.id as game_id',
                'games.name as game_name',
                'games.slug as game_slug',
                'games.url as game_url',
                'games.platform as game_platform',
                'games.is_visible as game_is_visible',
                DB::raw('COUNT(*) OVER() as total_count'),
            ])
            ->orderBy($sortField === 'rating' ? 'ratings.rating' : 'ratings.published_at', $sortDirection)
            ->forPage($page, $perPage)
            ->get();

        // When the requested page is beyond the end of the result set, the window-function
        // query returns no rows, so fall back to a cheap count on the filtered base query.
        $total = $rows->isNotEmpty()
            ? (int) $rows[0]->total_count
            : (int) ((clone $ratingsBase)->count('ratings.id'));

        $presenter = app(RatingPresenter::class);
        $ratings = [
            'data' => $rows->map(fn ($row) => $presenter->raterRatingRow($row))->toArray(),
            'current_page' => $page,
            'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'per_page' => $perPage,
            'total' => (int) $total,
        ];

        // Previous rating counts per game (invisible historical ratings)
        // Only compute previous rating counts for the games on the current page
        $pageGameIds = collect($rows)->pluck('game_id')->unique()->values();
        $previousRatingCounts = [];
        if ($pageGameIds->isNotEmpty()) {
            $previousRatingCounts = DB::table('ratings')
                ->where('rater_id', $rater)
                ->where('is_visible', false)
                ->where('is_moderation_hidden', false)
                ->whereIn('game_id', $pageGameIds)
                ->selectRaw('game_id, count(*) as count')
                ->groupBy('game_id')
                ->get()
                ->pluck('count', 'game_id')
                ->toArray();
        }

        // Provide stats/phrases immediately on full loads; keep them closure-backed during partial reloads
        $statsProp = $isPartialForThisPage ? fn () => $this->getRatingStats($rater) : $this->getRatingStats($rater);
        $phrasesProp = $isPartialForThisPage ? fn () => $this->getCommonPhrases($rater) : $this->getCommonPhrases($rater);

        $props = [
            'pageTitle' => 'Rater',
            'ratings' => $ratings,
            'stats' => $statsProp,
            'phrases' => $phrasesProp,
            'previousRatingCounts' => $previousRatingCounts,
            'filters' => [
                'showOnlyReviews' => $showOnlyReviews,
                'showOnlyVisibleGames' => $showOnlyVisibleGames,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'page' => $page,
                'perPage' => $perPage,
            ],
            'metaTags' => new MetaTags(
                title: $metaTitle,
                description: isset($raterPayload)
                    ? "View ratings and reviews by {$raterPayload['name']}. " .
                      "Currently showing {$total} ratings" .
                      ($total > 0 ? ' with an average rating of ' . round(collect($ratings['data'])->avg('rating') ?: 0, 1) . ' stars' : '') .
                      ' for various furry visual novels.'
                    : 'View rater profile and ratings.',
                noindex: true,
                structuredData: isset($raterPayload) ? [
                    '@type' => 'ProfilePage',
                    'name' => $metaTitle,
                    'description' => "Ratings and reviews by {$raterPayload['name']}",
                    'url' => route('raters.show', $rater),
                    'mainEntity' => [
                        '@type' => 'Person',
                        'name' => $raterPayload['name'],
                    ],
                    'mainEntityOfPage' => [
                        '@type' => 'ItemList',
                        'name' => "{$raterPayload['name']}'s Ratings",
                        'numberOfItems' => $total,
                        'itemListElement' => collect($ratings['data'])->take(10)->map(function ($rating, $index) use ($raterPayload) {
                            return [
                                '@type' => 'Review',
                                'position' => $index + 1,
                                'itemReviewed' => [
                                    '@type' => 'SoftwareApplication',
                                    'name' => $rating['game']['name'],
                                    'url' => route('games.show', $rating['game']['slug']),
                                ],
                                'author' => [
                                    '@type' => 'Person',
                                    'name' => $raterPayload['name'],
                                ],
                                'reviewRating' => [
                                    '@type' => 'Rating',
                                    'ratingValue' => $rating['rating'],
                                    'bestRating' => 5,
                                    'worstRating' => 1,
                                ],
                                'datePublished' => $rating['published_at'],
                            ];
                        })->toArray(),
                    ],
                ] : [
                    '@type' => 'WebPage',
                    'name' => $metaTitle,
                    'description' => 'View rater profile and ratings',
                    'url' => route('raters.show', $rater),
                ],
            )->toArray(),
        ];

        if ($includeRaterProp) {
            $props['rater'] = $raterPayload;
        }

        return Inertia::render('raters/show', $props);
    }

    /**
     * Show a single review detail page.
     */
    public function reviewShow(int $rating): Response
    {
        $review = Rating::with([
            'game:id,name,slug,thumb_url,optimized_thumbnails',
            'user:id,name,avatar',
            'rater:id,name,external_platform',
        ])
            ->where('is_visible', true)
            ->findOrFail($rating);

        $reviewData = app(RatingPresenter::class)->reviewDetail($review);

        $authorName = $review->user?->name ?? $review->rater?->name ?? 'Unknown';
        $gameName = $review->game?->name ?? 'Unknown';
        $excerpt = $review->review ? mb_substr(strip_tags($review->review), 0, 160) : null;

        return Inertia::render('reviews/show', [
            'review' => $reviewData,
            'metaTags' => new MetaTags(
                title: "{$authorName}'s review of {$gameName}",
                description: $excerpt ?? "{$authorName} rated {$gameName} {$review->rating}/5 stars.",
                structuredData: [
                    '@type' => 'Review',
                    'itemReviewed' => [
                        '@type' => 'SoftwareApplication',
                        'name' => $gameName,
                        'url' => $review->game ? route('games.show', $review->game->slug) : null,
                    ],
                    'author' => [
                        '@type' => 'Person',
                        'name' => $authorName,
                    ],
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $review->rating,
                        'bestRating' => 5,
                        'worstRating' => 1,
                    ],
                    'datePublished' => $review->published_at?->toISOString(),
                    'reviewBody' => $excerpt,
                ],
            )->toArray(),
        ]);
    }

    /**
     * Show all reviews by a specific user (FVN.li user reviews only).
     */
    public function userReviews(Request $request, User $user): Response
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(1, (int) $request->input('perPage', 10)));
        $sortField = $request->input('sortField', 'published_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['published_at', 'rating'];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'published_at';
        }

        $query = Rating::where('user_id', $user->id)
            ->where('is_visible', true)
            ->whereNotNull('user_id');

        $total = $query->count();

        $reviews = (clone $query)
            ->with(['game:id,name,slug,thumb_url,optimized_thumbnails'])
            ->orderBy($sortField, $sortDirection)
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Rating $review) => app(RatingPresenter::class)->userReview($review));

        // Stats
        $stats = DB::table('ratings')
            ->where('user_id', $user->id)
            ->where('is_visible', true)
            ->select([
                DB::raw('COUNT(*) as total_ratings'),
                DB::raw('SUM(CASE WHEN is_reviewed THEN 1 ELSE 0 END) as reviewed_count'),
                DB::raw('AVG(rating) as average_rating'),
                DB::raw('COUNT(DISTINCT game_id) as unique_games'),
            ])
            ->first();

        return Inertia::render('reviews/user', [
            'reviewUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
            ],
            'reviews' => [
                'data' => $reviews,
                'current_page' => $page,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'stats' => [
                'total_ratings' => (int) ($stats->total_ratings ?? 0),
                'reviewed_count' => (int) ($stats->reviewed_count ?? 0),
                'average_rating' => round((float) ($stats->average_rating ?? 0), 1),
                'unique_games' => (int) ($stats->unique_games ?? 0),
            ],
            'filters' => [
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'page' => $page,
                'perPage' => $perPage,
            ],
            'metaTags' => new MetaTags(
                title: "{$user->name}'s Reviews",
                description: "{$user->name} has reviewed {$stats->reviewed_count} visual novels with an average rating of " . round((float) ($stats->average_rating ?? 0), 1) . '/5.',
            )->toArray(),
        ]);
    }

    public function getRatingHistory(Request $request, Rater $rater, string $game)
    {
        $game = Game::query()
            ->whereKey($game)
            ->firstOrFail();

        $ratings = DB::table('ratings')
            ->where('rater_id', $rater->id)
            ->where('game_id', $game->id)
            ->where('is_moderation_hidden', false)
            ->orderBy('published_at', 'desc')
            ->select(['id', 'rating', 'published_at', 'is_visible', 'review', 'event_id'])
            ->get()
            ->map(fn ($row) => app(RatingPresenter::class)->historyRatingRow($row))
            ->toArray();

        return response()->json([
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
            ],
            'ratings' => $ratings,
        ]);
    }

    // ratingsTrends removed

    protected function getGlobalRatingStats(): array
    {
        return app(RatingAnalyticsService::class)->globalStats();
    }

    protected function getRatingStats(int $raterId): array
    {
        return app(RatingAnalyticsService::class)->raterStats($raterId);
    }

    protected function getCommonPhrases(int $raterId): array
    {
        return app(RatingAnalyticsService::class)->commonPhrases($raterId);
    }

    protected function raterPhrasesCacheKey(int $raterId): string
    {
        return app(RatingAnalyticsService::class)->phrasesCacheKey($raterId);
    }

    private function normalizeRatingPlatform(mixed $platform): ?string
    {
        if (! is_string($platform)) {
            return null;
        }

        $platform = trim($platform);

        return in_array($platform, self::ALLOWED_RATING_PLATFORMS, true) ? $platform : null;
    }
}
