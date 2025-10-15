<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Rater;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RatingsController extends Controller
{
    public function ratingsIndex(Request $request): Response
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('perPage', 10)));

        // Filters and sorting (similar to rater page)
        $showOnlyReviews = filter_var($request->input('showOnlyReviews', true), FILTER_VALIDATE_BOOLEAN);
        $showOnlyVisibleGames = filter_var($request->input('showOnlyVisibleGames', true), FILTER_VALIDATE_BOOLEAN);
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

        // Detect Inertia partial reloads; make stats lazy during paging/sorting-only reloads
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

        // Count strategy: when filtering by a specific star rating, use cached COUNT with partial index support.
        // Otherwise, separate COUNT remains fine.
        if ($stars !== null) {
            $cacheKey = sprintf('ratings_count_vis:%d_rev:%d_star:%d_listed:%d', 1, (int) $showOnlyReviews,
                (int) $stars, (int) $showOnlyVisibleGames);
            $total = cache()->remember($cacheKey, now()->addMinutes(5), function () use ($countQuery) {
                return (int) ($countQuery->selectRaw('COUNT(*) as aggregate')->value('aggregate') ?? 0);
            });
        } else {
            $total = (int) ($countQuery->selectRaw('COUNT(*) as aggregate')->value('aggregate') ?? 0);
        }

        // Data query: includes joins needed for rendering
        $rows = (clone $ratingsFilter)
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->join('raters', 'raters.id', '=', 'ratings.rater_id')
            ->when($showOnlyVisibleGames, function ($query) {
                $query->where('games.is_visible', true);
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
                'games.is_visible as game_is_visible',
                'raters.id as rater_id',
                'raters.name as rater_name',
            ])
            ->orderBy($sortField === 'rating' ? 'ratings.rating' : 'ratings.published_at', $sortDirection)
            ->forPage($page, $perPage)
            ->get();

        $ratings = [
            'data' => $rows->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'score' => (int) $row->rating,
                    'created_at' => optional($row->published_at) ? (string) $row->published_at : null,
                    'is_reviewed' => (bool) $row->is_reviewed,
                    'review' => $this->sanitizeReview($row->review),
                    'game' => [
                        'id' => (int) $row->game_id,
                        'name' => $row->game_name,
                        'slug' => $row->game_slug,
                        'url' => $row->game_url,
                        'is_visible' => (bool) $row->game_is_visible,
                    ],
                    'rater' => [
                        'id' => (int) $row->rater_id,
                        'name' => $row->rater_name,
                    ],
                ];
            })->toArray(),
            'current_page' => $page,
            'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'per_page' => $perPage,
            'total' => $total,
        ];

        $statsProp = $isPartialForThisPage
            ? Inertia::lazy(fn () => cache()->remember('global_rating_stats', now()->addMinutes(5),
                fn () => $this->getGlobalRatingStats()))
            : cache()->remember('global_rating_stats', now()->addMinutes(5), fn () => $this->getGlobalRatingStats());

        return Inertia::render('ratings/index', [
            'pageTitle' => 'Ratings',
            'stats' => $statsProp,
            'ratings' => $ratings,
            'filters' => [
                'showOnlyReviews' => $showOnlyReviews,
                'showOnlyVisibleGames' => $showOnlyVisibleGames,
                'stars' => $stars,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'page' => $page,
                'perPage' => $perPage,
            ],
            'metaTags' => [
                'title' => 'Game Ratings & Reviews',
                'description' => 'Browse community ratings and reviews for furry visual novels. ' .
                    "Currently featuring {$total} ratings" .
                    ($total > 0 ? ' with an average rating of ' . round(collect($ratings['data'])->avg('score') ?: 0, 1) . ' stars' : '') .
                    '. Filter by star rating, review status, and sort by date or rating.',
                'structuredData' => [
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
            ],
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

        // Detect Inertia partial reloads; only fetch the rater on full loads or when explicitly requested
        $isInertia = (bool) $request->headers->get('X-Inertia');
        $partialDataHeader = $request->headers->get('X-Inertia-Partial-Data');
        $partialComponent = $request->headers->get('X-Inertia-Partial-Component');
        $requestedProps = $partialDataHeader ? array_filter(array_map('trim', explode(',', $partialDataHeader))) : [];
        $isPartialForThisPage = $isInertia && $partialComponent === 'raters/show' && ! empty($requestedProps);
        $needsRater = ! $isPartialForThisPage || in_array('rater', $requestedProps, true) || in_array('metaTags',
            $requestedProps, true);

        $raterPayload = null;
        $metaTitle = 'Rater';
        if ($needsRater) {
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
        }

        // Defer expensive computations; they will only run on first load or when explicitly requested via partial reload

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
                'games.is_visible as game_is_visible',
                DB::raw('COUNT(*) OVER() as total_count'),
            ])
            ->orderBy($sortField === 'rating' ? 'ratings.rating' : 'ratings.published_at', $sortDirection)
            ->forPage($page, $perPage)
            ->get();

        $total = $rows->isNotEmpty() ? (int) $rows[0]->total_count : 0;

        $ratings = [
            'data' => $rows->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'rating' => (int) $row->rating,
                    'published_at' => optional($row->published_at) ? (string) $row->published_at : null,
                    'is_reviewed' => (bool) $row->is_reviewed,
                    'review' => $this->sanitizeReview($row->review),
                    'event_id' => $row->event_id,
                    'is_visible' => (bool) $row->rating_is_visible,
                    'game' => [
                        'id' => (int) $row->game_id,
                        'name' => $row->game_name,
                        'slug' => $row->game_slug,
                        'url' => $row->game_url,
                        'is_visible' => (bool) $row->game_is_visible,
                    ],
                ];
            })->toArray(),
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
                ->whereIn('game_id', $pageGameIds)
                ->selectRaw('game_id, count(*) as count')
                ->groupBy('game_id')
                ->get()
                ->pluck('count', 'game_id')
                ->toArray();
        }

        // Provide stats/phrases immediately on full loads; keep them lazy during partial reloads
        $statsProp = $isPartialForThisPage ? Inertia::lazy(fn (
        ) => $this->getRatingStats($rater)) : $this->getRatingStats($rater);
        $phrasesProp = $isPartialForThisPage ? Inertia::lazy(fn (
        ) => $this->getCommonPhrases($rater)) : $this->getCommonPhrases($rater);

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
            'metaTags' => [
                'title' => $metaTitle,
                'description' => isset($raterPayload)
                    ? "View ratings and reviews by {$raterPayload['name']}. " .
                      "Currently showing {$total} ratings" .
                      ($total > 0 ? ' with an average rating of ' . round(collect($ratings['data'])->avg('rating') ?: 0, 1) . ' stars' : '') .
                      ' for various furry visual novels.'
                    : 'View rater profile and ratings.',
                'noindex' => true, // Set noindex for all rater pages
                'structuredData' => isset($raterPayload) ? [
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
            ],
        ];

        if ($needsRater && $raterPayload !== null) {
            $props['rater'] = $raterPayload;
        }

        return Inertia::render('raters/show', $props);
    }

    public function getRatingHistory(Request $request, Rater $rater, Game $game)
    {
        $ratings = DB::table('ratings')
            ->where('rater_id', $rater->id)
            ->where('game_id', $game->id)
            ->orderByDesc('published_at')
            ->select(['id', 'rating', 'published_at', 'is_visible', 'review', 'event_id'])
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'rating' => (int) $row->rating,
                    'published_at' => optional($row->published_at) ? (string) $row->published_at : null,
                    'is_visible' => (bool) $row->is_visible,
                    'review' => $this->sanitizeReview($row->review),
                    'event_id' => $row->event_id,
                ];
            })
            ->toArray();

        return response()->json([
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
            ],
            'ratings' => $ratings,
        ]);
    }

    /**
     * Sanitize review content by replacing non-breaking spaces with regular spaces
     */
    private function sanitizeReview(?string $review): ?string
    {
        if (!$review) return $review;

        // Replace all variants of non-breaking spaces with regular spaces
        return preg_replace([
            '/&nbsp;/',
            '/\s+/'  // Replace multiple spaces with single space
        ], [
            ' ',
            ' '
        ], str_replace("\u{00A0}", ' ', trim($review)));
    }

    // ratingsTrends removed

    protected function getGlobalRatingStats(): array
    {
        // Compute only for listed games to reduce cost
        $agg = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.is_visible', true)
            ->where('games.is_visible', true)
            ->select([
                DB::raw('MIN(ratings.published_at) as first_rating'),
                DB::raw('MAX(ratings.published_at) as latest_rating'),
                DB::raw('COUNT(*) as total_ratings'),
                DB::raw('SUM(CASE WHEN ratings.is_reviewed THEN 1 ELSE 0 END) as reviewed_count'),
                DB::raw('AVG(ratings.rating) as average_rating'),
                DB::raw('COUNT(DISTINCT ratings.game_id) as unique_games'),
            ])
            ->first();

        // Per-rating distribution for listed games only
        $distRows = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.is_visible', true)
            ->where('games.is_visible', true)
            ->groupBy('ratings.rating')
            ->select([
                'ratings.rating as rating_value',
                DB::raw('COUNT(*) as count_for_rating'),
            ])
            ->get();

        if (! $agg) {
            return [
                'first_rating' => null,
                'latest_rating' => null,
                'all_games' => [
                    'total_ratings' => 0,
                    'reviewed_count' => 0,
                    'review_percentage' => 0,
                    'average_rating' => 0,
                    'unique_games' => 0,
                    'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                ],
                'visible_games' => [
                    'total_ratings' => 0,
                    'reviewed_count' => 0,
                    'review_percentage' => 0,
                    'average_rating' => 0,
                    'unique_games' => 0,
                    'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                ],
            ];
        }

        $distribution = [];
        foreach ($distRows as $row) {
            $r = (int) $row->rating_value;
            $distribution[$r] = (int) ($distribution[$r] ?? 0) + (int) $row->count_for_rating;
        }
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = $distribution[$i] ?? 0;
        }
        ksort($distribution);

        $visibleBlock = [
            'total_ratings' => (int) ($agg->total_ratings ?? 0),
            'reviewed_count' => (int) ($agg->reviewed_count ?? 0),
            'review_percentage' => ((int) ($agg->total_ratings ?? 0)) > 0
                ? (((int) ($agg->reviewed_count ?? 0)) / ((int) ($agg->total_ratings ?? 0)) * 100)
                : 0,
            'average_rating' => (float) ($agg->average_rating ?? 0),
            'unique_games' => (int) ($agg->unique_games ?? 0),
            'rating_distribution' => $distribution,
        ];

        // To keep response shape stable, mirror visible stats into all_games
        return [
            'first_rating' => $agg->first_rating ?? null,
            'latest_rating' => $agg->latest_rating ?? null,
            'all_games' => $visibleBlock,
            'visible_games' => $visibleBlock,
        ];
    }

    protected function getRatingStats(int $raterId): array
    {
        // 1) Overall aggregates (all visible ratings vs listed games)
        $agg = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.rater_id', $raterId)
            ->where('ratings.is_visible', true)
            ->select([
                DB::raw('MIN(ratings.published_at) as first_rating'),
                DB::raw('MAX(ratings.published_at) as latest_rating'),
                DB::raw('COUNT(*) as all_total_ratings'),
                DB::raw('SUM(CASE WHEN ratings.is_reviewed THEN 1 ELSE 0 END) as all_reviewed_count'),
                DB::raw('AVG(ratings.rating) as all_average_rating'),
                DB::raw('COUNT(DISTINCT ratings.game_id) as all_unique_games'),
                DB::raw('SUM(CASE WHEN games.is_visible THEN 1 ELSE 0 END) as vis_total_ratings'),
                DB::raw('SUM(CASE WHEN games.is_visible AND ratings.is_reviewed THEN 1 ELSE 0 END) as vis_reviewed_count'),
                DB::raw('AVG(CASE WHEN games.is_visible THEN ratings.rating END) as vis_average_rating'),
                DB::raw('COUNT(DISTINCT CASE WHEN games.is_visible THEN ratings.game_id END) as vis_unique_games'),
            ])
            ->first();

        // 2) Per-rating distributions (counts for all visible ratings vs listed)
        $distRows = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.rater_id', $raterId)
            ->where('ratings.is_visible', true)
            ->groupBy('ratings.rating')
            ->select([
                'ratings.rating as rating_value',
                DB::raw('COUNT(*) as all_count_for_rating'),
                DB::raw('SUM(CASE WHEN games.is_visible THEN 1 ELSE 0 END) as vis_count_for_rating'),
            ])
            ->get();

        if (! $agg) {
            return [
                'first_rating' => null,
                'latest_rating' => null,
                'all_games' => [
                    'total_ratings' => 0,
                    'reviewed_count' => 0,
                    'review_percentage' => 0,
                    'average_rating' => 0,
                    'unique_games' => 0,
                    'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                ],
                'visible_games' => [
                    'total_ratings' => 0,
                    'reviewed_count' => 0,
                    'review_percentage' => 0,
                    'average_rating' => 0,
                    'unique_games' => 0,
                    'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                ],
            ];
        }

        $allDistribution = [];
        $visDistribution = [];

        foreach ($distRows as $row) {
            $r = (int) $row->rating_value;
            $allDistribution[$r] = (int) ($allDistribution[$r] ?? 0) + (int) $row->all_count_for_rating;
            $visDistribution[$r] = (int) ($visDistribution[$r] ?? 0) + (int) $row->vis_count_for_rating;
        }

        for ($i = 1; $i <= 5; $i++) {
            $allDistribution[$i] = $allDistribution[$i] ?? 0;
            $visDistribution[$i] = $visDistribution[$i] ?? 0;
        }
        ksort($allDistribution);
        ksort($visDistribution);

        return [
            'first_rating' => $agg->first_rating,
            'latest_rating' => $agg->latest_rating,
            'all_games' => [
                'total_ratings' => (int) $agg->all_total_ratings,
                'reviewed_count' => (int) $agg->all_reviewed_count,
                'review_percentage' => ((int) $agg->all_total_ratings) > 0
                    ? (((int) $agg->all_reviewed_count) / ((int) $agg->all_total_ratings) * 100)
                    : 0,
                'average_rating' => (float) ($agg->all_average_rating ?? 0),
                'unique_games' => (int) $agg->all_unique_games,
                'rating_distribution' => $allDistribution,
            ],
            'visible_games' => [
                'total_ratings' => (int) $agg->vis_total_ratings,
                'reviewed_count' => (int) $agg->vis_reviewed_count,
                'review_percentage' => ((int) $agg->vis_total_ratings) > 0
                    ? (((int) $agg->vis_reviewed_count) / ((int) $agg->vis_total_ratings) * 100)
                    : 0,
                'average_rating' => (float) ($agg->vis_average_rating ?? 0),
                'unique_games' => (int) $agg->vis_unique_games,
                'rating_distribution' => $visDistribution,
            ],
        ];
    }

    protected function getCommonPhrases(int $raterId): array
    {
        // Cache the result for 1 hour since phrase analysis is expensive
        return cache()->remember("rater_phrases_{$raterId}", now()->addHour(), function () use ($raterId) {
            $reviews = DB::table('ratings')
                ->where('rater_id', $raterId)
                ->where('ratings.is_visible', true)
                ->whereNotNull('review')
                ->select([
                    'ratings.review',
                    'ratings.rating',
                    'games.name as game_name',
                    'games.slug as game_slug',
                    'ratings.rating as game_rating',
                ])
                ->join('games', 'games.id', '=', 'ratings.game_id')
                ->get();

            if ($reviews->isEmpty()) {
                return [];
            }

            $allPhrases = [];

            // Use a unique boundary marker that cannot appear in user content
            $boundaryMarker = '|||BOUNDARY_' . uniqid() . '|||';

            foreach ($reviews as $review) {
                // Preprocess the review text.
                $rawReview = $review->review;

                // Decode HTML entities first
                $decodedReview = html_entity_decode($rawReview, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Replace line breaks and block-level tags with boundary markers to prevent cross-boundary phrase matching
                $textWithDelimiters = preg_replace('/<br\s*\/?>/i', $boundaryMarker, $decodedReview);
                $textWithDelimiters = preg_replace('/<\/(p|div|h[1-6]|li|ul|ol|tr|td|th|blockquote)>/i', $boundaryMarker, $textWithDelimiters);
                $textWithDelimiters = preg_replace('/<(p|div|h[1-6]|li|ul|ol|tr|td|th|blockquote)[^>]*>/i', '', $textWithDelimiters);

                // Strip remaining tags and split into blocks
                $textStripped = strip_tags($textWithDelimiters);
                $blocks = explode($boundaryMarker, $textStripped);

                // Process each block separately to extract phrases
                $allWords = [];
                foreach ($blocks as $block) {
                    $cleanBlock = strtolower($block);
                    $cleanBlock = preg_replace('/[^\w\s\']/', ' ', $cleanBlock);
                    $cleanBlock = preg_replace('/\s+/', ' ', $cleanBlock);
                    $blockWords = explode(' ', trim($cleanBlock));
                    $blockWords = array_filter($blockWords); // Remove empty strings

                    if (count($blockWords) > 0) {
                        // Add a marker to indicate block boundary
                        $allWords = array_merge($allWords, $blockWords, [$boundaryMarker]);
                    }
                }

                // Remove the trailing boundary marker
                if (end($allWords) === $boundaryMarker) {
                    array_pop($allWords);
                }

                // Count only actual words, not boundary markers
                $actualWordCount = count(array_filter($allWords, function($word) use ($boundaryMarker) {
                    return $word !== $boundaryMarker;
                }));

                if ($actualWordCount === 0) {
                    continue;
                }

                $wordsCount = count($allWords); // Total count including markers for iteration
                $seenPhrases = [];

                // Split the review into sentences for context extraction
                // Replace line breaks and block tags with periods for sentence splitting
                $sentenceText = preg_replace('/<br\s*\/?>/i', '. ', $decodedReview);
                $sentenceText = preg_replace('/<\/(p|div|h[1-6]|li|ul|ol|tr|td|th|blockquote)>/i', '. ', $sentenceText);
                $sentenceText = preg_replace('/<(p|div|h[1-6]|li|ul|ol|tr|td|th|blockquote)[^>]*>/i', ' ', $sentenceText);
                $sentences = preg_split('/(?<=[.!?])\s+/', strip_tags($sentenceText));
                $lowerSentences = array_map('strtolower', $sentences);

                for ($length = 4; $length >= 2; $length--) {
                    if ($wordsCount < $length) {
                        continue;
                    }
                    for ($i = 0; $i <= $wordsCount - $length; $i++) {
                        // Skip if this phrase would cross a block boundary
                        $phraseWords = array_slice($allWords, $i, $length);
                        if (in_array($boundaryMarker, $phraseWords)) {
                            continue;
                        }

                        $phrase = implode(' ', $phraseWords);
                        if (strlen($phrase) < 5 || ! $this->isPhraseMeaningful($phrase)) {
                            continue;
                        }

                        if (isset($seenPhrases[$phrase])) {
                            continue;
                        }
                        $seenPhrases[$phrase] = true;

                        $pattern = '/\b' . implode('[-\s]+', array_map(function ($word) {
                            return preg_quote($word, '/');
                        }, explode(' ', $phrase))) . '\b/';

                        $matchingSentences = [];
                        // Limit to first 3 matching sentences to reduce memory and processing
                        $matchCount = 0;
                        foreach ($lowerSentences as $index => $lowerSentence) {
                            if ($matchCount >= 3) {
                                break;
                            }
                            if (preg_match($pattern, $lowerSentence)) {
                                $matchingSentences[] = $sentences[$index];
                                $matchCount++;
                            }
                        }

                        if (! isset($allPhrases[$phrase])) {
                            $allPhrases[$phrase] = [
                                'count' => 1,
                                'length' => $length,
                                'total_rating' => $review->rating,
                                'contexts' => [
                                    $review->game_name => [
                                        'slug' => $review->game_slug,
                                        'rating' => $review->game_rating,
                                        'sentences' => $matchingSentences,
                                    ],
                                ],
                            ];
                        } else {
                            $allPhrases[$phrase]['count']++;
                            $allPhrases[$phrase]['total_rating'] += $review->rating;
                            if (! isset($allPhrases[$phrase]['contexts'][$review->game_name])) {
                                $allPhrases[$phrase]['contexts'][$review->game_name] = [
                                    'slug' => $review->game_slug,
                                    'rating' => $review->game_rating,
                                    'sentences' => [],
                                ];
                            }
                            // Limit sentences per game to 3
                            $existingSentences = $allPhrases[$phrase]['contexts'][$review->game_name]['sentences'];
                            if (count($existingSentences) < 3) {
                                $allPhrases[$phrase]['contexts'][$review->game_name]['sentences'] = array_merge(
                                    $existingSentences,
                                    array_slice($matchingSentences, 0, 3 - count($existingSentences))
                                );
                            }
                        }
                    }
                }
            }

            // Remove phrases that appear only once.
            $allPhrases = array_filter($allPhrases, fn ($data) => $data['count'] > 1);

            foreach ($allPhrases as &$data) {
                $data['avg_rating'] = $data['total_rating'] / $data['count'];
                // Remove duplicate sentences in each game's context.
                foreach ($data['contexts'] as &$gameData) {
                    $gameData['sentences'] = array_values(array_unique($gameData['sentences']));
                    // Limit to 3 sentences per game
                    $gameData['sentences'] = array_slice($gameData['sentences'], 0, 3);
                }
                unset($data['total_rating']);
            }
            unset($data);

            uasort($allPhrases, function ($a, $b) {
                if ($a['count'] !== $b['count']) {
                    return $b['count'] <=> $a['count'];
                }

                return $b['length'] <=> $a['length'];
            });

            // Optimized filtering: only process top phrases to reduce O(n²) comparisons
            $topPhrases = array_slice($allPhrases, 0, 100, true);
            $filteredPhrases = [];

            foreach ($topPhrases as $phrase => $data) {
                $isSubphrase = false;
                $relations = [];

                foreach ($topPhrases as $otherPhrase => $otherData) {
                    if ($phrase === $otherPhrase) {
                        continue;
                    }

                    // If this phrase is part of another phrase with similar count.
                    if (stripos($otherPhrase, $phrase) !== false &&
                        $otherData['count'] >= ($data['count'] * 0.8)) {
                        $isSubphrase = true;
                        break;
                    } elseif (stripos($phrase, $otherPhrase) !== false &&
                        $data['count'] >= ($otherData['count'] * 0.8)) {
                        // Track related phrases (limit to 3).
                        if (count($relations) < 3) {
                            $relations[] = [
                                'phrase' => $otherPhrase,
                                'count' => $otherData['count'],
                                'avg_rating' => $otherData['avg_rating'],
                            ];
                        }
                    }

                    // REMOVED: expensive similar_text() call that was O(n²) on string length
                }

                if (! $isSubphrase) {
                    $filteredPhrases[$phrase] = $data;
                    $filteredPhrases[$phrase]['related'] = $relations;
                }

                // Early termination: stop once we have 10 phrases
                if (count($filteredPhrases) >= 10) {
                    break;
                }
            }

            return array_slice($filteredPhrases, 0, 10, true);
        });
    }

    private function isPhraseMeaningful(string $phrase): bool
    {
        // Convert filler words into an associative array for faster lookups.
        static $fillerWords = [
            'a' => true, 'about' => true, 'above' => true, 'after' => true, 'again' => true, 'against' => true,
            'all' => true, 'am' => true, 'an' => true, 'and' => true, 'any' => true, 'are' => true, "aren't" => true,
            'as' => true, 'at' => true,
            'be' => true, 'because' => true, 'been' => true, 'before' => true, 'being' => true, 'below' => true,
            'between' => true, 'both' => true, 'but' => true, 'by' => true,
            'could' => true, "couldn't" => true, 'did' => true, "didn't" => true, 'do' => true, 'does' => true,
            "doesn't" => true, 'doing' => true, "don't" => true, 'down' => true, 'during' => true,
            'each' => true, 'few' => true, 'for' => true, 'from' => true, 'further' => true,
            'had' => true, "hadn't" => true, 'has' => true, "hasn't" => true, 'have' => true, "haven't" => true,
            'having' => true, 'he' => true, "he'd" => true, "he'll" => true, "he's" => true, 'her' => true,
            'here' => true, "here's" => true, 'hers' => true, 'herself' => true, 'him' => true, 'himself' => true,
            'his' => true, 'how' => true, "how's" => true,
            'i' => true, "i'd" => true, "i'll" => true, "i'm" => true, "i've" => true, 'if' => true, 'in' => true,
            'into' => true, 'is' => true, "isn't" => true, 'it' => true, "it's" => true, 'its' => true,
            'itself' => true, "let's" => true,
            'me' => true, 'more' => true, 'most' => true, "mustn't" => true, 'my' => true, 'myself' => true,
            'no' => true, 'nor' => true, 'not' => true,
            'of' => true, 'off' => true, 'on' => true, 'once' => true, 'only' => true, 'or' => true, 'other' => true,
            'ought' => true, 'our' => true, 'ours' => true, 'ourselves' => true, 'out' => true, 'over' => true,
            'own' => true,
            'same' => true, "shan't" => true, 'she' => true, "she'd" => true, "she'll" => true, "she's" => true,
            'should' => true, "shouldn't" => true, 'so' => true, 'some' => true, 'such' => true,
            'than' => true, 'that' => true, "that's" => true, 'the' => true, 'their' => true, 'theirs' => true,
            'them' => true, 'themselves' => true, 'then' => true, 'there' => true, "there's" => true,
            'these' => true,
            'they' => true, "they'd" => true, "they'll" => true, "they're" => true, "they've" => true,
            'this' => true, 'those' => true, 'through' => true, 'to' => true,
            'too' => true,
            'under' => true, 'until' => true, 'up' => true,
            'very' => true,
            'was' => true, "wasn't" => true, 'we' => true, "we'd" => true, "we'll" => true, "we're" => true,
            "we've" => true, 'were' => true, "weren't" => true, 'what' => true, "what's" => true, 'when' => true,
            "when's" => true, 'where' => true, "where's" => true, 'which' => true, 'while' => true, 'who' => true,
            "who's" => true, 'whom' => true, 'why' => true, "why's" => true, 'with' => true, "won't" => true,
            'would' => true, "wouldn't" => true,
            'you' => true, "you'd" => true, "you'll" => true, "you're" => true, "you've" => true, 'your' => true,
            'yours' => true, 'yourself' => true, 'yourselves' => true,
        ];

        $words = explode(' ', $phrase);
        $totalWords = count($words);
        if ($totalWords === 0) {
            return false;
        }

        $fillerCount = 0;
        foreach ($words as $word) {
            if (isset($fillerWords[$word])) {
                $fillerCount++;
            }
        }

        if (($fillerCount / $totalWords) >= 0.5) {
            return false;
        }

        return true;
    }
}
