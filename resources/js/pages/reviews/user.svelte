<script lang="ts">
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import { Link, router, page } from '@inertiajs/svelte';
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

    const _auth = $derived(($page.props as SharedData).auth);
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

<svelte:head>
    <title>{metaTags?.title || `${reviewUser.name}'s Reviews`}</title>
</svelte:head>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            {#if reviewUser.avatar}
                <img src={reviewUser.avatar} alt="" class="h-10 w-10 rounded-full" />
            {/if}
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{reviewUser.name}'s Reviews</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {stats.reviewed_count} review{stats.reviewed_count !== 1 ? 's' : ''} across {stats.unique_games} game{stats.unique_games !== 1
                        ? 's'
                        : ''}
                    {#if stats.average_rating > 0}
                        &middot; avg {stats.average_rating}/5
                    {/if}
                </p>
            </div>
        </div>
        <Link
            href={route('lists.user-public', reviewUser.id)}
            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
            >View Lists</Link
        >
    </div>

    <!-- Sort controls -->
    <div class="flex gap-2">
        <button
            onclick={() => toggleSort('published_at')}
            class="rounded-md px-3 py-1.5 text-sm transition-colors {filters.sortField === 'published_at'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300'}"
        >
            Date{sortIcon('published_at')}
        </button>
        <button
            onclick={() => toggleSort('rating')}
            class="rounded-md px-3 py-1.5 text-sm transition-colors {filters.sortField === 'rating'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300'}"
        >
            Rating{sortIcon('rating')}
        </button>
    </div>

    <!-- Reviews list -->
    {#if localReviews.length === 0}
        <div class="py-12 text-center text-gray-500 dark:text-gray-400">No reviews yet.</div>
    {:else}
        <div class="space-y-4">
            {#each localReviews as review (review.id)}
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
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
                                        <svg
                                            class="h-4 w-4 {i < review.rating
                                                ? 'fill-yellow-400 text-yellow-400'
                                                : 'fill-gray-300 text-gray-300 dark:fill-gray-600 dark:text-gray-600'}"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                            />
                                        </svg>
                                    {/each}
                                </div>
                            </div>
                            {#if review.published_at}
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {new Date(review.published_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                </div>
                            {/if}
                            {#if review.review && review.is_reviewed}
                                <div class="mt-2">
                                    {#if review.has_spoilers && !spoilerRevealedIds.has(review.id)}
                                        <button
                                            onclick={() => {
                                                spoilerRevealedIds = new Set([...spoilerRevealedIds, review.id]);
                                            }}
                                            class="flex items-center gap-2 rounded-md border border-yellow-300 bg-yellow-50 px-2 py-1.5 text-xs text-yellow-800 dark:border-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-200"
                                        >
                                            Contains spoilers — click to reveal
                                        </button>
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
                </div>
            {/each}
        </div>
    {/if}

    <AdvancedPagination
        meta={{
            current_page: reviews.current_page,
            last_page: reviews.last_page,
            total: reviews.total,
            from: reviews.data.length ? (reviews.current_page - 1) * reviews.per_page + 1 : 0,
            to: reviews.data.length ? (reviews.current_page - 1) * reviews.per_page + reviews.data.length : 0,
            per_page: reviews.per_page,
        }}
        onPageChange={handlePageChange}
        onPerPageChange={handlePerPageChange}
        {isLoading}
        label="reviews"
        perPageOptions={[10, 25, 50]}
        {buildPageUrl}
    />
</div>
