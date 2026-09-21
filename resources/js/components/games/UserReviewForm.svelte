<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import StarIcon from '@/components/icons/Star.svelte';
    import TinyMCEEditor from '@/components/editor/TinyMCEEditor.svelte';
    import { untrack } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { Alert, Button, Card, Checkbox } from '@/components/ui';
    import { deleteUserReview, submitUserReview, type UserReview } from '@/api/user-reviews';

    interface Props {
        gameId: number;
        initialReview?: UserReview | null;
        onEditingChange?: (editing: boolean) => void;
        onReviewChange?: () => void;
    }

    let { gameId, initialReview = null, onEditingChange, onReviewChange }: Props = $props();

    const auth = $derived((page as any).props?.auth);
    const isAuthenticated = $derived(Boolean(auth?.user));

    let isEditing = $state(false);
    let rating = $state(untrack(() => initialReview?.rating ?? 0));
    let hoveredRating = $state(0);
    let reviewText = $state(untrack(() => initialReview?.review ?? ''));
    let hasSpoilers = $state(untrack(() => initialReview?.has_spoilers ?? false));
    const userReview = $derived(initialReview);
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
        onEditingChange?.(true);
    }

    function handleCancel() {
        isEditing = false;
        onEditingChange?.(false);
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
        if (isSubmitting || isDeleting) return;
        if (rating === 0) {
            showMessageFn('Please select a rating', 'error');
            return;
        }

        isSubmitting = true;
        try {
            const { message: successMessage } = await submitUserReview(gameId, {
                rating,
                review: reviewText,
                has_spoilers: hasSpoilers,
            });
            if (!(await refreshReview())) return;
            isEditing = false;
            onReviewChange?.();
            onEditingChange?.(false);
            showMessageFn(successMessage, 'success');
        } catch (error) {
            const msg = error instanceof Error ? error.message : 'Failed to submit review';
            showMessageFn(msg, 'error');
        } finally {
            isSubmitting = false;
        }
    }

    async function handleDelete() {
        if (isSubmitting || isDeleting) return;
        isDeleting = true;
        try {
            const successMessage = await deleteUserReview(gameId);
            if (!(await refreshReview())) return;
            rating = 0;
            reviewText = '';
            hasSpoilers = false;
            isEditing = false;
            showDeleteConfirm = false;
            onReviewChange?.();
            onEditingChange?.(false);
            showMessageFn(successMessage, 'success');
        } catch (error) {
            const msg = error instanceof Error ? error.message : 'Failed to delete review';
            showMessageFn(msg, 'error');
        } finally {
            isDeleting = false;
        }
    }

    const refreshReview = () => refreshPage(['userReview', 'reviews', 'availableRatings', 'game', 'metaTags']);
</script>

{#if !isAuthenticated}
    <Card variant="flat" padding="sm" class="text-center">
        <p class="text-sm text-fg-muted">
            <a href={route('login')} class="text-fg underline underline-offset-2">Sign in</a>
            to leave a review for this game.
        </p>
    </Card>
{:else if userReview && !isEditing}
    <Card variant="flat" padding="sm">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-fg">Your Review</span>
                <div class="flex items-center gap-0.5">
                    {#each Array(5) as _, i (i)}
                        <StarIcon class="h-4 w-4 {i < userReview.rating ? 'fill-accent text-accent' : 'fill-border text-border'}" />
                    {/each}
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button type="button" variant="link" tone="primary" onclick={handleStartEdit}>Edit</Button>
                <Button type="button" variant="link" tone="danger" onclick={() => (showDeleteConfirm = true)}>Delete</Button>
            </div>
        </div>

        {#if showDeleteConfirm}
            <Alert tone="danger" layout="inline" class="mt-3"
                >Delete your review?
                {#snippet actions()}
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="solid"
                            tone="danger"
                            onclick={handleDelete}
                            disabled={isDeleting}
                            loading={isDeleting}
                            size="xs"
                        >
                            {isDeleting ? 'Deleting...' : 'Confirm'}
                        </Button>
                        <Button type="button" variant="soft" tone="neutral" size="xs" onclick={() => (showDeleteConfirm = false)}>Cancel</Button>
                    </div>
                {/snippet}
            </Alert>
        {/if}

        {#if message}
            <div class="mt-2 text-sm {message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">
                {message.text}
            </div>
        {/if}
    </Card>
{:else if isEditing}
    <Card variant="flat" padding="sm">
        <h3 class="mb-3 text-sm font-medium text-fg">
            {userReview ? 'Edit Your Review' : 'Write a Review'}
        </h3>

        <form onsubmit={handleSubmit}>
            <fieldset class="mb-3">
                <legend class="mb-1 block text-xs text-fg-muted">Rating *</legend>
                <div class="flex items-center gap-1">
                    {#each Array(5) as _, i (i)}
                        {@const starValue = i + 1}
                        {@const isActive = starValue <= (hoveredRating || rating)}
                        <Button
                            type="button"
                            variant="ghost"
                            tone="warning"
                            size="icon-md"
                            onclick={() => (rating = starValue)}
                            onmouseenter={() => (hoveredRating = starValue)}
                            onmouseleave={() => (hoveredRating = 0)}
                            class="focus:outline-none"
                            ariaLabel="{starValue} star{starValue !== 1 ? 's' : ''}"
                        >
                            <StarIcon
                                class="h-7 w-7 cursor-pointer transition-colors {isActive
                                    ? 'fill-accent text-accent'
                                    : 'fill-border text-border hover:fill-fg-faint'}"
                            />
                        </Button>
                    {/each}
                    {#if rating > 0}
                        <span class="ml-2 text-sm text-fg-faint">{rating}/5</span>
                    {/if}
                </div>
            </fieldset>

            <div class="mb-3">
                <label for="review-text" class="mb-1 block text-xs text-fg-muted">Review (optional)</label>
                <TinyMCEEditor
                    id="review-text"
                    ariaLabel="Review (optional)"
                    content={reviewText}
                    onUpdate={(content) => (reviewText = content)}
                    placeholder="Share your thoughts about this visual novel..."
                    height={240}
                    disableImages
                    reviewMode
                />
            </div>

            {#if reviewText.trim().length > 0}
                <div class="mb-3">
                    <Checkbox bind:checked={hasSpoilers} label="Mark the whole review as a spoiler" />
                </div>
            {/if}

            <div class="flex items-center gap-2">
                <Button type="submit" variant="solid" tone="primary" disabled={rating === 0 || isSubmitting} loading={isSubmitting}>
                    {isSubmitting ? 'Submitting...' : userReview ? 'Update Review' : 'Submit Review'}
                </Button>
                {#if isEditing}
                    <Button type="button" variant="soft" tone="neutral" onclick={handleCancel}>Cancel</Button>
                {/if}
            </div>

            {#if message}
                <div class="mt-2 text-sm {message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">
                    {message.text}
                </div>
            {/if}
        </form>
    </Card>
{/if}
