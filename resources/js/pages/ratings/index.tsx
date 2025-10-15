import AdvancedPagination from '@/components/advanced-pagination';
import Stars from '@/components/ui/stars';
import {Link, router} from '@inertiajs/react';
import {useEffect, useMemo, useRef, useState} from 'react';
import SeoHead, {type MetaTags} from '@/components/seo/SeoHead';

type RatingRow = {
    id: number;
    game: { id: number; name: string; slug: string };
    rater: { id: number; name: string };
    score: number;
    created_at: string;
    is_reviewed?: boolean;
    review?: string | null;
};

type RatingDistribution = { [key: number]: number };
type StatsBlock = {
    total_ratings: number;
    reviewed_count: number;
    review_percentage: number;
    average_rating: number;
    unique_games: number;
    rating_distribution: RatingDistribution;
};
type GlobalStats = {
    first_rating?: string | null;
    latest_rating?: string | null;
    all_games: StatsBlock;
    visible_games: StatsBlock;
};

type RatingsIndexProps = {
    pageTitle?: string;
    stats?: GlobalStats;
    ratings?: {
        data: RatingRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters?: {
        showOnlyReviews: boolean;
        showOnlyVisibleGames: boolean;
        stars?: number | null;
        sortField: 'published_at' | 'rating';
        sortDirection: 'asc' | 'desc';
        page: number;
        perPage: number;
    };
    metaTags?: MetaTags;
};

export default function RatingsIndex({pageTitle = 'Ratings', stats, ratings, filters, metaTags}: RatingsIndexProps) {
    const defaultStats: GlobalStats = useMemo(
        () => ({
            first_rating: null,
            latest_rating: null,
            all_games: {
                total_ratings: 0,
                reviewed_count: 0,
                review_percentage: 0,
                average_rating: 0,
                unique_games: 0,
                rating_distribution: {1: 0, 2: 0, 3: 0, 4: 0, 5: 0},
            },
            visible_games: {
                total_ratings: 0,
                reviewed_count: 0,
                review_percentage: 0,
                average_rating: 0,
                unique_games: 0,
                rating_distribution: {1: 0, 2: 0, 3: 0, 4: 0, 5: 0},
            },
        }),
        [],
    );
    const safeStats = stats ?? defaultStats;
    const initialPage = filters?.page ?? 1;
    const initialPerPage = filters?.perPage ?? ratings?.per_page ?? 10;
    const [page, setPage] = useState(initialPage);
    const [perPage, setPerPage] = useState(initialPerPage);
    const [showOnlyReviews, setShowOnlyReviews] = useState(filters?.showOnlyReviews ?? true);
    const [showOnlyVisibleGames, setShowOnlyVisibleGames] = useState(filters?.showOnlyVisibleGames ?? true);
    const [sortField, setSortField] = useState<'published_at' | 'rating'>(filters?.sortField ?? 'published_at');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>(filters?.sortDirection ?? 'desc');
    const [isLoading, setIsLoading] = useState(false);
    const [stars, setStars] = useState<number | ''>(filters?.stars ?? '');

    const ratingMeta = useMemo(
        () => ({
            current_page: ratings?.current_page ?? page,
            last_page: ratings?.last_page ?? 0,
            per_page: ratings?.per_page ?? perPage,
            total: ratings?.total ?? 0,
        }),
        [ratings, page, perPage],
    );

    const didMountRef = useRef(false);
    useEffect(() => {
        // Avoid adding query params on initial page load
        if (!didMountRef.current) {
            didMountRef.current = true;
            return;
        }

        // Build desired params and compare with current URL; only navigate if different
        const desired = new URLSearchParams({
            page: String(page),
            perPage: String(perPage),
            showOnlyReviews: String(showOnlyReviews),
            showOnlyVisibleGames: String(showOnlyVisibleGames),
            sortField,
            sortDirection,
            ...(stars !== '' ? {stars: String(stars)} : {}),
        } as Record<string, string>);
        const current = new URLSearchParams(window.location.search);
        if (desired.toString() === current.toString()) return;

        setIsLoading(true);
        router.get(
            route('ratings.index'),
            Object.fromEntries(desired.entries()),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['ratings', 'filters'],
                onFinish: () => setIsLoading(false),
            },
        );
    }, [page, perPage, showOnlyReviews, showOnlyVisibleGames, sortField, sortDirection, stars]);

    // Build SSR-friendly URLs for pagination
    const buildPageUrl = (pageNum: number): string => {
        const params = new URLSearchParams();
        params.set('page', pageNum.toString());
        params.set('perPage', perPage.toString());
        params.set('showOnlyReviews', String(showOnlyReviews));
        params.set('showOnlyVisibleGames', String(showOnlyVisibleGames));
        params.set('sortField', sortField);
        params.set('sortDirection', sortDirection);
        if (stars !== '') params.set('stars', String(stars));
        return `/ratings?${params.toString()}`;
    };

    return (
        <>
            <SeoHead metaTags={metaTags} />
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {pageTitle}
                    </h1>
                    <div className="flex items-center gap-2 text-sm"/>
                </div>

                {/* Stats header (listed games only) */}
                <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Global Rating
                            Statistics</h2>
                        <div className="text-sm text-gray-500 dark:text-gray-400">
                            {safeStats.first_rating ? new Date(safeStats.first_rating).toLocaleDateString(undefined, {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric'
                            }) : '—'}
                            {' – '}
                            {safeStats.latest_rating ? new Date(safeStats.latest_rating).toLocaleDateString(undefined, {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric'
                            }) : '—'}
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Listed Games
                                Rated
                            </div>
                            <div
                                className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{safeStats.visible_games.unique_games.toLocaleString()}</div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Average Rating
                                (Listed)
                            </div>
                            <div
                                className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{Number(safeStats.visible_games.average_rating ?? 0).toFixed(1)}</div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Review Rate (Listed)
                            </div>
                            <div
                                className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{Math.round(safeStats.visible_games.review_percentage)}%
                            </div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Ratings Count
                                (Listed)
                            </div>
                            <div
                                className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{safeStats.visible_games.total_ratings.toLocaleString()}</div>
                        </div>
                    </div>

                    {/* Distribution (listed only) */}
                    <div className="mt-6">
                        <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Listed Games Rating
                            Distribution</h3>
                        <div className="space-y-2">
                            {Object.entries(safeStats.visible_games.rating_distribution).map(([ratingKey, count]) => {
                                const ratingNum = Number(ratingKey);
                                const total = safeStats.visible_games.total_ratings;
                                const percentage = total > 0 ? (Number(count) / total) * 100 : 0;
                                return (
                                    <div key={ratingKey}>
                                        <div className="flex items-center">
                                            <span
                                                className="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">{ratingNum} Stars</span>
                                            <div className="mx-2 flex-1">
                                                <div
                                                    className="h-4 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div className="h-full bg-yellow-400 dark:bg-yellow-500"
                                                         style={{width: `${percentage}%`}}/>
                                                </div>
                                            </div>
                                            <div
                                                className="flex w-[11rem] items-center justify-end gap-1 text-sm text-gray-500 dark:text-gray-400">
                                                <span className="w-[6.5rem] text-right tabular-nums whitespace-nowrap">
                                                    {Number(count).toLocaleString()}
                                                </span>
                                                <span className="w-[4.5rem] text-right tabular-nums whitespace-nowrap">
                                                    ({percentage.toFixed(1)}%)
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Filters and sorting */}
                <div className="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <div className="flex flex-wrap items-center gap-4">
                        <label className="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={showOnlyReviews}
                                onChange={(e) => {
                                    setShowOnlyReviews(e.target.checked);
                                    setPage(1);
                                }}
                                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                            />
                            Reviews only
                        </label>
                        <label className="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={showOnlyVisibleGames}
                                onChange={(e) => {
                                    setShowOnlyVisibleGames(e.target.checked);
                                    setPage(1);
                                }}
                                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                            />
                            Listed games only
                        </label>
                        <div className="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <span>Stars:</span>
                            <select
                                value={stars}
                                onChange={(e) => {
                                    const v = e.target.value === '' ? '' : Number(e.target.value);
                                    setStars(v as number | '');
                                    setPage(1);
                                }}
                                className="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">Any</option>
                                {[5, 4, 3, 2, 1].map((r) => (
                                    <option key={r} value={r}>{r} Stars</option>
                                ))}
                            </select>
                        </div>
                        <div className="ml-auto flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <span>Sort by:</span>
                            <select
                                value={`${sortField}:${sortDirection}`}
                                onChange={(e) => {
                                    const [field, dir] = e.target.value.split(':') as ['published_at' | 'rating', 'asc' | 'desc'];
                                    setSortField(field);
                                    setSortDirection(dir);
                                    setPage(1);
                                }}
                                className="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="published_at:desc">Newest</option>
                                <option value="published_at:asc">Oldest</option>
                                <option value="rating:desc">Rating: High to Low</option>
                                <option value="rating:asc">Rating: Low to High</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow dark:bg-gray-800">
                    <div className="divide-y divide-gray-200 dark:divide-gray-700">
                        {!ratings || ratings.data.length === 0 ? (
                            <div className="p-6 text-gray-500 dark:text-gray-400">
                                No ratings yet
                            </div>
                        ) : (
                            ratings.data.map((row) => (
                                <div key={row.id} className="p-4">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-1">
                                            <Link
                                                href={route(
                                                    'games.show',
                                                    row.game.slug,
                                                )}
                                                className="font-medium text-blue-700 hover:underline dark:text-blue-300"
                                            >
                                                {row.game.name}
                                            </Link>
                                            <div className="text-sm text-gray-600 dark:text-gray-300">
                                                by{' '}
                                                <Link
                                                    href={route(
                                                        'raters.show',
                                                        row.rater.id,
                                                    )}
                                                    className="text-gray-800 hover:underline dark:text-gray-100"
                                                >
                                                    {row.rater.name}
                                                </Link>
                                                {' • '}
                                                <span className="text-gray-500 dark:text-gray-400">
                                                    {new Date(
                                                        row.created_at,
                                                    ).toLocaleString()}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Stars rating={row.score}/>
                                            <div
                                                className="text-lg font-semibold text-gray-900 dark:text-gray-100">{row.score.toFixed(1)}</div>
                                        </div>
                                    </div>
                                    {row.review ? (
                                        <div
                                            className="prose dark:prose-invert mt-2 max-w-none text-gray-600 dark:text-gray-300">
                                            {/* review is trusted HTML from server */}
                                            <div dangerouslySetInnerHTML={{__html: row.review || ''}}/>
                                        </div>
                                    ) : null}
                                </div>
                            ))
                        )}
                    </div>
                    <div className="p-4">
                        <AdvancedPagination
                            meta={ratingMeta}
                            onPageChange={(p) => setPage(p)}
                            onPerPageChange={(pp) => {
                                setPerPage(pp);
                                setPage(1);
                            }}
                            isLoading={isLoading}
                            label="ratings"
                            perPageOptions={[10, 25, 50, 100]}
                            buildPageUrl={buildPageUrl}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
