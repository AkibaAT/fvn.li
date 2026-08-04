<script lang="ts">
    import { Card } from '@/components/ui';
    import StatTile from './StatTile.svelte';
    import { formatNumber } from '@/utils/number-formatting';
    interface HealthSummary {
        total: number;
        active: number;
        failed: number;
        never_run: number;
        monitored_on_oh_dear: number;
    }

    let { healthSummary }: { healthSummary: HealthSummary } = $props();
</script>

<Card variant="outline" class="border-gray-200 shadow-none dark:border-gray-700 dark:bg-gray-800/60">
    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <StatTile label="Total Tasks" value={formatNumber(healthSummary.total)} />
        <StatTile label="Active" value={formatNumber(healthSummary.active)} class="text-green-600 dark:text-green-400" />
        <StatTile
            label="Failed"
            value={formatNumber(healthSummary.failed)}
            class={healthSummary.failed > 0 ? 'text-red-600 dark:text-red-400' : ''}
        />
        <StatTile
            label="Never Run"
            value={formatNumber(healthSummary.never_run)}
            class={healthSummary.never_run > 0 ? 'text-yellow-600 dark:text-yellow-400' : ''}
        />
    </dl>
</Card>
