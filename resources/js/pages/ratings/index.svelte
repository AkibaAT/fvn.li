<script lang="ts">
    import { untrack } from 'svelte';
    import Pagination from '@/components/Pagination.svelte';
    import RatingFilterBar from '@/components/ratings/RatingFilterBar.svelte';
    import RatingRow from '@/components/ratings/RatingRow.svelte';
    import RatingStatsCard from '@/components/ratings/RatingStatsCard.svelte';
    import { emptyStats, type GlobalStats, type RatingRowData } from '@/components/ratings/types';
    import { Card } from '@/components/ui';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import type { MetaTags } from '@/types/meta-tags';
    import ReviewTextControls, { useReviewTextStyles } from '@/components/ReviewTextControls.svelte';
    import { useUrlSyncedFilters } from '@/hooks/useUrlSyncedFilters.svelte';

    type SourceRating = {
        id: number;
        game: { id: number; name: string; slug: string; primary_url?: string | null };
        rater: { id: number; name: string; external_platform?: string };
        score: number;
        created_at: string;
        review?: string | null;
    };

    type Props = {
        pageTitle?: string;
        stats?: GlobalStats;
        ratings?: {
            data: SourceRating[];
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

    let { pageTitle = 'Ratings', stats, ratings, filters, metaTags }: Props = $props();

    const safeStats = $derived(stats ?? emptyStats());
    let page = $state(untrack(() => filters?.page ?? 1));
    let perPage = $state(untrack(() => filters?.perPage ?? ratings?.per_page ?? 10));
    let showOnlyReviews = $state(untrack(() => filters?.showOnlyReviews ?? true));
    let showOnlyVisibleGames = $state(untrack(() => filters?.showOnlyVisibleGames ?? true));
    let platform = $state(untrack(() => filters?.platform ?? ''));
    let stars = $state<number | ''>(untrack(() => filters?.stars ?? ''));
    let sortField = $state<'published_at' | 'rating'>(untrack(() => filters?.sortField ?? 'published_at'));
    let sortDirection = $state<'asc' | 'desc'>(untrack(() => filters?.sortDirection ?? 'desc'));

    const filterSync = useUrlSyncedFilters({
        route: route('ratings.index'),
        only: ['ratings', 'filters'],
        getParams: () => ({ page, perPage, showOnlyReviews, showOnlyVisibleGames, platform, stars, sortField, sortDirection }),
    });

    const reviewStylesObj = useReviewTextStyles();
    const reviewStyle = $derived(
        `max-width: ${reviewStylesObj.maxWidth}; font-size: ${reviewStylesObj.fontSize}; line-height: ${reviewStylesObj.lineHeight}; margin: ${reviewStylesObj.margin};`,
    );
    const ratingMeta = $derived({
        current_page: ratings?.current_page ?? page,
        last_page: ratings?.last_page ?? 0,
        per_page: ratings?.per_page ?? perPage,
        total: ratings?.total ?? 0,
    });
    const rows = $derived(
        (ratings?.data ?? []).map((rating): RatingRowData => ({
            id: rating.id,
            score: rating.score,
            date: rating.created_at,
            review: rating.review,
            game: {
                id: rating.game.id,
                name: rating.game.name,
                slug: rating.game.slug,
                primaryUrl: rating.game.primary_url,
            },
            rater: {
                id: rating.rater.id,
                name: rating.rater.name,
                externalPlatform: rating.rater.external_platform,
            },
        })),
    );
</script>

<SeoHead {metaTags} />
<div class="space-y-4">
    <PageHeader title={pageTitle} class="mb-6" />
    <RatingStatsCard stats={safeStats} scope="visible_games" heading="Global Rating Statistics" />
    <ReviewTextControls />
    <RatingFilterBar
        bind:showOnlyReviews
        bind:showOnlyVisibleGames
        bind:platform
        bind:stars
        bind:sortField
        bind:sortDirection
        showPlatform
        showStars
        onFilterChange={() => (page = 1)}
    />

    <Card padding="none" class="shadow">
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            {#if rows.length === 0}
                <div class="p-6 text-gray-500 dark:text-gray-400">No ratings yet</div>
            {:else}
                {#each rows as row (row.id)}<RatingRow {row} {reviewStyle} showRater />{/each}
            {/if}
        </div>
        <div class="p-4">
            <Pagination
                layout="full"
                meta={ratingMeta}
                onChange={(nextPage) => (page = nextPage)}
                onPerPageChange={(nextPerPage) => {
                    perPage = nextPerPage;
                    page = 1;
                }}
                loading={filterSync.isLoading}
                label="ratings"
                perPageOptions={[10, 25, 50, 100]}
                buildPageUrl={filterSync.buildPageUrl}
            />
        </div>
    </Card>
</div>
