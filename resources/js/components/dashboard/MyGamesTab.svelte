<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import { notify } from '@/components/Toast.svelte';
    import { useAsyncAction } from '@/utils/async-action.svelte';
    import ItchioIcon from '@/components/icons/Itchio.svelte';
    import { Button } from '@/components/ui';
    import { syncItchioGames } from '@/api/my-games';
    import ConnectItchIoAlert from '@/components/my-games/ConnectItchIoAlert.svelte';
    import MyGamesGrid from '@/components/my-games/MyGamesGrid.svelte';
    import type { GameClickStatsMap, GameSummary } from '@/types/my-games';

    interface MyGamesTabProps {
        hasItchio: boolean;
        itchioData: { username?: string };
        myGames: GameSummary[];
        myGamesClickStats: GameClickStatsMap | null;
    }

    let { hasItchio, itchioData, myGames, myGamesClickStats }: MyGamesTabProps = $props();
    const syncAction = useAsyncAction();

    async function syncGames() {
        const result = await syncAction.run(
            async () => {
                const message = await syncItchioGames();
                if (!(await refreshPage(['myGames', 'myGamesClickStats']))) return null;
                return { message };
            },
            { fallbackError: 'Could not sync your itch.io games.' },
        );
        if (!result) return;
        notify(result.message, 'success');
    }
</script>

<div class="space-y-6">
    {#if !hasItchio}
        <ConnectItchIoAlert intended={`${route('dashboard')}#my-games`}>
            {#snippet icon()}<ItchioIcon class="h-5 w-5" />{/snippet}
        </ConnectItchIoAlert>
    {/if}

    {#if hasItchio}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <ItchioIcon class="text-itchio h-5 w-5" />
                <span class="text-sm text-fg-muted"
                    >Connected: <span class="font-medium text-fg">{itchioData.username}.itch.io</span>
                    &middot; {myGames.length}
                    {myGames.length === 1 ? 'game' : 'games'}</span
                >
            </div>
            <Button type="button" size="sm" variant="outline" tone="neutral" loading={syncAction.isLoading} onclick={syncGames}>
                {syncAction.isLoading ? 'Syncing games…' : 'Sync games'}
            </Button>
        </div>
    {/if}

    <MyGamesGrid games={myGames} clickStats={myGamesClickStats} variant="dashboard" showEmptyState={hasItchio} />
</div>
