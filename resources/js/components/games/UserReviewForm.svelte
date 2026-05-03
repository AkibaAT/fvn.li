<script lang="ts">
    import { untrack } from 'svelte';
    import { page } from '@inertiajs/svelte';

    interface UserReview {
        id: number;
        rating: number;
        review: string;
        has_spoilers: boolean;
        published_at: string;
        updated_at: string;
    }

    interface Props {
        gameId: number;
        gameName: string;
        initialReview?: UserReview | null;
    }

    let { gameId, gameName, initialReview = null }: Props = $props();

    const auth = $derived((page as any)?.props?.auth);
    const isAuthenticated = $derived(Boolean(auth?.user));

    let isEditing = $state(false);
    let rating = $state(untrack(() => initialReview?.rating ?? 0));
    let hoveredRating = $state(0);
    let reviewText = $state(untrack(() => initialReview?.review ?? ''));
    let hasSpoilers = $state(untrack(() => initialReview?.has_spoilers ?? false));
    let userReview = $state<UserReview | null>(untrack(() => initialReview));
    let message = $state<{ type: 'success' | 'error'; text: string } | null>(null);
    let showDeleteConfirm = $state(false);
    let isSubmitting = $state(false);
    let isDeleting = $state(false);

    function showMessageFn(text: string, type: 'success' | 'error') {
        message = { text, type };
        setTimeout(() => (message = null), 5000);
    }

    export function startEditing() {
        handleStartEdit();
    }

    function handleStartEdit() {
        if (userReview) {
            rating = userReview.rating;
            reviewText = userReview.review || '';
            hasSpoilers = userReview.has_spoilers;
        }
        isEditing = true;
    }

    function handleCancel() {
        isEditing = false;
        if (userReview) {
            rating = userReview.rating;
            reviewText = userReview.review || '';
            hasSpoilers = userReview.has_spoilers;
        } else {
            rating = 0;
            reviewText = '';
            hasSpoilers = false;
        }
    }

    async function handleSubmit(e: Event) {
        e.preventDefault();
        if (rating === 0) {
            showMessageFn('Please select a rating', 'error');
            return;
        }

        isSubmitting = true;
        try {
            const response = await (window as any).axios.post(route('browser-api.user-reviews.store', { game: gameId }), {
                rating,
                review: reviewText,
                has_spoilers: hasSpoilers,
            });
            userReview = response.data.review;
            isEditing = false;
            showMessageFn(response.data.message, 'success');
        } catch (error: any) {
            const msg = error?.response?.data?.message || 'Failed to submit review';
            showMessageFn(msg, 'error');
        } finally {
            isSubmitting = false;
        }
    }

    async function handleDelete() {
        isDeleting = true;
        try {
            const response = await (window as any).axios.delete(route('browser-api.user-reviews.destroy', { game: gameId }));
            userReview = null;
            rating = 0;
            reviewText = '';
            hasSpoilers = false;
            isEditing = false;
            showDeleteConfirm = false;
            showMessageFn(response.data.message, 'success');
        } catch (error: any) {
            const msg = error?.response?.data?.message || 'Failed to delete review';
            showMessageFn(msg, 'error');
        } finally {
            isDeleting = false;
        }
    }
</script>

{#if !isAuthenticated}
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-800/50">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            <a href={route('login')} class="text-blue-600 hover:underline dark:text-blue-400">Sign in</a>
            to leave a review for this game.
        </p>
    </div>
{:else if userReview && !isEditing}
    <!-- Existing review display -->
    <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Your Review</span>
                <div class="flex items-center gap-0.5">
                    {#each Array(5) as _, i (i)}
                        <svg
                            class="h-4 w-4 {i < userReview.rating
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
            <div class="flex items-center gap-2">
                <button onclick={handleStartEdit} class="text-sm text-blue-600 hover:underline dark:text-blue-400">Edit</button>
                <button onclick={() => (showDeleteConfirm = true)} class="text-sm text-red-600 hover:underline dark:text-red-400">Delete</button>
            </div>
        </div>

        {#if showDeleteConfirm}
            <div class="mt-3 flex items-center gap-2 rounded border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                <span class="text-sm text-red-800 dark:text-red-200">Delete your review?</span>
                <button
                    onclick={handleDelete}
                    disabled={isDeleting}
                    class="rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700 disabled:opacity-50"
                >
                    {isDeleting ? 'Deleting...' : 'Confirm'}
                </button>
                <button
                    onclick={() => (showDeleteConfirm = false)}
                    class="rounded bg-gray-200 px-2 py-1 text-xs text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300"
                >
                    Cancel
                </button>
            </div>
        {/if}

        {#if message}
            <div class="mt-2 text-sm {message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">
                {message.text}
            </div>
        {/if}
    </div>
{:else if !userReview && !isEditing}
    <!-- Write review prompt -->
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <button
            onclick={handleStartEdit}
            class="flex w-full items-center gap-2 text-sm text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
            </svg>
            Write a review for {gameName}
        </button>
    </div>
{:else}
    <!-- Review form -->
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="mb-3 text-sm font-medium text-gray-900 dark:text-gray-100">
            {userReview ? 'Edit Your Review' : 'Write a Review'}
        </h3>

        <form onsubmit={handleSubmit}>
            <!-- Star Rating -->
            <fieldset class="mb-3">
                <legend class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Rating *</legend>
                <div class="flex items-center gap-1">
                    {#each Array(5) as _, i (i)}
                        {@const starValue = i + 1}
                        {@const isActive = starValue <= (hoveredRating || rating)}
                        <button
                            type="button"
                            onclick={() => (rating = starValue)}
                            onmouseenter={() => (hoveredRating = starValue)}
                            onmouseleave={() => (hoveredRating = 0)}
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
                    {#if rating > 0}
                        <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{rating}/5</span>
                    {/if}
                </div>
            </fieldset>

            <!-- Review Text -->
            <div class="mb-3">
                <label for="review-text" class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Review (optional)</label>
                <textarea
                    id="review-text"
                    bind:value={reviewText}
                    placeholder="Share your thoughts about this visual novel..."
                    rows="6"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                ></textarea>
            </div>

            <!-- Spoiler Toggle -->
            {#if reviewText.trim().length > 0}
                <div class="mb-3">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            bind:checked={hasSpoilers}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                        />
                        <span class="text-sm text-gray-600 dark:text-gray-400">This review contains spoilers</span>
                    </label>
                </div>
            {/if}

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    disabled={rating === 0 || isSubmitting}
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {isSubmitting ? 'Submitting...' : userReview ? 'Update Review' : 'Submit Review'}
                </button>
                {#if isEditing}
                    <button
                        type="button"
                        onclick={handleCancel}
                        class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                {/if}
            </div>

            {#if message}
                <div class="mt-2 text-sm {message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">
                    {message.text}
                </div>
            {/if}
        </form>
    </div>
{/if}
