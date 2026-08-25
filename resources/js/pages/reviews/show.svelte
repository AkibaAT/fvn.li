<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import TinyMCEEditor from '@/components/editor/TinyMCEEditor.svelte';
    import ChevronLeftIcon from '@/components/icons/ChevronLeft.svelte';
    import PencilIcon from '@/components/icons/Pencil.svelte';
    import StarIcon from '@/components/icons/Star.svelte';
    import ReviewTextControls, { useReviewTextStyles } from '@/components/ReviewTextControls.svelte';
    import { untrack } from 'svelte';
    import { Link, page } from '@inertiajs/svelte';
    import { Button, Card, Checkbox, PlatformIcon, Stars } from '@/components/ui';
    import type { SharedData } from '@/types';
    import { submitUserReview } from '@/api/user-reviews';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { formatLocalDate } from '@/utils/date-formatting';

    interface ReviewGame {
        id: number;
        name: string;
        slug: string;
    }
    interface ReviewUser {
        id: number;
        name: string;
        avatar?: string;
    }
    interface ReviewRater {
        id: number;
        name: string;
        external_platform?: string;
    }
    interface Review {
        id: number;
        rating: number;
        review?: string;
        published_at?: string;
        is_reviewed: boolean;
        has_spoilers: boolean;
        event_id?: string;
        source_platform?: string;
        game?: ReviewGame | null;
        user?: ReviewUser | null;
        rater?: ReviewRater | null;
    }

    interface Props {
        review: Review;
        metaTags?: { title?: string; description?: string };
    }

    let { review: initialReview, metaTags }: Props = $props();

    const auth = $derived((page.props as SharedData).auth);
    const currentUserId = $derived(auth?.user?.id ?? null);

    let review = $state(untrack(() => initialReview));
    let isEditing = $state(false);
    let spoilerRevealed = $state(false);

    // Inline editor state
    let editRating = $state(untrack(() => review.rating));
    let editHoveredRating = $state(0);
    let editReviewText = $state(untrack(() => review.review ?? ''));
    let editHasSpoilers = $state(untrack(() => review.has_spoilers));
    let editIsSubmitting = $state(false);
    let editError = $state<string | null>(null);

    const authorName = $derived(review.user?.name ?? review.rater?.name ?? 'Unknown');
    const isUserReview = $derived(Boolean(review.user));
    const isOwnReview = $derived(isUserReview && currentUserId === review.user?.id);
    const reviewStyles = useReviewTextStyles();
    const reviewStyle = $derived(
        `max-width: ${reviewStyles.maxWidth}; font-size: ${reviewStyles.fontSize}; line-height: ${reviewStyles.lineHeight}; margin: ${reviewStyles.margin};`,
    );

    async function handleEditSubmit(e: Event) {
        e.preventDefault();
        if (editRating === 0 || !review.game) return;
        editIsSubmitting = true;
        editError = null;
        try {
            const { review: savedReview } = await submitUserReview(review.game.id, {
                rating: editRating,
                review: editReviewText,
                has_spoilers: editHasSpoilers,
            });
            review = {
                ...review,
                rating: savedReview.rating,
                review: savedReview.review,
                has_spoilers: savedReview.has_spoilers,
                is_reviewed: Boolean(savedReview.review?.replace(/<[^>]*>/g, '').trim()),
            };
            isEditing = false;
        } catch (err) {
            editError = err instanceof Error ? err.message : 'Failed to update review';
        } finally {
            editIsSubmitting = false;
        }
    }
</script>

<SeoHead {metaTags} title={`Review by ${authorName}`} />

<div class="sticky top-[4.5rem] z-40 mb-5 flex border-b border-gray-200 bg-gray-100 px-4 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <Link
        href={review.game ? route('games.show', review.game.slug) : route('ratings.index')}
        class="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
    >
        <ChevronLeftIcon class="mr-1 h-5 w-5" />
        {review.game ? `Back to ${review.game.name}` : 'Back to Ratings'}
    </Link>
</div>

<div class="space-y-6">
    <Card padding="lg">
        <PageHeader title={review.game ? `Review of ${review.game.name}` : `Review by ${authorName}`} class="mb-0">
            {#snippet metadata()}
                <div class="flex flex-wrap items-center gap-2">
                    {#if review.user?.avatar}
                        <img src={review.user.avatar} alt="" aria-hidden="true" class="h-5 w-5 rounded-full" />
                    {/if}
                    <span>Review by</span>
                    {#if isUserReview && review.user}
                        <Link href={route('users.reviews', review.user.id)} class="font-medium text-gray-800 hover:underline dark:text-gray-100">
                            {authorName}
                        </Link>
                    {:else if review.rater}
                        <Link href={route('raters.show', review.rater.id)} class="font-medium text-gray-800 hover:underline dark:text-gray-100">
                            {authorName}
                        </Link>
                    {:else}
                        <span class="font-medium text-gray-800 dark:text-gray-100">{authorName}</span>
                    {/if}
                    {#if isUserReview}
                        <span class="rounded bg-blue-100 px-1 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                            >FVN.li</span
                        >
                    {:else if review.rater?.external_platform}
                        <PlatformIcon platform={review.rater.external_platform} />
                    {/if}
                    {#if review.published_at}
                        <span aria-hidden="true">&middot;</span>
                        <time datetime={review.published_at}>{formatLocalDate(review.published_at)}</time>
                    {/if}
                </div>
            {/snippet}
            {#snippet actions()}
                <div class="flex items-center gap-2">
                    <Stars rating={review.rating} />
                    <span class="font-semibold text-gray-700 dark:text-gray-300">{review.rating}/5</span>
                </div>
                {#if isOwnReview && review.game && !isEditing}
                    <Button type="button" variant="soft" tone="primary" size="sm" onclick={() => (isEditing = true)} class="gap-1.5">
                        <PencilIcon class="h-4 w-4" />
                        Edit review
                    </Button>
                {/if}
            {/snippet}
        </PageHeader>
    </Card>

    {#if review.review && review.is_reviewed && !isEditing}
        <ReviewTextControls />
    {/if}

    <Card padding="lg">
        {#if isEditing && review.game}
            <form onsubmit={handleEditSubmit} class="space-y-4">
                <fieldset>
                    <legend class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Rating *</legend>
                    <div class="flex items-center gap-1">
                        {#each Array(5) as _, i (i)}
                            {@const starValue = i + 1}
                            {@const isActive = starValue <= (editHoveredRating || editRating)}
                            <Button
                                type="button"
                                variant="ghost"
                                tone="warning"
                                size="icon-md"
                                onclick={() => (editRating = starValue)}
                                onmouseenter={() => (editHoveredRating = starValue)}
                                onmouseleave={() => (editHoveredRating = 0)}
                                class="focus:outline-none"
                                ariaLabel="{starValue} star{starValue !== 1 ? 's' : ''}"
                            >
                                <StarIcon
                                    class="h-7 w-7 cursor-pointer transition-colors {isActive
                                        ? 'fill-yellow-400 text-yellow-400'
                                        : 'fill-gray-300 text-gray-300 hover:fill-yellow-200 hover:text-yellow-200 dark:fill-gray-600 dark:text-gray-600'}"
                                />
                            </Button>
                        {/each}
                        {#if editRating > 0}
                            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{editRating}/5</span>
                        {/if}
                    </div>
                </fieldset>
                <div>
                    <label for="edit-review-text" class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Review (optional)</label>
                    <TinyMCEEditor
                        id="edit-review-text"
                        ariaLabel="Review (optional)"
                        content={editReviewText}
                        onUpdate={(content) => (editReviewText = content)}
                        placeholder="Share your thoughts..."
                        height={240}
                        disableImages
                        reviewMode
                    />
                </div>
                {#if editReviewText.trim().length > 0}
                    <Checkbox bind:checked={editHasSpoilers} label="Mark the whole review as a spoiler" />
                {/if}
                {#if editError}
                    <div class="text-sm text-red-600 dark:text-red-400">{editError}</div>
                {/if}
                <div class="flex items-center gap-2">
                    <Button type="submit" variant="solid" tone="primary" disabled={editRating === 0 || editIsSubmitting} loading={editIsSubmitting}>
                        {editIsSubmitting ? 'Saving...' : 'Update Review'}
                    </Button>
                    <Button type="button" variant="soft" tone="neutral" size="sm" onclick={() => (isEditing = false)}>Cancel</Button>
                </div>
            </form>
        {:else}
            {#if review.review && review.is_reviewed}
                <div>
                    {#if review.has_spoilers}
                        <span
                            class="mr-1 mb-2 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                            >Spoilers</span
                        >
                    {/if}
                    {#if review.has_spoilers && !spoilerRevealed}
                        <Button type="button" variant="outline" tone="warning" size="sm" onclick={() => (spoilerRevealed = true)}>
                            This review contains spoilers. Click to reveal.
                        </Button>
                    {:else if review.review}
                        <div
                            class="prose max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert"
                            class:fvn-review={isUserReview}
                            style={reviewStyle}
                        >
                            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                            {@html review.review}
                        </div>
                    {/if}
                </div>
            {/if}
            {#if !review.review}
                <p class="text-gray-500 italic dark:text-gray-400">Rating only, no written review.</p>
            {/if}
        {/if}
    </Card>
</div>
