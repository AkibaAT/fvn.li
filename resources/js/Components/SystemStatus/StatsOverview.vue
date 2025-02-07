// Components/SystemStatus/StatsOverview.vue
<script lang="ts" setup>
import type {GameStats, RatingStats} from '@/types/system';
import {formatDate, formatNumber, timeDiff} from '@/utils/formatters';
import RatingStars from './RatingStars.vue';

defineProps<{
    gameStats: GameStats;
    ratingStats: RatingStats;
}>();
</script>

<template>
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
                        {{ formatNumber(gameStats.total) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Listed Games
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ formatNumber(gameStats.visible) }}
                    </dd>
                    <dd class="text-sm text-gray-500 dark:text-gray-400">
                        Listing rate: {{ ((gameStats.visible / Math.max(gameStats.total, 1)) * 100).toFixed(1) }}%
                    </dd>
                </div>
            </dl>
            <div v-if="gameStats.latest_update" class="mt-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Latest Update:</span>
                <span class="ml-1 text-gray-900 dark:text-gray-100">
                    {{ timeDiff(gameStats.latest_update) }}
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        ({{ formatDate(gameStats.latest_update) }})
                    </span>
                </span>
            </div>
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
                                {{ formatNumber(ratingStats.total) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                Reviews
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ formatNumber(ratingStats.reviews.total) }}
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">
                                Review rate: {{ ratingStats.reviews.review_rate.toFixed(1) }}%
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                Average Rating
                            </dt>
                            <dd class="mt-1 flex items-center gap-2">
                                <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ Number(ratingStats.average_rating).toFixed(2) }}
                                </span>
                                <RatingStars :rating="Number(ratingStats.average_rating)"/>
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
                                {{ formatNumber(ratingStats.visible_games.total) }}
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">
                                ({{
                                    ((ratingStats.visible_games.total / Math.max(ratingStats.total, 1)) * 100).toFixed(1)
                                }}% of all)
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                Reviews
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ formatNumber(ratingStats.visible_games.reviews) }}
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">
                                Review rate: {{ ratingStats.visible_games.review_rate.toFixed(1) }}%
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                Average Rating
                            </dt>
                            <dd class="mt-1 flex items-center gap-2">
                                <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ Number(ratingStats.visible_games.average_rating).toFixed(2) }}
                                </span>
                                <RatingStars :rating="Number(ratingStats.visible_games.average_rating)"/>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div v-if="ratingStats.latest" class="mt-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Latest Rating:</span>
                <span class="ml-1 text-gray-900 dark:text-gray-100">
                    {{ timeDiff(ratingStats.latest) }}
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        ({{ formatDate(ratingStats.latest) }})
                    </span>
                </span>
            </div>
        </div>
    </div>
</template>
