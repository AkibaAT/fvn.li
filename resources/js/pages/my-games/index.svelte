<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import { Alert } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import ConnectItchIoAlert from '@/components/my-games/ConnectItchIoAlert.svelte';
    import MyGamesGrid from '@/components/my-games/MyGamesGrid.svelte';
    import type { GameClickStatsMap, GameSummary } from '@/types/my-games';

    interface Props {
        itchio: { username?: string | null };
        games: GameSummary[];
        clickStats: GameClickStatsMap | null;
        metaTags?: { title?: string };
    }

    let { itchio, games, clickStats, metaTags }: Props = $props();
    const hasItchio = $derived(!!itchio?.username);
</script>

<SeoHead {metaTags} title="Manage My Games" />

<div class="space-y-8">
    <PageHeader title="Manage My Games" backHref={route('dashboard')} backLabel="Back to Dashboard" />

    {#if !hasItchio}
        <ConnectItchIoAlert intended={route('my-games.index')} />
    {/if}

    {#if hasItchio}
        <Alert title="Connected: {itchio.username}.itch.io" tone="info" layout="inline" role="status">
            {games.length}
            {games.length === 1 ? 'game' : 'games'} found
        </Alert>
    {/if}

    <MyGamesGrid {games} {clickStats} variant="page" showEmptyState={hasItchio} />
</div>
