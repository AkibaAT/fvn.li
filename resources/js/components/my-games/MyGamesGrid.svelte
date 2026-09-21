<script lang="ts">
    import type { Snippet } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import ArrowTopRightIcon from '@/components/icons/ArrowTopRight.svelte';
    import ClockIcon from '@/components/icons/Clock.svelte';
    import DocumentArrowDownIcon from '@/components/icons/DocumentArrowDown.svelte';
    import EyeIcon from '@/components/icons/Eye.svelte';
    import GamepadIcon from '@/components/icons/Gamepad.svelte';
    import PhotoIcon from '@/components/icons/Photo.svelte';
    import { Badge, Button, Card } from '@/components/ui';
    import type { GameClickStatsMap, GameSummary } from '@/types/my-games';

    interface Props {
        games: GameSummary[];
        clickStats?: GameClickStatsMap | null;
        /** Cosmetic context: 'page' is the My Games page styling, 'dashboard' the dashboard tab styling. */
        variant?: 'page' | 'dashboard';
        /** Render the trailing empty state (parents pass their connected/auth gate, e.g. hasItchio). */
        showEmptyState?: boolean;
        emptyMessage?: string;
        /** Optional CTA rendered after the empty-state message. */
        emptyAction?: Snippet;
    }

    let {
        games,
        clickStats = null,
        variant = 'page',
        showEmptyState = false,
        emptyMessage = 'No owned games were detected for your itch.io account.',
        emptyAction,
    }: Props = $props();
</script>

<div class="grid grid-cols-1 gap-6 md:grid-cols-3">
    {#each games as g (g.id)}
        {@const gameStats = clickStats?.[g.id.toString()]}
        {@const totalViews = gameStats?.page_views_unique || 0}
        {@const totalDownloads = gameStats?.custom_link_clicks_unique || 0}
        {@const itchioVisits = gameStats?.external_project_unique || 0}
        <Card variant="flat" padding="none" class="overflow-hidden">
            <Link href={route('games.show', g.slug)} class="block">
                {#if g.thumb_url}
                    <img
                        src={g.thumb_url}
                        alt={g.name}
                        class="aspect-[4/3] w-full {g.platform === 'steam' ? 'object-contain' : 'object-cover'} transition-opacity hover:opacity-90"
                    />
                {:else}
                    <div
                        class="flex h-36 w-full items-center justify-center bg-surface-alt text-fg-muted transition-colors {variant === 'dashboard'
                            ? 'hover:bg-border'
                            : ''}"
                    >
                        <div class="text-center">
                            {#if variant === 'dashboard'}
                                <PhotoIcon class="mx-auto mb-1 h-8 w-8 opacity-50" stroke-width="1.5" />
                            {:else}
                                <GamepadIcon class="mx-auto mb-2 h-8 w-8 opacity-50" />
                            {/if}
                            <div class="text-sm font-medium">No Image</div>
                        </div>
                    </div>
                {/if}
            </Link>
            <div class="space-y-2 p-4">
                <div class="font-semibold text-fg">{g.name}</div>
                {#if variant === 'page'}
                    {#if g.has_additional_links}
                        <Badge tone="success" size="sm">Has download links</Badge>
                    {:else}
                        <Badge tone="neutral" size="sm">No download links</Badge>
                    {/if}
                {:else if g.has_additional_links}
                    <div class="text-xs text-green-700 dark:text-green-400">Has download links</div>
                {:else}
                    <div class="text-xs text-fg-faint">No download links</div>
                {/if}

                {#if gameStats && (totalViews > 0 || totalDownloads > 0 || itchioVisits > 0)}
                    <div class="space-y-1 rounded-lg {variant === 'dashboard' ? 'border border-border ' : ''}bg-surface-alt p-2">
                        <div class="text-xs font-medium text-fg-muted">Last 30 days:</div>
                        <div class="flex flex-wrap gap-3 text-xs {variant === 'dashboard' ? 'text-fg-muted' : 'text-fg'}">
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
                        {#if variant !== 'dashboard'}
                            <ClockIcon class="h-4 w-4" />
                        {/if}
                        <span>Edit</span>
                    </Button>
                </div>
            </div>
        </Card>
    {/each}
</div>

{#if showEmptyState && games.length === 0}
    <div class="text-center text-fg-muted">
        {emptyMessage}
        {@render emptyAction?.()}
    </div>
{/if}
