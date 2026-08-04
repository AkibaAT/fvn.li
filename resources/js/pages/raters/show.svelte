<script lang="ts">
    import { untrack } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import { Button, Card, Checkbox, Dialog, Select, Stars } from '@/components/ui';
    import { Link, router } from '@inertiajs/svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/components/seo/SeoHead.svelte';
    import ReviewTextControls, { useReviewTextStyles } from '@/components/ReviewTextControls.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';

    type Rater = {
        id: number;
        name: string;
        avatar?: string | null;
        bio?: string | null;
        joined_at?: string | null;
        ratings_count?: number;
        average_score?: number | null;
    };

    type RaterRating = {
        id: number;
        rating: number;
        published_at: string | null;
        is_reviewed: boolean;
        review?: string | null;
        event_id?: number | null;
        is_visible: boolean;
        game: { id: number; name: string; slug: string; primary_url?: string | null; platform?: string; is_visible?: boolean };
    };

    type RatingDistribution = { [key: number]: number };

    type Stats = {
        first_rating?: string;
        latest_rating?: string;
        all_games: {
            total_ratings: number;
            reviewed_count: number;
            review_percentage: number;
            average_rating: number;
            unique_games: number;
            rating_distribution: RatingDistribution;
        };
        visible_games: {
            total_ratings: number;
            reviewed_count: number;
            review_percentage: number;
            average_rating: number;
            unique_games: number;
            rating_distribution: RatingDistribution;
        };
    };

    type PhraseContext = {
        slug: string;
        rating: number;
        sentences: string[];
    };

    type PhraseData = {
        count: number;
        length: number;
        avg_rating: number;
        contexts: { [gameName: string]: PhraseContext };
        related: { phrase: string; count: number; avg_rating: number }[];
    };

    type Phrases = { [phrase: string]: PhraseData };

    type RaterShowProps = {
        pageTitle?: string;
        rater: Rater;
        ratings?: {
            data: RaterRating[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        stats?: Stats;
        phrases?: Phrases;
        previousRatingCounts?: Record<number, number>;
        filters?: {
            showOnlyReviews: boolean;
            showOnlyVisibleGames: boolean;
            sortField: 'published_at' | 'rating';
            sortDirection: 'asc' | 'desc';
            page: number;
            perPage: number;
        };
        metaTags?: MetaTags;
    };

    let { rater, ratings, stats, phrases, previousRatingCounts = {}, filters, metaTags }: RaterShowProps = $props();

    const defaultStats: Stats = {
        first_rating: undefined,
        latest_rating: undefined,
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
    const safePhrases: Phrases = $derived(phrases ?? {});
    let selectedPhrase = $state<string | null>(null);
    let showContext = $state(false);
    let showOnlyReviews = $state(untrack(() => filters?.showOnlyReviews ?? true));
    let showOnlyVisibleGames = $state(untrack(() => filters?.showOnlyVisibleGames ?? false));
    let sortField = $state<'published_at' | 'rating'>(untrack(() => filters?.sortField ?? 'published_at'));
    let sortDirection = $state<'asc' | 'desc'>(untrack(() => filters?.sortDirection ?? 'desc'));
    let page = $state<number>(untrack(() => filters?.page ?? 1));
    let perPage = $state<number>(untrack(() => filters?.perPage ?? 10));
    let isLoading = $state(false);
    let historyModal = $state<{ gameName: string; ratings: RaterRating[]; open: boolean; error: string | null }>({
        gameName: '',
        ratings: [],
        open: false,
        error: null,
    });
    const reviewStylesObj = useReviewTextStyles();
    const reviewStyles = $derived(
        `max-width: ${reviewStylesObj.maxWidth}; font-size: ${reviewStylesObj.fontSize}; line-height: ${reviewStylesObj.lineHeight}; margin: ${reviewStylesObj.margin};`,
    );

    let didMount = false;
    $effect(() => {
        // Track all filter dependencies
        void page;
        void perPage;
        void showOnlyReviews;
        void showOnlyVisibleGames;
        void sortField;
        void sortDirection;

        if (!didMount) {
            didMount = true;
            return;
        }

        const desired = new URLSearchParams({
            page: String(page),
            perPage: String(perPage),
            showOnlyReviews: String(showOnlyReviews),
            showOnlyVisibleGames: String(showOnlyVisibleGames),
            sortField,
            sortDirection,
        });

        if (typeof window === 'undefined') return;
        const current = new URLSearchParams(window.location.search);
        if (desired.toString() === current.toString()) return;

        isLoading = true;
        router.get(route('raters.show', rater.id), Object.fromEntries(desired.entries()), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['ratings', 'previousRatingCounts', 'filters'],
            onFinish: () => {
                isLoading = false;
            },
        });
    });

    const ratingMeta = $derived(
        ratings
            ? { current_page: ratings.current_page, last_page: ratings.last_page, per_page: ratings.per_page, total: ratings.total }
            : { current_page: 1, last_page: 0, per_page: perPage, total: 0 },
    );

    const buildPageUrl = (pageNum: number): string => {
        const params = new SvelteURLSearchParams();
        params.set('page', pageNum.toString());
        params.set('perPage', perPage.toString());
        params.set('showOnlyReviews', String(showOnlyReviews));
        params.set('showOnlyVisibleGames', String(showOnlyVisibleGames));
        params.set('sortField', sortField);
        params.set('sortDirection', sortDirection);
        return `/raters/${rater.id}?${params.toString()}`;
    };

    const openHistory = async (gameId: number, gameName: string) => {
        isLoading = true;
        try {
            const res = await fetch(route('raters.games.history', { rater: rater.id, game: gameId }), {
                headers: {
                    Accept: 'application/json',
                },
            });
            if (!res.ok) {
                throw new Error(`Rating history request failed with ${res.status}`);
            }
            const json = await res.json();
            historyModal = { gameName, ratings: json.ratings ?? [], open: true, error: null };
        } catch (error) {
            console.error('Failed to load rating history', error);
            historyModal = { gameName, ratings: [], open: true, error: 'Unable to load rating history.' };
        } finally {
            isLoading = false;
        }
    };

    const closeHistory = () => {
        historyModal = { ...historyModal, open: false };
    };

    const colorForAvg = (avg: number) => {
        if (avg >= 4) return 'bg-green-50 dark:bg-green-900 text-green-900 dark:text-green-100';
        if (avg >= 3) return 'bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100';
        return 'bg-red-50 dark:bg-red-900 text-red-900 dark:text-red-100';
    };

    function closePhrasesDialog() {
        showContext = false;
    }
</script>

<SeoHead {metaTags} />
<div class="space-y-6">
    <PageHeader title={`${rater.name}'s Ratings`} backHref={route('ratings.index')} backLabel="Back to Ratings" class="mb-0" />

    <!-- Stats -->
    <Card padding="lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{rater.name}'s Rating Statistics</h2>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Games Rated</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{safeStats.all_games.unique_games.toLocaleString()}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{safeStats.visible_games.unique_games.toLocaleString()} listed</div>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Rating</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {Number(safeStats.all_games.average_rating ?? 0).toFixed(1)}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {Number(safeStats.visible_games.average_rating ?? 0).toFixed(1)} for listed games
                </div>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Review Rate</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{Math.round(safeStats.all_games.review_percentage)}%</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{Math.round(safeStats.visible_games.review_percentage)}% for listed games</div>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Rating Period</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {safeStats.first_rating
                        ? new Date(safeStats.first_rating).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
                        : '\u2014'}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    to {safeStats.latest_rating
                        ? new Date(safeStats.latest_rating).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
                        : '\u2014'}
                </div>
            </div>
        </div>

        <!-- Two distributions -->
        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            {#each [['All Games', safeStats.all_games], ['Listed Games', safeStats.visible_games]] as [title, block] (title)}
                {@const statsBlock = block as Stats['all_games']}
                <div>
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">{title} Rating Distribution</h3>
                    <div class="space-y-2">
                        {#each Object.entries(statsBlock.rating_distribution) as [ratingKey, count] (ratingKey)}
                            {@const percentage = statsBlock.total_ratings > 0 ? (Number(count) / statsBlock.total_ratings) * 100 : 0}
                            <div>
                                <div class="flex items-center">
                                    <span class="w-16 text-sm font-medium text-gray-500 dark:text-gray-400">{Number(ratingKey)} Stars</span>
                                    <div class="mx-2 flex-1">
                                        <div class="h-4 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-full bg-yellow-400 dark:bg-yellow-500" style="width: {percentage}%"></div>
                                        </div>
                                    </div>
                                    <span class="w-20 text-right text-sm text-gray-500 dark:text-gray-400">
                                        {Number(count).toLocaleString()} ({percentage.toFixed(1)}%)
                                    </span>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            {/each}
        </div>
    </Card>

    <!-- Phrases -->
    <Card padding="lg" class="shadow">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Common Phrases in Reviews</h2>
        <div class="mt-4">
            {#if Object.keys(safePhrases).length === 0}
                <div class="text-gray-500 dark:text-gray-400">No common phrases found</div>
            {:else}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {#each Object.entries(safePhrases) as [phrase, data] (phrase)}
                        {@const color = colorForAvg(data.avg_rating)}
                        <div class="flex items-center justify-between rounded p-2 {color}">
                            <span class="flex-grow">{phrase}</span>
                            <div class="ml-2 flex items-center gap-2 text-sm opacity-75">
                                <span>{data.count}x</span>
                                <span>({data.avg_rating.toFixed(1)}★)</span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    tone="neutral"
                                    size="icon-sm"
                                    onclick={() => {
                                        selectedPhrase = phrase;
                                        showContext = true;
                                    }}
                                    class="ml-1"
                                    title="Show contexts"
                                    ariaLabel="Show contexts"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </Button>
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>
        {#if Object.keys(safePhrases).length > 0}
            <div class="mt-4 flex gap-4 text-sm text-gray-500 dark:text-gray-400">
                <div><span class="mr-1 inline-block h-3 w-3 rounded bg-green-100 dark:bg-green-900"></span>Positive context (4-5★)</div>
                <div><span class="mr-1 inline-block h-3 w-3 rounded bg-gray-100 dark:bg-gray-700"></span>Neutral context (3★)</div>
                <div><span class="mr-1 inline-block h-3 w-3 rounded bg-red-100 dark:bg-red-900"></span>Negative context (1-2★)</div>
            </div>
        {/if}
    </Card>

    <!-- Review Text Controls -->
    <ReviewTextControls />

    <Card padding="none" class="overflow-hidden">
        <div class="border-b border-gray-200 p-4 dark:border-gray-700">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Rating History</h2>
                    <div class="mt-1 flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                        <span>{ratingMeta.total.toLocaleString()}</span>
                        <span>{showOnlyReviews ? 'reviews' : 'ratings'}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <Checkbox
                        label="Reviews only"
                        bind:checked={showOnlyReviews}
                        onchange={() => {
                            page = 1;
                        }}
                    />

                    <Checkbox
                        label="Listed games only"
                        bind:checked={showOnlyVisibleGames}
                        onchange={() => {
                            page = 1;
                        }}
                    />

                    <div class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <span>Sort by:</span>
                        <Select
                            value={`${sortField}:${sortDirection}`}
                            onchange={(e) => {
                                const [field, direction] = (e.target as HTMLSelectElement).value.split(':');
                                sortField = field as 'published_at' | 'rating';
                                sortDirection = direction as 'asc' | 'desc';
                                page = 1;
                            }}
                        >
                            <option value="published_at:desc">Newest</option>
                            <option value="published_at:asc">Oldest</option>
                            <option value="rating:desc">Rating: High to Low</option>
                            <option value="rating:asc">Rating: Low to High</option>
                        </Select>
                    </div>
                </div>
            </div>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            {#if !ratings || ratings.data.length === 0}
                <div class="p-6 text-gray-500 dark:text-gray-400">No ratings</div>
            {:else}
                {#each ratings.data as row (row.id)}
                    <div class="p-6">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <Link
                                    href={route('games.show', { game: row.game.slug })}
                                    class="text-lg font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    {row.game.name}
                                </Link>
                                {#if previousRatingCounts[row.game.id]}
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        <Button type="button" variant="link" tone="neutral" onclick={() => openHistory(row.game.id, row.game.name)}>
                                            ({previousRatingCounts[row.game.id]} previous
                                            {previousRatingCounts[row.game.id] > 1 ? ' ratings' : ' rating'})
                                        </Button>
                                    </span>
                                {/if}
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
                            </div>
                            <div class="flex items-center gap-4">
                                <Stars rating={row.rating} />
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {row.published_at
                                        ? new Date(row.published_at).toLocaleDateString(undefined, {
                                              month: 'short',
                                              day: 'numeric',
                                              year: 'numeric',
                                          })
                                        : ''}
                                </span>
                                {#if row.event_id}
                                    <a
                                        href={`https://itch.io/event/${row.event_id}`}
                                        target="_blank"
                                        rel="noopener"
                                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                        title="View on itch.io"
                                    >
                                        <i class="icon-external-link"></i>
                                    </a>
                                {/if}
                            </div>
                        </div>
                        {#if row.review}
                            <div class="mx-auto prose mt-2 text-gray-600 dark:text-gray-300 dark:prose-invert" style={reviewStyles}>
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
                    page = p;
                }}
                onPerPageChange={(pp) => {
                    perPage = pp;
                    page = 1;
                }}
                {isLoading}
                label="ratings"
                {buildPageUrl}
            />
        </div>
    </Card>

    <!-- Common Phrases Dialog -->
    <Dialog
        open={showContext && !!selectedPhrase && !!safePhrases[selectedPhrase]}
        onClose={closePhrasesDialog}
        title={selectedPhrase ?? 'Phrase Contexts'}
        size="xl"
    >
        {#if selectedPhrase && safePhrases[selectedPhrase]}
            <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                {safePhrases[selectedPhrase].count}x / {safePhrases[selectedPhrase].avg_rating.toFixed(1)}★
            </div>
            <div class="max-h-96 space-y-4 overflow-y-auto">
                {#each Object.entries(safePhrases[selectedPhrase].contexts) as [gameName, context] (gameName)}
                    <div>
                        <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">
                            <Link href={route('games.show', { game: context.slug })} class="text-blue-600 hover:underline dark:text-blue-400"
                                >{gameName}</Link
                            >
                            <span class="font-normal text-gray-500 dark:text-gray-400">({context.rating}★)</span>
                        </h4>
                        <div class="space-y-2">
                            {#each context.sentences as sentence, _index (_index)}
                                <div class="rounded bg-gray-50 p-2 text-sm dark:bg-gray-700">
                                    {sentence}
                                </div>
                            {/each}
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </Dialog>

    <!-- Rating History Dialog -->
    <Dialog open={historyModal.open} onClose={closeHistory} title={historyModal.gameName || 'Rating History'} size="lg">
        {#if historyModal.gameName}
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Rating history for this game:</p>
        {/if}
        <div class="space-y-6">
            {#if historyModal.error}
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200">
                    {historyModal.error}
                </div>
            {:else if historyModal.ratings.length > 0}
                {#each historyModal.ratings as hr, idx (hr.id)}
                    <div class={idx < historyModal.ratings.length - 1 ? 'border-b border-gray-200 pb-6 dark:border-gray-700' : ''}>
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Stars rating={hr.rating} />
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {hr.published_at
                                        ? new Date(hr.published_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
                                        : ''}
                                </span>
                                {#if hr.is_visible}
                                    <span class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                                        >Current</span
                                    >
                                {/if}
                            </div>
                            {#if hr.event_id}
                                <a
                                    href={`https://itch.io/event/${hr.event_id}`}
                                    target="_blank"
                                    rel="noopener"
                                    class="text-sm text-blue-600 hover:underline dark:text-blue-400">View on itch.io</a
                                >
                            {/if}
                        </div>
                        {#if hr.review}
                            <div class="mx-auto prose text-gray-600 dark:text-gray-300 dark:prose-invert" style={reviewStyles}>
                                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                {@html hr.review}
                            </div>
                        {/if}
                    </div>
                {/each}
            {:else}
                <div class="py-4 text-center text-gray-500 dark:text-gray-400">No rating history found.</div>
            {/if}
        </div>
        {#snippet footer()}
            <Button type="button" variant="outline" tone="neutral" onclick={closeHistory}>Close</Button>
        {/snippet}
    </Dialog>
</div>
