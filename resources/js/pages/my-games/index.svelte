<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Badge, Button, Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';

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

    interface ClickStatsSummary {
        [gameId: string]: GameClickStats;
    }

    interface Props {
        itchio: { username?: string | null };
        games: GameSummary[];
        clickStats: ClickStatsSummary | null;
        metaTags?: { title?: string };
    }

    let { itchio, games, clickStats, metaTags }: Props = $props();
    const hasItchio = $derived(!!itchio?.username);
</script>

<svelte:head>
    <title>{metaTags?.title || 'Manage My Games'}</title>
</svelte:head>

<div class="space-y-8">
    <PageHeader title="Manage My Games" backHref={route('dashboard')} backLabel="Back to Dashboard" class="mb-0" />

    {#if !hasItchio}
        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
            <div class="flex items-center space-x-3">
                <div class="flex-1">
                    <div class="font-medium text-yellow-800 dark:text-yellow-300">Connect your itch.io account to manage your games</div>
                    <div class="mt-1 text-xs text-yellow-700 dark:text-yellow-400">
                        After connecting, we'll show your owned games here for quick editing and analytics.
                    </div>
                </div>
                <Button href={route('auth.redirect', { provider: 'itchio', intended: route('my-games.index') })} inertia={false} size="sm"
                    >Connect itch.io</Button
                >
            </div>
        </div>
    {/if}

    {#if hasItchio}
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
            <div class="flex items-center space-x-3">
                <div class="flex-1">
                    <div class="text-blue-800 dark:text-blue-300">Connected: {itchio.username}.itch.io</div>
                    <div class="mt-0.5 text-xs text-blue-700 dark:text-blue-400">{games.length} {games.length === 1 ? 'game' : 'games'} found</div>
                </div>
            </div>
        </div>
    {/if}

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        {#each games as g (g.id)}
            {@const gameStats = clickStats?.[g.id.toString()]}
            {@const totalViews = gameStats?.page_views_unique || 0}
            {@const totalDownloads = gameStats?.custom_link_clicks_unique || 0}
            {@const itchioVisits = gameStats?.external_project_unique || 0}
            <Card variant="glass" padding="none" class="overflow-hidden">
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
                                <i class="icon-gamepad-2 mb-2 text-3xl opacity-50" aria-hidden="true"></i>
                                <div class="text-sm font-medium">No Image</div>
                            </div>
                        </div>
                    {/if}
                </Link>
                <div class="space-y-2 p-4">
                    <div class="font-semibold text-gray-900 dark:text-white">{g.name}</div>
                    {#if g.has_additional_links}
                        <Badge tone="success" size="sm">Has download links</Badge>
                    {:else}
                        <Badge tone="neutral" size="sm">No download links</Badge>
                    {/if}

                    {#if gameStats && (totalViews > 0 || totalDownloads > 0 || itchioVisits > 0)}
                        <div class="space-y-1 rounded-lg bg-gray-50 p-2 dark:bg-gray-700/50">
                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300">Last 30 days:</div>
                            <div class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-400">
                                {#if totalViews > 0}
                                    <div class="flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                            />
                                        </svg>
                                        <span>{totalViews}</span>
                                    </div>
                                {/if}
                                {#if totalDownloads > 0}
                                    <div class="flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                        <span>{totalDownloads}</span>
                                    </div>
                                {/if}
                                {#if itchioVisits > 0}
                                    <div class="flex items-center gap-1">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                            />
                                        </svg>
                                        <span>{itchioVisits}</span>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <div class="pt-2">
                        <Button href={route('my-games.edit', { game: g.slug })} size="sm">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11 5h2m-1 14v-4m0 0l-2-2m2 2l2-2M5 13a7 7 0 1114 0 7 7 0 01-14 0z"
                                />
                            </svg>
                            <span>Edit</span>
                        </Button>
                    </div>
                </div>
            </Card>
        {/each}
    </div>

    {#if hasItchio && games.length === 0}
        <div class="text-center text-gray-600 dark:text-gray-400">No owned games were detected for your itch.io account.</div>
    {/if}
</div>
