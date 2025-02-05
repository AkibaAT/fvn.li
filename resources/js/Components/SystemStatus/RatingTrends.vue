// Components/SystemStatus/RatingTrends.vue
<script setup lang="ts">
import type { RatingStats } from '@/types/system';
import TrendGraph from './TrendGraph.vue';

defineProps<{
    ratingStats: RatingStats;
}>();
</script>

<template>
    <!-- All Ratings Trend -->
    <div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="space-y-8">
            <div>
                <h2
                    class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100"
                >
                    All Ratings Trend
                </h2>
                <TrendGraph
                    :data="ratingStats.monthly_trend"
                    line-color="#EAB308"
                    text-color="#6B7280"
                    grid-color="#E5E7EB"
                />
            </div>
        </div>
    </div>

    <!-- Listed Games Ratings Trend -->
    <div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="space-y-8">
            <div>
                <h2
                    class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100"
                >
                    Listed Games Ratings Trend
                </h2>
                <TrendGraph
                    :data="ratingStats.visible_games_monthly_trend"
                    line-color="#22C55E"
                    text-color="#6B7280"
                    grid-color="#E5E7EB"
                />
            </div>
        </div>
    </div>
</template>
