<script lang="ts">
    import InformationCircleIcon from '@/components/icons/InformationCircle.svelte';
    import type { RatingHistoryEntry } from '@/api';
    import { untrack } from 'svelte';
    import RatingHistoryDialog from '@/components/RatingHistoryDialog.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import RatingFilterBar from '@/components/ratings/RatingFilterBar.svelte';
    import RatingRow from '@/components/ratings/RatingRow.svelte';
    import RatingStatsCard from '@/components/ratings/RatingStatsCard.svelte';
    import { emptyStats, type GlobalStats, type RatingRowData } from '@/components/ratings/types';
    import { Button, Card, Dialog } from '@/components/ui';
    import { Link } from '@inertiajs/svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/types/meta-tags';
    import ReviewTextControls, { useReviewTextStyles } from '@/components/ReviewTextControls.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { useUrlSyncedFilters } from '@/hooks/useUrlSyncedFilters.svelte';

    type Rater = {
        id: number;
        name: string;
        avatar?: string | null;
        bio?: string | null;
        joined_at?: string | null;
        ratings_count?: number;
        average_score?: number | null;
    };

    type RaterRating = RatingHistoryEntry;

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
        stats?: GlobalStats;
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

    const safeStats = $derived(stats ?? emptyStats());
    const safePhrases: Phrases = $derived(phrases ?? {});
    let selectedPhrase = $state<string | null>(null);
    let showContext = $state(false);
    let showOnlyReviews = $state(untrack(() => filters?.showOnlyReviews ?? true));
    let showOnlyVisibleGames = $state(untrack(() => filters?.showOnlyVisibleGames ?? false));
    let sortField = $state<'published_at' | 'rating'>(untrack(() => filters?.sortField ?? 'published_at'));
    let sortDirection = $state<'asc' | 'desc'>(untrack(() => filters?.sortDirection ?? 'desc'));
    let page = $state<number>(untrack(() => filters?.page ?? 1));
    let perPage = $state<number>(untrack(() => filters?.perPage ?? 10));
    let historyModal = $state<{ gameId: number | null; gameName: string; open: boolean }>({
        gameId: null,
        gameName: '',
        open: false,
    });
    const reviewStylesObj = useReviewTextStyles();
    const reviewStyles = $derived(
        `max-width: ${reviewStylesObj.maxWidth}; font-size: ${reviewStylesObj.fontSize}; line-height: ${reviewStylesObj.lineHeight}; margin: ${reviewStylesObj.margin};`,
    );

    const filterSync = useUrlSyncedFilters({
        route: untrack(() => route('raters.show', rater.id)),
        only: ['ratings', 'previousRatingCounts', 'filters'],
        getParams: () => ({ page, perPage, showOnlyReviews, showOnlyVisibleGames, sortField, sortDirection }),
    });

    const ratingMeta = $derived(
        ratings
            ? { current_page: ratings.current_page, last_page: ratings.last_page, per_page: ratings.per_page, total: ratings.total }
            : { current_page: 1, last_page: 0, per_page: perPage, total: 0 },
    );

    const openHistory = (gameId: number, gameName: string) => {
        historyModal = { gameId, gameName, open: true };
    };

    const closeHistory = () => {
        historyModal = { ...historyModal, open: false };
    };

    const rows = $derived(
        (ratings?.data ?? []).map((rating): RatingRowData => ({
            id: rating.id,
            score: rating.rating,
            date: rating.published_at,
            review: rating.review,
            eventId: rating.event_id,
            game: {
                id: rating.game.id,
                name: rating.game.name,
                slug: rating.game.slug,
                primaryUrl: rating.game.primary_url,
            },
            previousRatingCount: previousRatingCounts[rating.game.id],
            onOpenHistory: () => openHistory(rating.game.id, rating.game.name),
        })),
    );

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
    <PageHeader title={`${rater.name}'s Ratings`} backHref={route('ratings.index')} backLabel="Back to Ratings" />

    <RatingStatsCard stats={safeStats} heading={`${rater.name}'s Rating Statistics`} />

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
                                    <InformationCircleIcon class="h-4 w-4" />
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

                <RatingFilterBar
                    bind:showOnlyReviews
                    bind:showOnlyVisibleGames
                    bind:sortField
                    bind:sortDirection
                    onFilterChange={() => (page = 1)}
                    embedded
                />
            </div>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            {#if rows.length === 0}
                <div class="p-6 text-gray-500 dark:text-gray-400">No ratings</div>
            {:else}
                {#each rows as row (row.id)}<RatingRow {row} reviewStyle={reviewStyles} />{/each}
            {/if}
        </div>

        <div class="p-4">
            <Pagination
                layout="full"
                meta={ratingMeta}
                onChange={(p) => {
                    page = p;
                }}
                onPerPageChange={(pp) => {
                    perPage = pp;
                    page = 1;
                }}
                loading={filterSync.isLoading}
                label="ratings"
                buildPageUrl={filterSync.buildPageUrl}
            />
        </div>
    </Card>

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

    <RatingHistoryDialog
        open={historyModal.open}
        raterId={rater.id}
        gameId={historyModal.gameId}
        title={historyModal.gameName || 'Rating History'}
        {reviewStyles}
        onClose={closeHistory}
    />
</div>
