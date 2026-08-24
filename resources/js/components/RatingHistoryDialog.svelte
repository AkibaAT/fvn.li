<script lang="ts">
    import { fetchRaterGameHistory, type RatingHistoryEntry } from '@/api';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { Alert, Button, Dialog, Stars } from '@/components/ui';

    let {
        open,
        raterId,
        gameId,
        title,
        reviewStyles = '',
        onClose,
    }: {
        open: boolean;
        raterId: number | null;
        gameId: number | null;
        title: string;
        reviewStyles?: string;
        onClose: () => void;
    } = $props();

    let ratings = $state<RatingHistoryEntry[]>([]);
    let error = $state<string | null>(null);
    let loading = $state(false);

    $effect(() => {
        if (open && raterId && gameId) {
            loadHistory(raterId, gameId);
        }
    });

    async function loadHistory(rater: number, game: number) {
        loading = true;
        ratings = [];
        error = null;
        try {
            ratings = await fetchRaterGameHistory(rater, game);
        } catch (err) {
            console.error('Failed to load rating history', err);
            error = 'Unable to load rating history.';
        } finally {
            loading = false;
        }
    }
</script>

<Dialog {open} {onClose} title={title || 'Rating History'} size="lg">
    <div class="space-y-6">
        {#if loading}
            <div class="flex items-center justify-center py-8">
                <LoadingSpinner size="lg" label="Loading history" />
                <span class="ml-2 text-gray-600 dark:text-gray-400">Loading history...</span>
            </div>
        {:else if error}
            <Alert tone="danger">{error}</Alert>
        {:else if ratings.length > 0}
            {#each ratings as hr, idx (hr.id)}
                <div class={idx < ratings.length - 1 ? 'border-b border-gray-200 pb-6 dark:border-gray-700' : ''}>
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Stars rating={hr.rating} />
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {hr.published_at
                                    ? new Date(hr.published_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
                                    : ''}
                            </span>
                            {#if hr.is_visible}
                                <span class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                                    >Current</span
                                >
                            {/if}
                        </div>
                        {#if hr.event_id}
                            <a
                                href={`https://itch.io/event/${hr.event_id}`}
                                target="_blank"
                                rel="noopener"
                                class="text-sm text-blue-600 hover:underline dark:text-blue-400">View on itch.io</a
                            >
                        {/if}
                    </div>
                    {#if hr.review}
                        <div class="mx-auto prose text-gray-600 dark:text-gray-300 dark:prose-invert" style={reviewStyles}>
                            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                            {@html hr.review}
                        </div>
                    {/if}
                </div>
            {/each}
        {:else}
            <div class="py-4 text-center text-gray-500 dark:text-gray-400">No rating history found.</div>
        {/if}
    </div>
    {#snippet footer()}
        <Button type="button" variant="outline" tone="neutral" onclick={onClose}>Close</Button>
    {/snippet}
</Dialog>
