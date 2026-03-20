<script lang="ts">
    interface HealthSummary {
        total: number;
        active: number;
        failed: number;
        never_run: number;
        monitored_on_oh_dear: number;
    }

    let { healthSummary }: { healthSummary: HealthSummary } = $props();

    const formatNumber = (num: number) => {
        return new Intl.NumberFormat().format(num);
    };
</script>

<div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            Scheduled Tasks Health
        </h2>
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                Task Status:
            </span>
            <span class="rounded-full bg-green-100 px-2 py-1 text-xs text-green-800 dark:bg-green-900 dark:text-green-200">
                Active
            </span>
            <span class="rounded-full bg-red-100 px-2 py-1 text-xs text-red-800 dark:bg-red-900 dark:text-red-200">
                Failed
            </span>
            <span class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                Single Server
            </span>
            <span class="rounded-full bg-indigo-100 px-2 py-1 text-xs text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                Maintenance OK
            </span>
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Total Tasks
            </dt>
            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                {formatNumber(healthSummary.total)}
            </dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Active
            </dt>
            <dd class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">
                {formatNumber(healthSummary.active)}
            </dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Failed
            </dt>
            <dd class="mt-1 text-2xl font-semibold {healthSummary.failed > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'}">
                {formatNumber(healthSummary.failed)}
            </dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Never Run
            </dt>
            <dd class="mt-1 text-2xl font-semibold {healthSummary.never_run > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-900 dark:text-gray-100'}">
                {formatNumber(healthSummary.never_run)}
            </dd>
        </div>
    </dl>
</div>
