<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import ReviewTextControls, { useReviewTextStyles } from '@/components/ReviewTextControls.svelte';
    import RatingRow from '@/components/ratings/RatingRow.svelte';
    import type { RatingRowData } from '@/components/ratings/types';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import Pagination from '@/components/Pagination.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';

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

    let isLoading = $state(false);
    const reviewStylesObj = useReviewTextStyles();
    const reviewStyle = $derived(
        `max-width: ${reviewStylesObj.maxWidth}; font-size: ${reviewStylesObj.fontSize}; line-height: ${reviewStylesObj.lineHeight}; margin: ${reviewStylesObj.margin};`,
    );
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
                          isFvnReview: true,
                          hasSpoilers: review.has_spoilers,
                      }
                    : null,
            )
            .filter((row): row is RatingRowData => row !== null),
    );

    function navigate(params: Record<string, string | number>) {
        isLoading = true;
        router.get(
            route('users.reviews', reviewUser.id),
            { ...filters, ...params },
            { preserveState: true, preserveScroll: true, onFinish: () => (isLoading = false) },
        );
    }

    function handlePageChange(p: number) {
        navigate({ page: p });
    }
    function handlePerPageChange(pp: number) {
        navigate({ perPage: pp, page: 1 });
    }
    function toggleSort(field: string) {
        const newDirection = filters.sortField === field && filters.sortDirection === 'desc' ? 'asc' : 'desc';
        navigate({ sortField: field, sortDirection: newDirection, page: 1 });
    }

    function buildPageUrl(p: number): string {
        const params = new SvelteURLSearchParams();
        params.set('page', p.toString());
        params.set('perPage', reviews.per_page.toString());
        params.set('sortField', filters.sortField);
        params.set('sortDirection', filters.sortDirection);
        return `/users/${reviewUser.id}/reviews?${params.toString()}`;
    }

    function sortIcon(field: string): string {
        if (filters.sortField !== field) return '';
        return filters.sortDirection === 'asc' ? ' \u2191' : ' \u2193';
    }
</script>

<SeoHead {metaTags} title={`${reviewUser.name}'s Reviews`} />

<div class="space-y-6">
    <PageHeader title={`${reviewUser.name}'s Reviews`} class="mb-0">
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
            <Link
                href={route('lists.user-public', reviewUser.id)}
                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                >View Lists</Link
            >
        {/snippet}
    </PageHeader>

    <div class="flex gap-2">
        <Button
            type="button"
            variant={filters.sortField === 'published_at' ? 'solid' : 'soft'}
            tone={filters.sortField === 'published_at' ? 'primary' : 'neutral'}
            onclick={() => toggleSort('published_at')}
            class="rounded-md px-3 py-1.5 text-sm transition-colors {filters.sortField === 'published_at'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300'}"
        >
            Date{sortIcon('published_at')}
        </Button>
        <Button
            type="button"
            variant={filters.sortField === 'rating' ? 'solid' : 'soft'}
            tone={filters.sortField === 'rating' ? 'primary' : 'neutral'}
            onclick={() => toggleSort('rating')}
            class="rounded-md px-3 py-1.5 text-sm transition-colors {filters.sortField === 'rating'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300'}"
        >
            Rating{sortIcon('rating')}
        </Button>
    </div>

    <ReviewTextControls />

    {#if rows.length === 0}
        <div class="py-12 text-center text-gray-500 dark:text-gray-400">No reviews yet.</div>
    {:else}
        <Card padding="none" class="shadow">
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                {#each rows as row (row.id)}<RatingRow {row} {reviewStyle} />{/each}
            </div>
            <div class="p-4">
                <Pagination
                    layout="full"
                    meta={{
                        current_page: reviews.current_page,
                        last_page: reviews.last_page,
                        total: reviews.total,
                        from: reviews.data.length ? (reviews.current_page - 1) * reviews.per_page + 1 : 0,
                        to: reviews.data.length ? (reviews.current_page - 1) * reviews.per_page + reviews.data.length : 0,
                        per_page: reviews.per_page,
                    }}
                    onChange={handlePageChange}
                    onPerPageChange={handlePerPageChange}
                    loading={isLoading}
                    label="reviews"
                    perPageOptions={[10, 25, 50]}
                    {buildPageUrl}
                />
            </div>
        </Card>
    {/if}
</div>
