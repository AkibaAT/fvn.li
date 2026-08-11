<script lang="ts">
    import { Button, Dialog, Stars } from '@/components/ui';

    type HistoryRating = {
        id: number;
        rating: number;
        published_at: string | null;
        review?: string | null;
        event_id?: number | null;
        is_visible: boolean;
    };

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

    let ratings = $state<HistoryRating[]>([]);
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
            const res = await fetch(route('raters.games.history', { rater, game }), {
                headers: {
                    Accept: 'application/json',
                },
            });
            if (!res.ok) {
                throw new Error(`Rating history request failed with ${res.status}`);
            }
            const json = await res.json();
            ratings = json.ratings ?? [];
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
                <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
                <span class="ml-2 text-gray-600 dark:text-gray-400">Loading history...</span>
            </div>
        {:else if error}
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200">
                {error}
            </div>
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
                                <span class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900 dark:text-blue-300">Current</span>
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
