<script lang="ts">
    import { formatLocalDate } from '@/utils/date-formatting';
    import { fetchRaterGameHistory, type RatingHistoryEntry } from '@/api';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { Alert, Badge, Button, Dialog, Stars } from '@/components/ui';

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
        if (!open || !raterId || !gameId) return;
        let active = true;
        loading = true;
        ratings = [];
        error = null;
        fetchRaterGameHistory(raterId, gameId)
            .then((history) => {
                if (active) ratings = history;
            })
            .catch(() => {
                if (active) error = 'Unable to load rating history.';
            })
            .finally(() => {
                if (active) loading = false;
            });
        return () => {
            active = false;
        };
    });
</script>

<Dialog {open} {onClose} title={title || 'Rating History'} size="lg">
    <div class="space-y-6">
        {#if loading}
            <div class="flex items-center justify-center py-8">
                <LoadingSpinner size="lg" label="Loading history" />
                <span class="ml-2 text-fg-muted">Loading history...</span>
            </div>
        {:else if error}
            <Alert tone="danger">{error}</Alert>
        {:else if ratings.length > 0}
            {#each ratings as hr, idx (hr.id)}
                <div class={idx < ratings.length - 1 ? 'border-b border-border pb-6' : ''}>
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Stars rating={hr.rating} />
                            <span class="text-sm text-fg-faint">
                                {hr.published_at ? formatLocalDate(hr.published_at) : ''}
                            </span>
                            {#if hr.is_visible}
                                <Badge tone="neutral" size="sm">Current</Badge>
                            {/if}
                        </div>
                        {#if hr.event_id}
                            <a
                                href={`https://itch.io/event/${hr.event_id}`}
                                target="_blank"
                                rel="noopener"
                                class="text-sm text-fg-muted transition-colors hover:text-fg">View on itch.io</a
                            >
                        {/if}
                    </div>
                    {#if hr.review}
                        <div class="mx-auto prose text-fg-muted dark:prose-invert" style={reviewStyles}>
                            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                            {@html hr.review}
                        </div>
                    {/if}
                </div>
            {/each}
        {:else}
            <div class="py-4 text-center text-fg-muted">No rating history found.</div>
        {/if}
    </div>
    {#snippet footer()}
        <Button type="button" variant="outline" tone="neutral" onclick={onClose}>Close</Button>
    {/snippet}
</Dialog>
