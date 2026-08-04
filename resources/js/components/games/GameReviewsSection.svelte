<script lang="ts">
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import UserReviewForm from '@/components/games/UserReviewForm.svelte';
    import { Button, Card, PlatformIcon } from '@/components/ui';
    import { fetchReviews } from '@/hooks/api';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { shouldCollapseReview } from '@/utils/game-show';
    import type { PaginationMeta, Review } from '@/types/game-show';

    let {
        reviews,
        gameId,
        initialUserReview,
        isAuthenticated,
        availableRatings,
        selectedRating,
        showAllRatings,
        reviewsLoading,
        copiedReviewId,
        expandedReviews,
        revealedSpoilers,
        reviewStyles,
        pagination,
        onToggleRatingsView,
        onRatingFilterChange,
        onCopyReviewLink,
        onReportReview,
        onRevealSpoilers,
        onToggleReviewExpanded,
        onPageChange,
        onPerPageChange,
    }: {
        reviews: Review[];
        gameId: number;
        initialUserReview?: {
            id: number;
            rating: number;
            review: string;
            has_spoilers: boolean;
            published_at: string;
            updated_at: string;
        } | null;
        isAuthenticated: boolean;
        availableRatings: number[];
        selectedRating: number | null;
        showAllRatings: boolean;
        reviewsLoading: boolean;
        copiedReviewId: number | null;
        expandedReviews: Record<number, boolean>;
        revealedSpoilers: Record<number, boolean>;
        reviewStyles: string;
        pagination: PaginationMeta;
        onToggleRatingsView: () => void;
        onRatingFilterChange: (rating: number | null) => void;
        onCopyReviewLink: (reviewId: number) => void;
        onReportReview: (reviewId: number, reviewerName: string) => void;
        onRevealSpoilers: (reviewId: number) => void;
        onToggleReviewExpanded: (reviewId: number) => void;
        onPageChange: (page: number) => void;
        onPerPageChange: (perPage: number) => void;
    } = $props();

    let reviewForm = $state<{ startEditing: () => void } | null>(null);
    let reviewFormEditing = $state(false);
    let hasUserReview = $state(untrack(() => Boolean(initialUserReview)));
    let reviewRefreshLoading = $state(false);
    const isReviewsLoading = $derived(reviewsLoading || reviewRefreshLoading);

    const getReviewAuthorHref = (review: Review) => (review.user ? route('users.reviews', review.user.id) : route('raters.show', review.rater.id));

    async function handleReviewChange(hasReview: boolean) {
        hasUserReview = hasReview;
        reviewRefreshLoading = true;
        try {
            const refreshed = await fetchReviews(gameId, {
                showAllRatings,
                selectedRating,
                page: pagination.current_page,
                perPage: pagination.per_page,
            });
            reviews = refreshed.reviews;
            availableRatings = refreshed.availableRatings;
            pagination = refreshed.pagination;
        } catch {
            // Keep the current list if the refresh fails; the saved review remains visible above it.
        } finally {
            reviewRefreshLoading = false;
        }
    }
</script>

<Card id="reviews" padding="lg" class="mb-6">
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Reviews</h2>
            {#if availableRatings.length > 0}
                <select
                    value={selectedRating || ''}
                    onchange={(event) =>
                        onRatingFilterChange((event.target as HTMLSelectElement).value ? Number((event.target as HTMLSelectElement).value) : null)}
                    class="rounded border border-gray-200 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    disabled={isReviewsLoading}
                >
                    <option value="">Any Stars</option>
                    {#each availableRatings as rating (rating)}
                        <option value={rating}>{rating} Stars</option>
                    {/each}
                </select>
            {/if}
        </div>
        <div class="flex flex-wrap items-center justify-end gap-4">
            {#if isAuthenticated && !hasUserReview && !reviewFormEditing}
                <Button type="button" variant="link" tone="primary" onclick={() => reviewForm?.startEditing()} class="text-sm">Write a review</Button>
            {/if}
            <Button
                type="button"
                variant="link"
                tone="primary"
                onclick={onToggleRatingsView}
                disabled={isReviewsLoading}
                loading={isReviewsLoading}
                class="text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 dark:text-blue-400"
            >
                {isReviewsLoading ? 'Loading...' : `Show ${showAllRatings ? 'reviews only' : 'all ratings'}`}
            </Button>
        </div>
    </div>

    <div class:mb-4={!isAuthenticated || hasUserReview || reviewFormEditing}>
        <UserReviewForm
            bind:this={reviewForm}
            {gameId}
            initialReview={initialUserReview}
            onEditingChange={(editing) => (reviewFormEditing = editing)}
            onReviewChange={handleReviewChange}
        />
    </div>

    {#if isReviewsLoading}
        <div class="flex items-center justify-center py-8">
            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600 dark:text-gray-400">Loading reviews...</span>
        </div>
    {:else if reviews.length === 0}
        <div class="py-8 text-center text-gray-500 dark:text-gray-400">
            No {showAllRatings ? 'ratings' : 'reviews'} found{selectedRating ? ` with ${selectedRating} star${selectedRating !== 1 ? 's' : ''}` : ''}.
        </div>
    {:else}
        <div class="space-y-6">
            {#each reviews as review (review.id)}
                <div id="review-{review.id}" class="border-b border-gray-200 pb-6 last:border-0 dark:border-gray-700">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                <Link href={getReviewAuthorHref(review)} class="flex items-center gap-1 hover:underline">
                                    {#if review.user?.avatar}
                                        <img src={review.user.avatar} alt="" aria-hidden="true" class="h-5 w-5 rounded-full" />
                                    {/if}
                                    {review.user?.name || review.rater.name}
                                    {#if review.user}
                                        <span
                                            class="ml-1 rounded bg-blue-100 px-1 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                                        >
                                            FVN.li
                                        </span>
                                    {/if}
                                </Link>
                            </span>
                            {#if !review.user && review.rater.external_platform}
                                <PlatformIcon platform={review.rater.external_platform} />
                            {/if}
                            <span class="text-sm text-gray-500 dark:text-gray-400">{formatLocalDate(review.published_at)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1 text-yellow-400">
                                {#each Array.from({ length: review.rating }) as _, index (index)}
                                    <svg class="h-5 w-5 fill-current" viewBox="0 0 20 20">
                                        <path
                                            fill-rule="evenodd"
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                        />
                                    </svg>
                                {/each}
                            </div>
                            {#if review.event_id}
                                <a
                                    href={`https://itch.io/event/${review.event_id}`}
                                    target="_blank"
                                    rel="noopener"
                                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                    title="View on itch.io"
                                >
                                    <i class="icon-external-link h-4 w-4"></i>
                                </a>
                            {/if}
                            {#if review.user}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    tone="primary"
                                    size="icon-sm"
                                    onclick={() => onCopyReviewLink(review.id)}
                                    class="text-gray-400 hover:text-blue-500 dark:text-gray-500 dark:hover:text-blue-400"
                                    title={copiedReviewId === review.id ? 'Link copied!' : 'Copy link to review'}
                                >
                                    {#if copiedReviewId === review.id}
                                        <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    {:else}
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                            />
                                        </svg>
                                    {/if}
                                </Button>
                            {/if}
                            <Button
                                type="button"
                                variant="ghost"
                                tone="danger"
                                size="icon-sm"
                                onclick={() => onReportReview(review.id, review.user?.name || review.rater.name)}
                                class="text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                                title="Report review"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"
                                    />
                                </svg>
                            </Button>
                        </div>
                    </div>

                    {#if review.review && (!showAllRatings || review.is_reviewed)}
                        {#if review.has_spoilers && !revealedSpoilers[review.id]}
                            <Button
                                type="button"
                                variant="outline"
                                tone="warning"
                                onclick={() => onRevealSpoilers(review.id)}
                                class="flex items-center gap-2 rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-800 transition-colors hover:bg-yellow-100 dark:border-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-200 dark:hover:bg-yellow-900/50"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                                    />
                                </svg>
                                This review contains spoilers — click to reveal
                            </Button>
                        {:else}
                            <div>
                                {#if review.has_spoilers}
                                    <span
                                        class="mr-1 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                                    >
                                        Spoilers
                                    </span>
                                {/if}
                                <div
                                    class="relative overflow-hidden transition-[max-height] duration-300 ease-in-out"
                                    style={!expandedReviews[review.id] && shouldCollapseReview(review.review) ? 'max-height: 200px;' : undefined}
                                >
                                    <div class="prose max-w-none text-gray-600 dark:text-gray-300 dark:prose-invert" style={reviewStyles}>
                                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                        {@html review.review}
                                    </div>
                                    {#if !expandedReviews[review.id] && shouldCollapseReview(review.review)}
                                        <div
                                            class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-white dark:from-gray-800"
                                        ></div>
                                    {/if}
                                </div>
                                {#if shouldCollapseReview(review.review)}
                                    <Button
                                        type="button"
                                        variant="link"
                                        tone="primary"
                                        onclick={() => onToggleReviewExpanded(review.id)}
                                        class="mt-1 text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        {expandedReviews[review.id] ? 'Show less' : 'Read more'}
                                    </Button>
                                {/if}
                            </div>
                        {/if}
                    {/if}
                </div>
            {/each}
        </div>
    {/if}

    <div class="mt-4">
        <AdvancedPagination meta={pagination} {onPageChange} {onPerPageChange} isLoading={isReviewsLoading} label="reviews" />
    </div>
</Card>
