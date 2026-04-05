<script lang="ts">
    import { untrack } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import Stars from '@/components/ui/Stars.svelte';
    import PlatformIcon from '@/components/ui/PlatformIcon.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/components/seo/SeoHead.svelte';
    import ReviewTextControls, { useReviewTextStyles } from '@/components/ReviewTextControls.svelte';

    type RatingRow = {
        id: number;
        game: { id: number; name: string; slug: string; primary_url?: string | null; platform?: string };
        rater: { id: number; name: string; external_platform?: string };
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
            platform?: string | null;
            stars?: number | null;
            sortField: 'published_at' | 'rating';
            sortDirection: 'asc' | 'desc';
            page: number;
            perPage: number;
        };
        metaTags?: MetaTags;
    };

    let { pageTitle = 'Ratings', stats, ratings, filters, metaTags }: RatingsIndexProps = $props();

    const defaultStats: GlobalStats = {
        first_rating: null,
        latest_rating: null,
        all_games: {
            total_ratings: 0,
            reviewed_count: 0,
            review_percentage: 0,
            average_rating: 0,
            unique_games: 0,
            rating_distribution: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 },
        },
        visible_games: {
            total_ratings: 0,
            reviewed_count: 0,
            review_percentage: 0,
            average_rating: 0,
            unique_games: 0,
            rating_distribution: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 },
        },
    };
    const safeStats = $derived(stats ?? defaultStats);

    let pageNum = $state(untrack(() => filters?.page ?? 1));
    let perPage = $state(untrack(() => filters?.perPage ?? ratings?.per_page ?? 10));
    let showOnlyReviews = $state(untrack(() => filters?.showOnlyReviews ?? true));
    let showOnlyVisibleGames = $state(untrack(() => filters?.showOnlyVisibleGames ?? true));
    let platform = $state<string>(untrack(() => filters?.platform ?? ''));
    let sortField = $state<'published_at' | 'rating'>(untrack(() => filters?.sortField ?? 'published_at'));
    let sortDirection = $state<'asc' | 'desc'>(untrack(() => filters?.sortDirection ?? 'desc'));
    let isLoading = $state(false);
    let stars = $state<number | ''>(untrack(() => filters?.stars ?? ''));

    const reviewStylesObj = useReviewTextStyles();
    const reviewStyles = $derived(
        `max-width: ${reviewStylesObj.maxWidth}; font-size: ${reviewStylesObj.fontSize}; line-height: ${reviewStylesObj.lineHeight}; margin: ${reviewStylesObj.margin};`,
    );

    const ratingMeta = $derived({
        current_page: ratings?.current_page ?? pageNum,
        last_page: ratings?.last_page ?? 0,
        per_page: ratings?.per_page ?? perPage,
        total: ratings?.total ?? 0,
    });

    let didMount = false;
    $effect(() => {
        void pageNum;
        void perPage;
        void showOnlyReviews;
        void showOnlyVisibleGames;
        void platform;
        void sortField;
        void sortDirection;
        void stars;

        if (!didMount) {
            didMount = true;
            return;
        }

        const desired: Record<string, string> = {
            page: String(pageNum),
            perPage: String(perPage),
            showOnlyReviews: String(showOnlyReviews),
            showOnlyVisibleGames: String(showOnlyVisibleGames),
            sortField,
            sortDirection,
        };
        if (platform !== '') desired.platform = platform;
        if (stars !== '') desired.stars = String(stars);

        if (typeof window === 'undefined') return;
        const current = new URLSearchParams(window.location.search);
        if (new URLSearchParams(desired).toString() === current.toString()) return;

        isLoading = true;
        router.get(route('ratings.index'), desired, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['ratings', 'filters'],
            onFinish: () => {
                isLoading = false;
            },
        });
    });

    const buildPageUrl = (p: number): string => {
        const params = new SvelteURLSearchParams();
        params.set('page', p.toString());
        params.set('perPage', perPage.toString());
        params.set('showOnlyReviews', String(showOnlyReviews));
        params.set('showOnlyVisibleGames', String(showOnlyVisibleGames));
        if (platform) params.set('platform', platform);
        params.set('sortField', sortField);
        params.set('sortDirection', sortDirection);
        if (stars !== '') params.set('stars', String(stars));
        return `/ratings?${params.toString()}`;
    };
</script>

<SeoHead {metaTags} />
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{pageTitle}</h1>
    </div>

    <!-- Stats header -->
    <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Global Rating Statistics</h2>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {safeStats.first_rating
                    ? new Date(safeStats.first_rating).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
                    : '\u2014'}
                \u2013
                {safeStats.latest_rating
                    ? new Date(safeStats.latest_rating).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
                    : '\u2014'}
            </div>
        </div>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Listed Games Rated</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {safeStats.visible_games.unique_games.toLocaleString()}
                </div>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Rating (Listed)</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {Number(safeStats.visible_games.average_rating ?? 0).toFixed(1)}
                </div>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Review Rate (Listed)</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {Math.round(safeStats.visible_games.review_percentage)}%
                </div>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Ratings Count (Listed)</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {safeStats.visible_games.total_ratings.toLocaleString()}
                </div>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Listed Games Rating Distribution</h3>
            <div class="space-y-2">
                {#each Object.entries(safeStats.visible_games.rating_distribution) as [ratingKey, count] (ratingKey)}
                    {@const total = safeStats.visible_games.total_ratings}
                    {@const percentage = total > 0 ? (Number(count) / total) * 100 : 0}
                    <div>
                        <div class="flex items-center">
                            <span class="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">{Number(ratingKey)} Stars</span>
                            <div class="mx-2 flex-1">
                                <div class="h-4 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full bg-yellow-400 dark:bg-yellow-500" style="width: {percentage}%"></div>
                                </div>
                            </div>
                            <div class="flex w-[11rem] items-center justify-end gap-1 text-sm text-gray-500 dark:text-gray-400">
                                <span class="w-[6.5rem] text-right whitespace-nowrap tabular-nums">{Number(count).toLocaleString()}</span>
                                <span class="w-[4.5rem] text-right whitespace-nowrap tabular-nums">({percentage.toFixed(1)}%)</span>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        </div>
    </div>

    <ReviewTextControls />

    <!-- Filters and sorting -->
    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
        <div class="flex flex-wrap items-center gap-4">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    bind:checked={showOnlyReviews}
                    onchange={() => {
                        pageNum = 1;
                    }}
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                />
                Reviews only
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    bind:checked={showOnlyVisibleGames}
                    onchange={() => {
                        pageNum = 1;
                    }}
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                />
                Listed games only
            </label>
            <div class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span>Platform:</span>
                <select
                    bind:value={platform}
                    onchange={() => {
                        pageNum = 1;
                    }}
                    class="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    <option value="">Any</option>
                    <option value="itch_io">itch.io</option>
                    <option value="steam">Steam</option>
                </select>
            </div>
            <div class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span>Stars:</span>
                <select
                    value={stars}
                    onchange={(e) => {
                        const v = (e.target as HTMLSelectElement).value;
                        stars = v === '' ? '' : (Number(v) as number);
                        pageNum = 1;
                    }}
                    class="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    <option value="">Any</option>
                    {#each [5, 4, 3, 2, 1] as r (r)}
                        <option value={r}>{r} Stars</option>
                    {/each}
                </select>
            </div>
            <div class="ml-auto flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span>Sort by:</span>
                <select
                    value={`${sortField}:${sortDirection}`}
                    onchange={(e) => {
                        const [f, d] = (e.target as HTMLSelectElement).value.split(':');
                        sortField = f as any;
                        sortDirection = d as any;
                        pageNum = 1;
                    }}
                    class="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    <option value="published_at:desc">Newest</option>
                    <option value="published_at:asc">Oldest</option>
                    <option value="rating:desc">Rating: High to Low</option>
                    <option value="rating:asc">Rating: Low to High</option>
                </select>
            </div>
        </div>
    </div>

    <div class="rounded-lg bg-white shadow dark:bg-gray-800">
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            {#if !ratings || ratings.data.length === 0}
                <div class="p-6 text-gray-500 dark:text-gray-400">No ratings yet</div>
            {:else}
                {#each ratings.data as row (row.id)}
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <Link href={route('games.show', row.game.slug)} class="font-medium text-blue-700 hover:underline dark:text-blue-300"
                                    >{row.game.name}</Link
                                >
                                {#if row.game.primary_url}
                                    <a
                                        href={route('track.external-project', { game_id: row.game.id, url: row.game.primary_url })}
                                        target="_blank"
                                        rel="noopener"
                                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                        title="Open on external platform"
                                    >
                                        <i class="icon-external-link"></i>
                                    </a>
                                {/if}
                                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    <span
                                        >by <Link href={route('raters.show', row.rater.id)} class="text-gray-800 hover:underline dark:text-gray-100"
                                            >{row.rater.name}</Link
                                        ></span
                                    >
                                    {#if row.rater.external_platform}
                                        <PlatformIcon platform={row.rater.external_platform} />
                                    {/if}
                                    <span class="text-gray-500 dark:text-gray-400">&bull; {new Date(row.created_at).toLocaleString()}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <Stars rating={row.score} />
                                <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">{row.score.toFixed(1)}</div>
                            </div>
                        </div>
                        {#if row.review}
                            <div class="prose mt-2 max-w-none text-gray-600 dark:text-gray-300 dark:prose-invert" style={reviewStyles}>
                                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                {@html row.review}
                            </div>
                        {/if}
                    </div>
                {/each}
            {/if}
        </div>
        <div class="p-4">
            <AdvancedPagination
                meta={ratingMeta}
                onPageChange={(p) => {
                    pageNum = p;
                }}
                onPerPageChange={(pp) => {
                    perPage = pp;
                    pageNum = 1;
                }}
                {isLoading}
                label="ratings"
                perPageOptions={[10, 25, 50, 100]}
                {buildPageUrl}
            />
        </div>
    </div>
</div>
