<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import ArrowTopRightIcon from '@/components/icons/ArrowTopRight.svelte';
    import ClockIcon from '@/components/icons/Clock.svelte';
    import DocumentArrowDownIcon from '@/components/icons/DocumentArrowDown.svelte';
    import EyeIcon from '@/components/icons/Eye.svelte';
    import GamepadIcon from '@/components/icons/Gamepad.svelte';
    import { Link } from '@inertiajs/svelte';
    import { Alert, Badge, Button, Card } from '@/components/ui';
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

<SeoHead {metaTags} title="Manage My Games" />

<div class="space-y-8">
    <PageHeader title="Manage My Games" backHref={route('dashboard')} backLabel="Back to Dashboard" />

    {#if !hasItchio}
        <Alert title="Connect your itch.io account to manage your games" layout="inline" role="status">
            After connecting, we'll show your owned games here for quick editing and analytics.
            {#snippet actions()}
                <Button href={route('auth.redirect', { provider: 'itchio', intended: route('my-games.index') })} inertia={false} size="sm"
                    >Connect itch.io</Button
                >
            {/snippet}
        </Alert>
    {/if}

    {#if hasItchio}
        <Alert title="Connected: {itchio.username}.itch.io" tone="info" layout="inline" role="status">
            {games.length}
            {games.length === 1 ? 'game' : 'games'} found
        </Alert>
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
                                <GamepadIcon class="mx-auto mb-2 h-8 w-8 opacity-50" />
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
                        <Button href={route('my-games.edit', { game: g.slug })} size="sm">
                            <ClockIcon class="h-4 w-4" />
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
