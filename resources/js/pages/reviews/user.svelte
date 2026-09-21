<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import ReviewTextControls, { useReviewStyleString } from '@/components/ReviewTextControls.svelte';
    import RatingList from '@/components/ratings/RatingList.svelte';
    import type { RatingRowData } from '@/components/ratings/types';
    import { untrack } from 'svelte';
    import { Button } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { useUrlSyncedFilters } from '@/hooks/useUrlSyncedFilters.svelte';
    import { buildPageMeta } from '@/utils/pagination';

    interface ReviewGame {
        id: number;
        name: string;
        slug: string;
    }
    interface Review {
        id: number;
        rating: number;
        review?: string;
        published_at?: string;
        is_reviewed: boolean;
        has_spoilers: boolean;
        source_platform?: string;
        game?: ReviewGame | null;
    }
    interface ReviewUser {
        id: number;
        name: string;
        avatar?: string;
    }
    interface Stats {
        total_ratings: number;
        reviewed_count: number;
        average_rating: number;
        unique_games: number;
    }
    interface Filters {
        sortField: string;
        sortDirection: string;
        page: number;
        perPage: number;
    }

    interface Props {
        reviewUser: ReviewUser;
        reviews: { data: Review[]; current_page: number; last_page: number; per_page: number; total: number };
        stats: Stats;
        filters: Filters;
        metaTags?: { title?: string; description?: string };
    }

    let { reviewUser, reviews, stats, filters, metaTags }: Props = $props();

    let page = $state(untrack(() => filters.page ?? 1));
    let perPage = $state(untrack(() => filters.perPage ?? reviews.per_page ?? 10));
    let sortField = $state(untrack(() => filters.sortField ?? 'published_at'));
    let sortDirection = $state(untrack(() => filters.sortDirection ?? 'desc'));

    const filterSync = useUrlSyncedFilters({
        route: untrack(() => route('users.reviews', reviewUser.id)),
        only: ['reviewUser', 'reviews', 'stats', 'filters', 'metaTags'],
        getParams: () => ({ page, perPage, sortField, sortDirection }),
    });

    const reviewStyle = useReviewStyleString();
    const rows = $derived(
        reviews.data
            .map((review): RatingRowData | null =>
                review.game
                    ? {
                          id: review.id,
                          score: review.rating,
                          date: review.published_at,
                          review: review.is_reviewed ? review.review : null,
                          game: {
                              id: review.game.id,
                              name: review.game.name,
                              slug: review.game.slug,
                              primaryUrl: null,
                          },
                          isFvnReview: review.source_platform === 'fvn_li',
                          sourcePlatform: review.source_platform,
                          hasSpoilers: review.has_spoilers,
                      }
                    : null,
            )
            .filter((row): row is RatingRowData => row !== null),
    );

    const reviewsMeta = $derived(buildPageMeta(reviews, reviews.data));

    function handlePageChange(p: number) {
        page = p;
    }
    function handlePerPageChange(pp: number) {
        perPage = pp;
        page = 1;
    }
    function toggleSort(field: string) {
        const newDirection = sortField === field && sortDirection === 'desc' ? 'asc' : 'desc';
        sortField = field;
        sortDirection = newDirection;
        page = 1;
    }

    function sortIcon(field: string): string {
        if (filters.sortField !== field) return '';
        return filters.sortDirection === 'asc' ? ' \u2191' : ' \u2193';
    }
</script>

<SeoHead {metaTags} title={`${reviewUser.name}'s Reviews`} />

<div class="space-y-6">
    <PageHeader title={`${reviewUser.name}'s Reviews`}>
        {#snippet leading()}
            {#if reviewUser.avatar}
                <img src={reviewUser.avatar} alt="" aria-hidden="true" class="h-10 w-10 rounded-full" />
            {/if}
        {/snippet}
        {#snippet metadata()}
            <span>
                {stats.reviewed_count} review{stats.reviewed_count !== 1 ? 's' : ''} across {stats.unique_games} game{stats.unique_games !== 1
                    ? 's'
                    : ''}
                {#if stats.average_rating > 0}
                    &middot; avg {stats.average_rating}/5
                {/if}
            </span>
        {/snippet}
        {#snippet actions()}
            <Button href={route('lists.user-public', reviewUser.id)} variant="solid" tone="primary">View Lists</Button>
        {/snippet}
    </PageHeader>

    <div class="flex items-center gap-2">
        <Button
            type="button"
            variant={filters.sortField === 'published_at' ? 'solid' : 'soft'}
            tone={filters.sortField === 'published_at' ? 'primary' : 'neutral'}
            onclick={() => toggleSort('published_at')}
            size="sm"
        >
            Date{sortIcon('published_at')}
        </Button>
        <Button
            type="button"
            variant={filters.sortField === 'rating' ? 'solid' : 'soft'}
            tone={filters.sortField === 'rating' ? 'primary' : 'neutral'}
            onclick={() => toggleSort('rating')}
            size="sm"
        >
            Rating{sortIcon('rating')}
        </Button>
        {#if rows.length > 0}
            <ReviewTextControls class="ml-auto" />
        {/if}
    </div>

    {#if rows.length === 0}
        <div class="py-12 text-center text-fg-muted">No reviews yet.</div>
    {:else}
        <RatingList
            {rows}
            reviewStyle={reviewStyle.css}
            meta={reviewsMeta}
            onChange={handlePageChange}
            onPerPageChange={handlePerPageChange}
            loading={filterSync.isLoading}
            label="reviews"
            perPageOptions={[10, 25, 50]}
            buildPageUrl={filterSync.buildPageUrl}
        />
    {/if}
</div>
