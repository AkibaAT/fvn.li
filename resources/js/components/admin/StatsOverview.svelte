<script lang="ts">
    import Stars from '@/components/ui/Stars.svelte';
    import { formatRelativeDateTime, getUserTimezone } from '@/utils/date-formatting';

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

    const formatNumber = (num: number) => {
        return new Intl.NumberFormat().format(num);
    };

    const gameLatestUpdate = $derived(formatRelativeDateTime(gameStats.latest_update));
    const ratingLatest = $derived(formatRelativeDateTime(ratingStats.latest));
    const userTimezone = getUserTimezone();
</script>

<div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
    <!-- Game Stats -->
    <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
            Games
        </h2>
        <dl class="grid grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Games
                </dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {formatNumber(gameStats.total)}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Listed Games
                </dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {formatNumber(gameStats.visible)}
                </dd>
                <dd class="text-sm text-gray-500 dark:text-gray-400">
                    Listing rate: {gameStats.listing_rate
                        ? gameStats.listing_rate.toFixed(1)
                        : '0.0'}%
                </dd>
            </div>
        </dl>
        {#if gameLatestUpdate}
            <div class="mt-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Latest Update:</span><span class="ml-1 text-gray-900 dark:text-gray-100">{gameLatestUpdate.timeAgo} <span class="text-xs text-gray-500 dark:text-gray-400">({gameLatestUpdate.formattedDate} {userTimezone})</span></span>
            </div>
        {/if}
    </div>

    <!-- Rating Stats -->
    <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
            Ratings
        </h2>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- All Ratings -->
            <div>
                <h3 class="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">
                    All Ratings
                </h3>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Ratings
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {formatNumber(ratingStats.total)}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Reviews
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {formatNumber(ratingStats.reviews.total)}
                        </dd>
                        <dd class="text-sm text-gray-500 dark:text-gray-400">
                            Review rate: {ratingStats.reviews.review_rate
                                ? ratingStats.reviews.review_rate.toFixed(1)
                                : '0.0'}%
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Average Rating
                        </dt>
                        <dd class="mt-1 flex items-center gap-2">
                            <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {ratingStats.average_rating
                                    ? Number(ratingStats.average_rating).toFixed(2)
                                    : 'N/A'}
                            </span>
                            <Stars
                                rating={Number(ratingStats.average_rating) || 0}
                            />
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Listed Games -->
            <div>
                <h3 class="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">
                    Listed Games
                </h3>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Ratings
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {formatNumber(ratingStats.visible_games.total)}
                        </dd>
                        <dd class="text-sm text-gray-500 dark:text-gray-400">
                            ({ratingStats.visible_games.total && ratingStats.total
                                ? ((ratingStats.visible_games.total / Math.max(ratingStats.total, 1)) * 100).toFixed(1)
                                : '0.0'}% of all)
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Reviews
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {formatNumber(ratingStats.visible_games.reviews)}
                        </dd>
                        <dd class="text-sm text-gray-500 dark:text-gray-400">
                            Review rate: {ratingStats.visible_games.review_rate
                                ? ratingStats.visible_games.review_rate.toFixed(1)
                                : '0.0'}%
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Average Rating
                        </dt>
                        <dd class="mt-1 flex items-center gap-2">
                            <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {ratingStats.visible_games.average_rating
                                    ? Number(ratingStats.visible_games.average_rating).toFixed(2)
                                    : 'N/A'}
                            </span>
                            <Stars
                                rating={Number(ratingStats.visible_games.average_rating) || 0}
                            />
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
        {#if ratingLatest}
            <div class="mt-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Latest Rating:</span><span class="ml-1 text-gray-900 dark:text-gray-100">{ratingLatest.timeAgo} <span class="text-xs text-gray-500 dark:text-gray-400">({ratingLatest.formattedDate} {userTimezone})</span></span>
            </div>
        {/if}
    </div>
</div>
