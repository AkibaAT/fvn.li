<script lang="ts">
    import InformationCircleIcon from '@/components/icons/InformationCircle.svelte';
    import type { RatingHistoryEntry } from '@/api';
    import { untrack } from 'svelte';
    import RatingHistoryDialog from '@/components/RatingHistoryDialog.svelte';
    import RatingFilterBar from '@/components/ratings/RatingFilterBar.svelte';
    import RatingList from '@/components/ratings/RatingList.svelte';
    import RatingStatsCard from '@/components/ratings/RatingStatsCard.svelte';
    import { emptyStats, type GlobalStats, type RatingRowData } from '@/components/ratings/types';
    import { Button, Card, Dialog } from '@/components/ui';
    import { alertToneClasses } from '@/components/ui/Alert.svelte';
    import { Link } from '@inertiajs/svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/types/meta-tags';
    import ReviewTextControls, { useReviewStyleString } from '@/components/ReviewTextControls.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { useUrlSyncedFilters } from '@/hooks/useUrlSyncedFilters.svelte';
    import { buildPageMeta } from '@/utils/pagination';

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
    const reviewStyle = useReviewStyleString();

    const filterSync = useUrlSyncedFilters({
        route: untrack(() => route('raters.show', rater.id)),
        only: ['ratings', 'previousRatingCounts', 'filters'],
        getParams: () => ({ page, perPage, showOnlyReviews, showOnlyVisibleGames, sortField, sortDirection }),
    });

    const ratingMeta = $derived(ratings ? buildPageMeta(ratings) : { current_page: 1, last_page: 0, per_page: perPage, total: 0 });

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

    const toneForAvg = (avg: number) => alertToneClasses[avg >= 4 ? 'success' : avg >= 3 ? 'neutral' : 'danger'];

    function closePhrasesDialog() {
        showContext = false;
    }
</script>

<SeoHead {metaTags} />
<div class="space-y-6">
    <PageHeader title={`${rater.name}'s Ratings`} backHref={route('ratings.index')} backLabel="Back to Ratings" />

    <RatingStatsCard stats={safeStats} heading={`${rater.name}'s Rating Statistics`} />

    <Card variant="flat" padding="lg">
        <h2 class="mb-4 text-title font-semibold text-fg">Common Phrases in Reviews</h2>
        <div class="mt-4">
            {#if Object.keys(safePhrases).length === 0}
                <div class="text-fg-muted">No common phrases found</div>
            {:else}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {#each Object.entries(safePhrases) as [phrase, data] (phrase)}
                        {@const tone = toneForAvg(data.avg_rating)}
                        <div class="flex items-center justify-between rounded-md border p-2 {tone.box} {tone.title}">
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
            <div class="mt-4 flex gap-4 text-sm text-fg-muted">
                <div><span class="mr-1 inline-block h-3 w-3 rounded-sm border {alertToneClasses.success.box}"></span>Positive context (4-5★)</div>
                <div><span class="mr-1 inline-block h-3 w-3 rounded-sm border {alertToneClasses.neutral.box}"></span>Neutral context (3★)</div>
                <div><span class="mr-1 inline-block h-3 w-3 rounded-sm border {alertToneClasses.danger.box}"></span>Negative context (1-2★)</div>
            </div>
        {/if}
    </Card>

    <RatingList
        {rows}
        reviewStyle={reviewStyle.css}
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
        class="overflow-hidden"
    >
        {#snippet header()}
            <div class="border-b border-border p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-title font-semibold text-fg">Rating History</h2>
                        <div class="mt-1 flex items-center gap-1 text-sm text-fg-faint">
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
                    >
                        {#snippet actions()}<ReviewTextControls />{/snippet}
                    </RatingFilterBar>
                </div>
            </div>
        {/snippet}
        {#snippet empty()}
            <div class="p-6 text-fg-muted">No ratings</div>
        {/snippet}
    </RatingList>

    <Dialog
        open={showContext && !!selectedPhrase && !!safePhrases[selectedPhrase]}
        onClose={closePhrasesDialog}
        title={selectedPhrase ?? 'Phrase Contexts'}
        size="xl"
    >
        {#if selectedPhrase && safePhrases[selectedPhrase]}
            <div class="mb-4 text-sm text-fg-muted">
                {safePhrases[selectedPhrase].count}x / {safePhrases[selectedPhrase].avg_rating.toFixed(1)}★
            </div>
            <div class="max-h-96 space-y-4 overflow-y-auto">
                {#each Object.entries(safePhrases[selectedPhrase].contexts) as [gameName, context] (gameName)}
                    <div>
                        <h4 class="mb-2 font-medium text-fg">
                            <Link href={route('games.show', { game: context.slug })} class="text-fg hover:underline">{gameName}</Link>
                            <span class="font-normal text-fg-faint">({context.rating}★)</span>
                        </h4>
                        <div class="space-y-2">
                            {#each context.sentences as sentence, _index (_index)}
                                <div class="rounded-md bg-surface-alt p-2 text-sm">
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
        reviewStyles={reviewStyle.css}
        onClose={closeHistory}
    />
</div>
