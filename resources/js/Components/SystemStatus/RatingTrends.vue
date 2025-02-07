<script setup lang="ts">
import TrendChart from '@/Components/SystemStatus/TrendChart.vue';
import type { RatingStats } from '@/types/system';

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
                <TrendChart
                    :data="ratingStats.monthly_trend"
                    line-color="#EAB308"
                    area-color="rgba(234, 179, 8, 0.1)"
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
                <TrendChart
                    :data="ratingStats.visible_games_monthly_trend"
                    line-color="#22C55E"
                    area-color="rgba(34, 197, 94, 0.1)"
                />
            </div>
        </div>
    </div>
</template>
