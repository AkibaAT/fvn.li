<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import StarIcon from '@/components/icons/Star.svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import Pagination from '@/components/Pagination.svelte';
    import { Link, router, page } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import type { SharedData } from '@/types';

    interface ReviewGame {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string;
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

    const _auth = $derived((page.props as SharedData).auth);
    let isLoading = $state(false);
    let localReviews = $derived(reviews.data);
    let spoilerRevealedIds = $state<Set<number>>(new Set());

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

    {#if localReviews.length === 0}
        <div class="py-12 text-center text-gray-500 dark:text-gray-400">No reviews yet.</div>
    {:else}
        <div class="space-y-4">
            {#each localReviews as review (review.id)}
                <Card variant="outline" padding="sm">
                    <div class="flex items-start gap-4">
                        {#if review.game}
                            <Link href={route('games.show', review.game.slug)} class="shrink-0">
                                {#if review.game.thumb_url}
                                    <img src={review.game.thumb_url} alt={review.game.name} class="h-16 w-16 rounded object-cover" loading="lazy" />
                                {:else}
                                    <div class="flex h-16 w-16 items-center justify-center rounded bg-gray-100 dark:bg-gray-700">
                                        <span class="text-xs text-gray-400">No img</span>
                                    </div>
                                {/if}
                            </Link>
                        {/if}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    {#if review.game}
                                        <Link
                                            href={route('games.show', review.game.slug)}
                                            class="font-medium text-blue-600 hover:underline dark:text-blue-400">{review.game.name}</Link
                                        >
                                    {/if}
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    {#each Array(5) as _, i (i)}
                                        <StarIcon
                                            class="h-4 w-4 {i < review.rating
                                                ? 'fill-yellow-400 text-yellow-400'
                                                : 'fill-gray-300 text-gray-300 dark:fill-gray-600 dark:text-gray-600'}"
                                        />
                                    {/each}
                                </div>
                            </div>
                            {#if review.published_at}
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {new Date(review.published_at).toLocaleDateString('en-US', {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                    })}
                                </div>
                            {/if}
                            {#if review.review && review.is_reviewed}
                                <div class="mt-2">
                                    {#if review.has_spoilers && !spoilerRevealedIds.has(review.id)}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            tone="warning"
                                            size="xs"
                                            onclick={() => {
                                                spoilerRevealedIds = new Set([...spoilerRevealedIds, review.id]);
                                            }}
                                        >
                                            Contains spoilers. Click to reveal.
                                        </Button>
                                    {:else if review.review}
                                        {#if review.has_spoilers}
                                            <span
                                                class="mr-1 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                                                >Spoilers</span
                                            >
                                        {/if}
                                        <div class="prose prose-sm max-w-none text-gray-600 dark:text-gray-300 dark:prose-invert">
                                            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                            {@html review.review}
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    </div>
                </Card>
            {/each}
        </div>
    {/if}

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
