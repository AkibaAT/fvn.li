<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { notify } from '@/components/Toast.svelte';
    import ItchioIcon from '@/components/icons/Itchio.svelte';
    import { Button, Card } from '@/components/ui';
    import { authenticatedFetch } from '@/utils/csrf';

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
            const response = await authenticatedFetch(route('user.itchio-games.sync'), { method: 'POST' });
            const data = await response.json();

            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Could not sync your itch.io games.');
            }

            notify(data.message, 'success');
            router.reload({ only: ['myGames', 'myGamesClickStats'] });
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Could not sync your itch.io games.', 'error');
        } finally {
            syncingGames = false;
        }
    }
</script>

<div class="space-y-6">
    {#if !hasItchio}
        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
            <div class="flex items-center space-x-3">
                <ItchioIcon class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                <div class="flex-1">
                    <div class="font-medium text-yellow-800 dark:text-yellow-300">Connect your itch.io account to manage your games</div>
                    <div class="mt-1 text-xs text-yellow-700 dark:text-yellow-400">
                        After connecting, we'll show your owned games here for quick editing and analytics.
                    </div>
                </div>
                <a
                    href={route('auth.redirect', { provider: 'itchio', intended: `${route('dashboard')}#my-games` })}
                    class="rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700">Connect itch.io</a
                >
            </div>
        </div>
    {/if}

    {#if hasItchio}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <ItchioIcon class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Connected: <span class="font-medium text-gray-900 dark:text-white">{itchioData.username}.itch.io</span>
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
            <Card variant="glass" padding="none" class="overflow-hidden shadow-none">
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
                        <div
                            class="flex h-36 w-full items-center justify-center bg-gray-100 text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        >
                            <div class="text-center">
                                <svg class="mx-auto mb-1 h-8 w-8 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    ><path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    /></svg
                                >
                                <div class="text-sm font-medium">No Image</div>
                            </div>
                        </div>
                    {/if}
                </Link>
                <div class="space-y-2 p-4">
                    <div class="font-semibold text-gray-900 dark:text-white">{g.name}</div>
                    {#if g.has_additional_links}
                        <div class="text-xs text-green-700 dark:text-green-400">Has download links</div>
                    {:else}
                        <div class="text-xs text-gray-500 dark:text-gray-400">No download links</div>
                    {/if}

                    {#if gameStats && (totalViews > 0 || totalDownloads > 0 || itchioVisits > 0)}
                        <div class="space-y-1 rounded-lg bg-gray-50 p-2 dark:bg-gray-700/50">
                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300">Last 30 days:</div>
                            <div class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-400">
                                {#if totalViews > 0}
                                    <div class="flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            ><path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            /><path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                            /></svg
                                        >
                                        <span>{totalViews}</span>
                                    </div>
                                {/if}
                                {#if totalDownloads > 0}
                                    <div class="flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            ><path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            /></svg
                                        >
                                        <span>{totalDownloads}</span>
                                    </div>
                                {/if}
                                {#if itchioVisits > 0}
                                    <div class="flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            ><path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                            /></svg
                                        >
                                        <span>{itchioVisits}</span>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <div class="pt-2">
                        <Link
                            href={route('my-games.edit', { game: g.slug })}
                            class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700"
                        >
                            <span>Edit</span>
                        </Link>
                    </div>
                </div>
            </Card>
        {/each}
    </div>

    {#if hasItchio && myGames.length === 0}
        <div class="text-center text-gray-600 dark:text-gray-400">No owned games were detected for your itch.io account.</div>
    {/if}
</div>
