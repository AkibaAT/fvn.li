<script lang="ts">
    import RatingTrends from '@/components/admin/RatingTrends.svelte';
    import ReleaseYearDistribution from '@/components/admin/ReleaseYearDistribution.svelte';
    import StatsOverview from '@/components/admin/StatsOverview.svelte';
    import TasksList from '@/components/admin/TasksList.svelte';
    import TasksSummary from '@/components/admin/TasksSummary.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/components/seo/SeoHead.svelte';
    import { Link } from '@inertiajs/svelte';

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
        monthly_trend: Array<{ month: string; count: number }>;
        visible_games_monthly_trend: Array<{ month: string; count: number }>;
    }

    interface HealthSummary {
        total: number;
        active: number;
        failed: number;
        never_run: number;
        monitored_on_oh_dear: number;
    }

    interface MonitoredTask {
        name: string;
        type: string;
        schedule: string;
        timezone: string;
        last_started: string | null;
        last_finished: string | null;
        last_failed: string | null;
        last_skipped: string | null;
        last_pinged: string | null;
        registered_on_oh_dear: boolean;
        next_run: string | null;
        grace_time: number;
        runs_on_one_server: boolean;
        runs_in_maintenance: boolean;
        latest_log: {
            id?: number;
            task_id?: number;
            type?: string;
            meta?: Record<string, unknown>;
            output?: string;
            status?: string;
            created_at?: string;
            updated_at?: string;
        } | null;
    }

    interface ReleaseYearStats {
        year_distribution: Array<{ year: number; count: number }>;
    }

    interface Props {
        gameStats: GameStats;
        ratingStats: RatingStats;
        releaseYearStats: ReleaseYearStats;
        healthSummary: HealthSummary;
        monitoredTasks: MonitoredTask[];
        metaTags?: MetaTags;
    }

    let { gameStats, ratingStats, releaseYearStats, healthSummary, monitoredTasks, metaTags }: Props = $props();
</script>

<SeoHead {metaTags} />

<svelte:head>
    <title>System Status</title>
</svelte:head>

<div class="bg-gray-100 dark:bg-gray-900">
    <div class="sticky top-0 z-10 mb-4 flex items-center justify-between bg-gray-100 py-4 dark:bg-gray-900">
        <Link
            href={route('games.index')}
            class="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
            <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Game List
        </Link>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">System Status</h1>
    </div>

    <StatsOverview {gameStats} {ratingStats} />
    <RatingTrends {ratingStats} />
    <ReleaseYearDistribution {releaseYearStats} />
    <TasksSummary {healthSummary} />
    <TasksList {monitoredTasks} />
</div>
