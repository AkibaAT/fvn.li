<script setup lang="ts">
import RatingTrends from '@/Components/SystemStatus/RatingTrends.vue';
import StatsOverview from '@/Components/SystemStatus/StatsOverview.vue';
import TasksList from '@/Components/SystemStatus/TasksList.vue';
import TasksSummary from '@/Components/SystemStatus/TasksSummary.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type {
    GameStats,
    HealthSummary,
    MonitoredTask,
    RatingStats,
} from '@/types/system';
import { Head, Link } from '@inertiajs/vue3';

interface Props {
    gameStats: GameStats;
    ratingStats: RatingStats;
    healthSummary: HealthSummary;
    monitoredTasks: MonitoredTask[];
    dateFormat: string;
}

defineProps<Props>();
</script>

<template>
    <Head>
        <title>System Status</title>
        <meta name="description" content="System status information" />
    </Head>

    <AppLayout>
        <div class="bg-gray-100 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div
                    class="sticky top-0 z-10 mb-4 flex items-center justify-between bg-gray-100 py-4 dark:bg-gray-900"
                >
                    <a
                        :href="route('games.index')"
                        class="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        <svg
                            class="mr-1 h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>
                        Back to Game List
                    </a>
                </div>

                <div class="mb-6">
                    <h1
                        class="text-2xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        System Status
                    </h1>
                </div>

                <StatsOverview
                    :game-stats="gameStats"
                    :rating-stats="ratingStats"
                />

                <RatingTrends :rating-stats="ratingStats" />

                <TasksSummary :health-summary="healthSummary" />

                <TasksList
                    :monitored-tasks="monitoredTasks"
                    :date-format="dateFormat"
                />
            </div>
        </div>
    </AppLayout>
</template>
