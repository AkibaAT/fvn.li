<script lang="ts">
    import Stars from '@/components/ui/Stars.svelte';
    import { Card } from '@/components/ui';
    import { formatRelativeDateTime, getUserTimezone } from '@/utils/date-formatting';
    import { formatNumber } from '@/utils/number-formatting';
    import StatTile from './StatTile.svelte';

    interface GameStats {
        total: number;
        visible: number;
        listing_rate: number;
        latest_update: string | null;
    }

    interface RatingStats {
        total: number;
        reviews: {
            total: number;
            review_rate: number;
        };
        average_rating: number;
        visible_games: {
            total: number;
            reviews: number;
            review_rate: number;
            average_rating: number;
        };
        latest: string | null;
    }

    let { gameStats, ratingStats }: { gameStats: GameStats; ratingStats: RatingStats } = $props();

    const gameLatestUpdate = $derived(formatRelativeDateTime(gameStats.latest_update));
    const ratingLatest = $derived(formatRelativeDateTime(ratingStats.latest));
    const userTimezone = getUserTimezone();
</script>

<section class="space-y-6">
    <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Overview</h2>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <Card variant="outline" class="border-gray-200 shadow-none dark:border-gray-700 dark:bg-gray-800/60">
            <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Games</h3>
            <dl class="grid grid-cols-2 gap-4">
                <StatTile label="Total Games" value={formatNumber(gameStats.total)} />
                <StatTile label="Listed Games" value={formatNumber(gameStats.visible)}>
                    {#snippet detail()}
                        <dd class="text-sm text-gray-500 dark:text-gray-400">
                            Listing rate: {gameStats.listing_rate ? gameStats.listing_rate.toFixed(1) : '0.0'}%
                        </dd>
                    {/snippet}
                </StatTile>
            </dl>
            {#if gameLatestUpdate}
                <div class="mt-4 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Latest Update:</span><span class="ml-1 text-gray-900 dark:text-gray-100"
                        >{gameLatestUpdate.timeAgo}
                        <span class="text-xs text-gray-500 dark:text-gray-400">({gameLatestUpdate.formattedDate} {userTimezone})</span></span
                    >
                </div>
            {/if}
        </Card>

        <Card variant="outline" class="border-gray-200 shadow-none dark:border-gray-700 dark:bg-gray-800/60">
            <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Ratings</h3>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <h3 class="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">All Ratings</h3>
                    <dl class="grid grid-cols-2 gap-4">
                        <StatTile label="Ratings" value={formatNumber(ratingStats.total)} />
                        <StatTile label="Reviews" value={formatNumber(ratingStats.reviews.total)}>
                            {#snippet detail()}
                                <dd class="text-sm text-gray-500 dark:text-gray-400">
                                    Review rate: {ratingStats.reviews.review_rate ? ratingStats.reviews.review_rate.toFixed(1) : '0.0'}%
                                </dd>
                            {/snippet}
                        </StatTile>
                        <StatTile label="Average Rating" value="" containerClass="col-span-2" class="flex items-center gap-2">
                            {#snippet valueContent()}
                                <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {ratingStats.average_rating ? Number(ratingStats.average_rating).toFixed(2) : 'N/A'}
                                </span>
                                <Stars rating={Number(ratingStats.average_rating) || 0} />
                            {/snippet}
                        </StatTile>
                    </dl>
                </div>

                <div>
                    <h3 class="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">Listed Games</h3>
                    <dl class="grid grid-cols-2 gap-4">
                        <StatTile label="Ratings" value={formatNumber(ratingStats.visible_games.total)}>
                            {#snippet detail()}
                                <dd class="text-sm text-gray-500 dark:text-gray-400">
                                    ({ratingStats.visible_games.total && ratingStats.total
                                        ? ((ratingStats.visible_games.total / Math.max(ratingStats.total, 1)) * 100).toFixed(1)
                                        : '0.0'}% of all)
                                </dd>
                            {/snippet}
                        </StatTile>
                        <StatTile label="Reviews" value={formatNumber(ratingStats.visible_games.reviews)}>
                            {#snippet detail()}
                                <dd class="text-sm text-gray-500 dark:text-gray-400">
                                    Review rate: {ratingStats.visible_games.review_rate ? ratingStats.visible_games.review_rate.toFixed(1) : '0.0'}%
                                </dd>
                            {/snippet}
                        </StatTile>
                        <StatTile label="Average Rating" value="" containerClass="col-span-2" class="flex items-center gap-2">
                            {#snippet valueContent()}
                                <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {ratingStats.visible_games.average_rating ? Number(ratingStats.visible_games.average_rating).toFixed(2) : 'N/A'}
                                </span>
                                <Stars rating={Number(ratingStats.visible_games.average_rating) || 0} />
                            {/snippet}
                        </StatTile>
                    </dl>
                </div>
            </div>
            {#if ratingLatest}
                <div class="mt-4 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Latest Rating:</span><span class="ml-1 text-gray-900 dark:text-gray-100"
                        >{ratingLatest.timeAgo}
                        <span class="text-xs text-gray-500 dark:text-gray-400">({ratingLatest.formattedDate} {userTimezone})</span></span
                    >
                </div>
            {/if}
        </Card>
    </div>
</section>
