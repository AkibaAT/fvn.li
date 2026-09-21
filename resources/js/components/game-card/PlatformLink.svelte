<script lang="ts">
    import GlobeIcon from '@/components/icons/Globe.svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { Button } from '@/components/ui';

    let {
        url,
        platform = 'other',
        gameId,
        class: className = '',
    }: {
        url: string;
        platform?: 'itch_io' | 'steam' | 'other';
        gameId: number;
        class?: string;
    } = $props();

    const label = $derived(platform === 'itch_io' ? 'Visit on itch.io' : platform === 'steam' ? 'Visit on Steam' : 'Visit Game Page');

    const trackingUrl = $derived.by(() => {
        try {
            return route('track.external-project', { game_id: gameId, url });
        } catch {
            return url;
        }
    });
</script>

<Button
    href={trackingUrl}
    external
    variant="outline"
    tone="neutral"
    size="sm"
    class={className}
    title={label}
    ariaLabel="{label} - opens in new window"
>
    {#snippet icon()}
        {#if platform === 'itch_io'}
            <Itchio class="h-4 w-4" monochrome />
        {:else if platform === 'steam'}
            <Steam class="h-4 w-4" monochrome />
        {:else}
            <GlobeIcon class="h-4 w-4" />
        {/if}
    {/snippet}
    {label}
</Button>
