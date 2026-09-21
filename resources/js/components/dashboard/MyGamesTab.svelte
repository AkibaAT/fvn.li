<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { refreshPage } from '@/utils/refreshPage';
    import { notify } from '@/components/Toast.svelte';
    import ArrowTopRightIcon from '@/components/icons/ArrowTopRight.svelte';
    import DocumentArrowDownIcon from '@/components/icons/DocumentArrowDown.svelte';
    import EyeIcon from '@/components/icons/Eye.svelte';
    import ItchioIcon from '@/components/icons/Itchio.svelte';
    import PhotoIcon from '@/components/icons/Photo.svelte';
    import { Alert, Button, Card } from '@/components/ui';
    import { syncItchioGames } from '@/api/my-games';

    interface GameSummary {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string | null;
        has_additional_links?: boolean;
        platform?: 'itch_io' | 'steam' | 'other';
    }

    interface GameClickStats {
        page_views_total: number;
        page_views_unique: number;
        external_project_total: number;
        external_project_unique: number;
        custom_link_clicks_total: number;
        custom_link_clicks_unique: number;
    }

    interface MyGamesTabProps {
        hasItchio: boolean;
        itchioData: { username?: string };
        myGames: GameSummary[];
        myGamesClickStats: { [gameId: string]: GameClickStats } | null;
    }

    let { hasItchio, itchioData, myGames, myGamesClickStats }: MyGamesTabProps = $props();
    let syncingGames = $state(false);

    async function syncGames() {
        syncingGames = true;

        try {
            const message = await syncItchioGames();
            if (!(await refreshPage(['myGames', 'myGamesClickStats']))) return;
            notify(message, 'success');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Could not sync your itch.io games.', 'error');
        } finally {
            syncingGames = false;
        }
    }
</script>

<div class="space-y-6">
    {#if !hasItchio}
        <Alert title="Connect your itch.io account to manage your games" layout="inline" role="status">
            After connecting, we'll show your owned games here for quick editing and analytics.
            {#snippet icon()}<ItchioIcon class="h-5 w-5" />{/snippet}
            {#snippet actions()}
                <Button href={route('auth.redirect', { provider: 'itchio', intended: `${route('dashboard')}#my-games` })} inertia={false} size="sm"
                    >Connect itch.io</Button
                >
            {/snippet}
        </Alert>
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
            <Button type="button" size="sm" variant="outline" tone="neutral" loading={syncingGames} onclick={syncGames}>
                {syncingGames ? 'Syncing games…' : 'Sync games'}
            </Button>
        </div>
    {/if}

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        {#each myGames as g (g.id)}
            {@const gameStats = myGamesClickStats?.[g.id.toString()]}
            {@const totalViews = gameStats?.page_views_unique || 0}
            {@const totalDownloads = gameStats?.custom_link_clicks_unique || 0}
            {@const itchioVisits = gameStats?.external_project_unique || 0}
            <Card variant="flat" padding="none" class="overflow-hidden">
                <Link href={route('games.show', g.slug)} class="block">
                    {#if g.thumb_url}
                        <img
                            src={g.thumb_url}
                            alt={g.name}
                            class="aspect-[4/3] w-full {g.platform === 'steam'
                                ? 'object-contain'
                                : 'object-cover'} transition-opacity hover:opacity-90"
                        />
                    {:else}
                        <div class="flex h-36 w-full items-center justify-center bg-surface-alt text-fg-muted transition-colors hover:bg-border">
                            <div class="text-center">
                                <PhotoIcon class="mx-auto mb-1 h-8 w-8 opacity-50" stroke-width="1.5" />
                                <div class="text-sm font-medium">No Image</div>
                            </div>
                        </div>
                    {/if}
                </Link>
                <div class="space-y-2 p-4">
                    <div class="font-semibold text-fg">{g.name}</div>
                    {#if g.has_additional_links}
                        <div class="text-xs text-green-700 dark:text-green-400">Has download links</div>
                    {:else}
                        <div class="text-xs text-fg-faint">No download links</div>
                    {/if}

                    {#if gameStats && (totalViews > 0 || totalDownloads > 0 || itchioVisits > 0)}
                        <div class="space-y-1 rounded-lg border border-border bg-surface-alt p-2">
                            <div class="text-xs font-medium text-fg-muted">Last 30 days:</div>
                            <div class="flex flex-wrap gap-3 text-xs text-fg-muted">
                                {#if totalViews > 0}
                                    <div class="flex items-center gap-1">
                                        <EyeIcon class="h-3 w-3" />
                                        <span>{totalViews}</span>
                                    </div>
                                {/if}
                                {#if totalDownloads > 0}
                                    <div class="flex items-center gap-1">
                                        <DocumentArrowDownIcon class="h-3 w-3" />
                                        <span>{totalDownloads}</span>
                                    </div>
                                {/if}
                                {#if itchioVisits > 0}
                                    <div class="flex items-center gap-1">
                                        <ArrowTopRightIcon class="h-3 w-3" />
                                        <span>{itchioVisits}</span>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <div class="pt-2">
                        <Link
                            href={route('my-games.edit', { game: g.slug })}
                            class="inline-flex items-center rounded-md bg-accent px-3 py-1.5 text-sm text-on-accent hover:opacity-90"
                        >
                            <span>Edit</span>
                        </Link>
                    </div>
                </div>
            </Card>
        {/each}
    </div>

    {#if hasItchio && myGames.length === 0}
        <div class="text-center text-fg-muted">No owned games were detected for your itch.io account.</div>
    {/if}
</div>
