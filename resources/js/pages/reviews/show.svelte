<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import PencilIcon from '@/components/icons/Pencil.svelte';
    import StarIcon from '@/components/icons/Star.svelte';
    import { untrack } from 'svelte';
    import { Link, page } from '@inertiajs/svelte';
    import { Button, Card, Checkbox, Textarea } from '@/components/ui';
    import type { SharedData } from '@/types';
    import { submitUserReview } from '@/api/user-reviews';
    import PageHeader from '@/components/layout/PageHeader.svelte';

    interface ReviewGame {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string;
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

<div class="space-y-6">
    <PageHeader
        title={`Review by ${authorName}`}
        backHref={review.game ? route('games.show', review.game.slug) : route('ratings.index')}
        backLabel={review.game ? `Back to ${review.game.name}` : 'Back to Ratings'}
        class="mb-0"
    />

    <Card variant="outline" padding="lg">
        {#if review.game}
            <Link
                href={route('games.show', review.game.slug)}
                class="mb-4 flex items-center gap-3 rounded-lg bg-gray-50 p-3 transition-colors hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
            >
                {#if review.game.thumb_url}
                    <img src={review.game.thumb_url} alt={review.game.name} class="h-12 w-12 rounded object-cover" />
                {/if}
                <div>
                    <div class="font-medium text-gray-900 dark:text-gray-100">{review.game.name}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">View game page</div>
                </div>
            </Link>
        {/if}

        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                {#if review.user?.avatar}
                    <img src={review.user.avatar} alt="" aria-hidden="true" class="h-8 w-8 rounded-full" />
                {/if}
                <div>
                    <div class="flex items-center gap-2">
                        {#if isUserReview && review.user}
                            <Link
                                href={route('users.reviews', review.user.id)}
                                class="font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400"
                            >
                                {authorName}
                            </Link>
                        {:else if review.rater}
                            <Link
                                href={route('raters.show', review.rater.id)}
                                class="font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400"
                            >
                                {authorName}
                            </Link>
                        {:else}
                            <span class="font-medium text-gray-900 dark:text-gray-100">{authorName}</span>
                        {/if}
                        {#if isUserReview}
                            <span class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                                >FVN.li</span
                            >
                        {/if}
                    </div>
                    {#if review.published_at}
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {new Date(review.published_at).toLocaleDateString('en-US', {
                                month: 'long',
                                day: 'numeric',
                                year: 'numeric',
                            })}
                        </div>
                    {/if}
                </div>
            </div>

            <div class="flex items-center gap-1">
                {#each Array(5) as _, i (i)}
                    <StarIcon
                        class="h-6 w-6 {i < review.rating
                            ? 'fill-yellow-400 text-yellow-400'
                            : 'fill-gray-300 text-gray-300 dark:fill-gray-600 dark:text-gray-600'}"
                    />
                {/each}
                <span class="ml-1 text-lg font-medium text-gray-700 dark:text-gray-300">{review.rating}/5</span>
            </div>
        </div>

        {#if isEditing && review.game}
            <form onsubmit={handleEditSubmit} class="mt-4 space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
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
                    <Textarea
                        id="edit-review-text"
                        bind:value={editReviewText}
                        rows={6}
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Share your thoughts..."
                    />
                </div>
                {#if editReviewText.trim().length > 0}
                    <Checkbox bind:checked={editHasSpoilers} label="This review contains spoilers" />
                {/if}
                {#if editError}
                    <div class="text-sm text-red-600 dark:text-red-400">{editError}</div>
                {/if}
                <div class="flex items-center gap-2">
                    <Button type="submit" variant="solid" tone="primary" disabled={editRating === 0 || editIsSubmitting} loading={editIsSubmitting}>
                        {editIsSubmitting ? 'Saving...' : 'Update Review'}
                    </Button>
                    <Button
                        type="button"
                        variant="soft"
                        tone="neutral"
                        size="sm"
                        onclick={() => (isEditing = false)}>Cancel</Button
                    >
                </div>
            </form>
        {:else}
            {#if review.review && review.is_reviewed}
                <div class="mt-4">
                    {#if review.has_spoilers}
                        <span
                            class="mr-1 mb-2 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                            >Spoilers</span
                        >
                    {/if}
                    {#if review.has_spoilers && !spoilerRevealed}
                        <Button
                            type="button"
                            variant="outline"
                            tone="warning"
                            size="sm"
                            onclick={() => (spoilerRevealed = true)}
                        >
                            This review contains spoilers. Click to reveal.
                        </Button>
                    {:else if review.review}
                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                        <div class="prose max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert">{@html review.review}</div>
                    {/if}
                </div>
            {/if}
            {#if !review.review}
                <p class="mt-4 text-gray-500 italic dark:text-gray-400">Rating only, no written review.</p>
            {/if}
            {#if isOwnReview && review.game}
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <Button
                        type="button"
                        variant="link"
                        tone="primary"
                        size="sm"
                        onclick={() => (isEditing = true)}
                        class="gap-1.5"
                    >
                        <PencilIcon class="h-4 w-4" />
                        Edit this review
                    </Button>
                </div>
            {/if}
        {/if}
    </Card>

    {#if review.game}
        <div class="text-center">
            <Link href="{route('games.show', review.game.slug)}#review-{review.id}" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                View all reviews for {review.game.name}
            </Link>
        </div>
    {/if}
</div>
