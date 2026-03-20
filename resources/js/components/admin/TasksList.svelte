<script lang="ts">
    import { formatFutureDateTime, formatRelativeDateTime } from '@/utils/date-formatting';

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
        latest_log: { meta?: { failure_message?: string } } | null;
        status_text?: 'Active' | 'Failed' | 'Inactive' | 'Never Run';
        status_color?: 'green' | 'red' | 'yellow' | 'gray';
    }

    let { monitoredTasks }: { monitoredTasks: MonitoredTask[] } = $props();

    const getTaskStatus = (task: MonitoredTask) => {
        if (task.status_text && task.status_color) {
            return {
                statusColor: task.status_color,
                statusText: task.status_text,
                hasFailedRecently: task.status_text === 'Failed',
                lastFailed: task.last_failed ? new Date(task.last_failed) : null,
            };
        }

        const lastFailed = task.last_failed ? new Date(task.last_failed) : null;
        const lastFinished = task.last_finished ? new Date(task.last_finished) : null;
        const now = new Date();

        const hasFailedRecently = lastFailed && (!lastFinished || lastFailed > lastFinished);
        const hasRunRecently = lastFinished && now.getTime() - lastFinished.getTime() < 24 * 60 * 60 * 1000;

        let statusColor = 'gray';
        let statusText = 'Never Run';

        if (hasFailedRecently) {
            statusColor = 'red';
            statusText = 'Failed';
        } else if (hasRunRecently) {
            statusColor = 'green';
            statusText = 'Active';
        } else if (task.last_started) {
            statusColor = 'yellow';
            statusText = 'Inactive';
        }

        return { statusColor, statusText, hasFailedRecently, lastFailed };
    };

    const getDuration = (startTime: string, endTime: string) => {
        const start = new Date(startTime);
        const end = new Date(endTime);
        return Math.floor((end.getTime() - start.getTime()) / 1000);
    };

    const getStatusClasses = (statusColor: string) => {
        if (statusColor === 'red') return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        if (statusColor === 'green') return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        if (statusColor === 'yellow') return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    };
</script>

<div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
    <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Scheduled Tasks</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"> Task </th>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"> Schedule </th>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"> Last Run </th>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"> Next Run </th>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"> Status </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                {#each monitoredTasks as task, index (index)}
                    {@const lastStarted = formatRelativeDateTime(task.last_started)}
                    {@const nextRun = formatFutureDateTime(task.next_run)}
                    {@const taskStatus = getTaskStatus(task)}
                    <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {task.name}
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span>{task.type}</span>
                                {#if task.grace_time > 0}
                                    <span class="text-xs">
                                        ({task.grace_time}m grace)
                                    </span>
                                {/if}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-gray-500 dark:text-gray-400">
                                {task.schedule}
                                {#if task.timezone && task.timezone !== 'UTC'}
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">
                                        {task.timezone}
                                    </span>
                                {/if}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-gray-500 dark:text-gray-400">
                                {#if lastStarted}
                                    <div>
                                        {lastStarted.timeAgo}
                                    </div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                        {lastStarted.formattedDate}
                                    </div>
                                    {#if task.last_finished}
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            Duration: {getDuration(task.last_started!, task.last_finished)}s
                                        </div>
                                    {/if}
                                {:else}
                                    Never
                                {/if}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-gray-500 dark:text-gray-400">
                                {#if nextRun}
                                    <div>{nextRun.timeUntil}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                        {nextRun.formattedDate}
                                    </div>
                                {:else}
                                    Unknown
                                {/if}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex rounded-full px-2 text-xs leading-5 font-semibold {getStatusClasses(
                                            taskStatus.statusColor,
                                        )}"
                                    >
                                        {taskStatus.statusText}
                                    </span>
                                    {#if taskStatus.hasFailedRecently && taskStatus.lastFailed}
                                        <span class="text-xs text-gray-400 dark:text-gray-500">
                                            {formatRelativeDateTime(task.last_failed!)?.timeAgo}
                                        </span>
                                    {/if}
                                </div>

                                {#if task.latest_log?.meta?.failure_message}
                                    <div class="max-w-xs truncate text-xs text-red-600 group-hover:whitespace-normal dark:text-red-400">
                                        {task.latest_log.meta.failure_message}
                                    </div>
                                {/if}

                                <div class="flex flex-wrap gap-1">
                                    {#if task.runs_on_one_server}
                                        <span
                                            class="inline-flex items-center rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                        >
                                            Single Server
                                        </span>
                                    {/if}
                                    {#if task.runs_in_maintenance}
                                        <span
                                            class="inline-flex items-center rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200"
                                        >
                                            Maintenance OK
                                        </span>
                                    {/if}
                                </div>
                            </div>
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
</div>
