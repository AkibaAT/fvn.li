<script lang="ts">
    import { untrack } from 'svelte';
    import { Link, page } from '@inertiajs/svelte';
    import type { SharedData } from '@/types';
    import http from '@/utils/http';

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

    const auth = $derived(((page as any)?.props as SharedData | undefined)?.auth);
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
            const response = await http.post(route('browser-api.user-reviews.store', { game: review.game.id }), {
                rating: editRating,
                review: editReviewText,
                has_spoilers: editHasSpoilers,
            });
            review = {
                ...review,
                rating: response.data.review.rating,
                review: response.data.review.review,
                has_spoilers: response.data.review.has_spoilers,
                is_reviewed: Boolean(response.data.review.review?.replace(/<[^>]*>/g, '').trim()),
            };
            isEditing = false;
        } catch (err: any) {
            editError = err?.response?.data?.message || 'Failed to update review';
        } finally {
            editIsSubmitting = false;
        }
    }
</script>

<svelte:head>
    <title>{metaTags?.title || `Review by ${authorName}`}</title>
</svelte:head>

<div class="space-y-6">
    <!-- Breadcrumb -->
    <nav class="text-sm text-gray-500 dark:text-gray-400">
        {#if review.game}
            <Link href={route('games.show', review.game.slug)} class="hover:text-blue-600 dark:hover:text-blue-400">
                {review.game.name}
            </Link>
            <span class="mx-2">/</span>
        {/if}
        <span>Review</span>
    </nav>

    <!-- Main review card -->
    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <!-- Game info -->
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

        <!-- Author and rating -->
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                {#if review.user?.avatar}
                    <img src={review.user.avatar} alt="" class="h-8 w-8 rounded-full" />
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
                            {new Date(review.published_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Star rating -->
            <div class="flex items-center gap-1">
                {#each Array(5) as _, i (i)}
                    <svg
                        class="h-6 w-6 {i < review.rating
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
                            <button
                                type="button"
                                onclick={() => (editRating = starValue)}
                                onmouseenter={() => (editHoveredRating = starValue)}
                                onmouseleave={() => (editHoveredRating = 0)}
                                class="focus:outline-none"
                                aria-label="{starValue} star{starValue !== 1 ? 's' : ''}"
                            >
                                <svg
                                    class="h-7 w-7 cursor-pointer transition-colors {isActive
                                        ? 'fill-yellow-400 text-yellow-400'
                                        : 'fill-gray-300 text-gray-300 hover:fill-yellow-200 hover:text-yellow-200 dark:fill-gray-600 dark:text-gray-600'}"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                    />
                                </svg>
                            </button>
                        {/each}
                        {#if editRating > 0}
                            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{editRating}/5</span>
                        {/if}
                    </div>
                </fieldset>
                <div>
                    <label for="edit-review-text" class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Review (optional)</label>
                    <textarea
                        id="edit-review-text"
                        bind:value={editReviewText}
                        rows={6}
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Share your thoughts..."
                    ></textarea>
                </div>
                {#if editReviewText.trim().length > 0}
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" bind:checked={editHasSpoilers} class="rounded border-gray-300 text-blue-600" />
                        <span class="text-sm text-gray-600 dark:text-gray-400">This review contains spoilers</span>
                    </label>
                {/if}
                {#if editError}
                    <div class="text-sm text-red-600 dark:text-red-400">{editError}</div>
                {/if}
                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        disabled={editRating === 0 || editIsSubmitting}
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {editIsSubmitting ? 'Saving...' : 'Update Review'}
                    </button>
                    <button
                        type="button"
                        onclick={() => (isEditing = false)}
                        class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >Cancel</button
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
                        <button
                            onclick={() => (spoilerRevealed = true)}
                            class="flex items-center gap-2 rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-800 transition-colors hover:bg-yellow-100 dark:border-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-200 dark:hover:bg-yellow-900/50"
                        >
                            This review contains spoilers — click to reveal
                        </button>
                    {:else if review.review}
                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                        <div class="prose max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert">{@html review.review}</div>
                    {/if}
                </div>
            {/if}
            {#if !review.review}
                <p class="mt-4 text-gray-500 italic dark:text-gray-400">Rating only — no written review.</p>
            {/if}
            {#if isOwnReview && review.game}
                <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button
                        onclick={() => (isEditing = true)}
                        class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                            />
                        </svg>
                        Edit this review
                    </button>
                </div>
            {/if}
        {/if}
    </div>

    {#if review.game}
        <div class="text-center">
            <Link href="{route('games.show', review.game.slug)}#review-{review.id}" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                View all reviews for {review.game.name}
            </Link>
        </div>
    {/if}
</div>
