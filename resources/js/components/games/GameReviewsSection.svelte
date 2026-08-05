<script lang="ts">
    import CheckIcon from '@/components/icons/Check.svelte';
    import EyeSlashIcon from '@/components/icons/EyeSlash.svelte';
    import ExternalLinkIcon from '@/components/icons/ExternalLink.svelte';
    import FlagIcon from '@/components/icons/Flag.svelte';
    import LinkIcon from '@/components/icons/Link.svelte';
    import StarIcon from '@/components/icons/Star.svelte';
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import Pagination from '@/components/Pagination.svelte';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import UserReviewForm from '@/components/games/UserReviewForm.svelte';
    import { Button, Card, PlatformIcon } from '@/components/ui';
    import { fetchReviews } from '@/api';
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
                size="sm"
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
            <LoadingSpinner size="lg" label="Loading reviews" />
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
                                    <StarIcon class="h-5 w-5 fill-current" />
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
                                    <ExternalLinkIcon class="h-4 w-4" />
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
                                        <CheckIcon class="h-4 w-4 text-green-500" />
                                    {:else}
                                        <LinkIcon class="h-4 w-4" />
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
                                <FlagIcon class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    {#if review.review && (!showAllRatings || review.is_reviewed)}
                        {#if review.has_spoilers && !revealedSpoilers[review.id]}
                            <Button
                                type="button"
                                variant="outline"
                                tone="warning"
                                size="sm"
                                onclick={() => onRevealSpoilers(review.id)}
                            >
                                <EyeSlashIcon class="h-4 w-4" />
                                This review contains spoilers. Click to reveal.
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
        <Pagination layout="full" meta={pagination} onChange={onPageChange} {onPerPageChange} loading={isReviewsLoading} label="reviews" />
    </div>
</Card>
