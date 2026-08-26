<script lang="ts">
    import RatingTrends from '@/components/admin/RatingTrends.svelte';
    import ReleaseYearDistribution from '@/components/admin/ReleaseYearDistribution.svelte';
    import StatsOverview from '@/components/admin/StatsOverview.svelte';
    import TasksList from '@/components/admin/TasksList.svelte';
    import TasksSummary from '@/components/admin/TasksSummary.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/types/meta-tags';

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
        healthSummary?: HealthSummary;
        monitoredTasks?: MonitoredTask[];
        metaTags?: MetaTags;
    }

    let { gameStats, ratingStats, releaseYearStats, healthSummary, monitoredTasks, metaTags }: Props = $props();
</script>

<SeoHead {metaTags} />

<div class="space-y-10">
    <PageHeader title="System Status" />

    <StatsOverview {gameStats} {ratingStats} />
    <RatingTrends {ratingStats} />
    <ReleaseYearDistribution {releaseYearStats} />
    {#if healthSummary && monitoredTasks}
        <section class="space-y-6 border-t border-gray-200 pt-10 dark:border-gray-800">
            <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Scheduled tasks</h2>
            <TasksSummary {healthSummary} />
            <TasksList {monitoredTasks} />
        </section>
    {/if}
</div>
