<script lang="ts">
    import { Card } from '@/components/ui';
    import RatingDistributionBars from './RatingDistributionBars.svelte';
    import type { GlobalStats, StatsBlock } from './types';

    type Props = {
        stats: GlobalStats;
        scope?: 'all_games' | 'visible_games';
        heading: string;
    };

    let { stats, scope, heading }: Props = $props();

    const primary = $derived(scope ? stats[scope] : stats.all_games);
    const secondary = $derived(scope ? null : stats.visible_games);
    const formatDate = (value?: string | null) =>
        value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '\u2014';

    const tiles = $derived([
        {
            label: scope === 'visible_games' ? 'Total Listed Games Rated' : 'Total Games Rated',
            value: primary.unique_games.toLocaleString(),
            detail: secondary ? `${secondary.unique_games.toLocaleString()} listed` : null,
        },
        {
            label: scope === 'visible_games' ? 'Average Rating (Listed)' : 'Average Rating',
            value: Number(primary.average_rating ?? 0).toFixed(1),
            detail: secondary ? `${Number(secondary.average_rating ?? 0).toFixed(1)} for listed games` : null,
        },
        {
            label: scope === 'visible_games' ? 'Review Rate (Listed)' : 'Review Rate',
            value: `${Math.round(primary.review_percentage)}%`,
            detail: secondary ? `${Math.round(secondary.review_percentage)}% for listed games` : null,
        },
        {
            label: scope === 'visible_games' ? 'Ratings Count (Listed)' : 'Ratings Count',
            value: primary.total_ratings.toLocaleString(),
            detail: secondary ? `${secondary.total_ratings.toLocaleString()} for listed games` : null,
        },
    ]);

    const distributions = $derived(
        scope
            ? [{ title: scope === 'visible_games' ? 'Listed Games' : 'All Games', block: stats[scope] }]
            : [
                  { title: 'All Games', block: stats.all_games },
                  { title: 'Listed Games', block: stats.visible_games },
              ],
    );
</script>

<Card padding="lg" class="shadow">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{heading}</h2>
        <div class="text-sm text-gray-500 dark:text-gray-400">{formatDate(stats.first_rating)} - {formatDate(stats.latest_rating)}</div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        {#each tiles as tile (tile.label)}
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{tile.label}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{tile.value}</div>
                {#if tile.detail}<div class="text-sm text-gray-500 dark:text-gray-400">{tile.detail}</div>{/if}
            </div>
        {/each}
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 {distributions.length > 1 ? 'md:grid-cols-2' : ''}">
        {#each distributions as distribution (distribution.title)}
            {@const block = distribution.block as StatsBlock}
            <div>
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">{distribution.title} Rating Distribution</h3>
                <RatingDistributionBars distribution={block.rating_distribution} total={block.total_ratings} />
            </div>
        {/each}
    </div>
</Card>
