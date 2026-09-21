<script lang="ts">
    import { Badge, Card } from '@/components/ui';
    import type { BadgeTone } from '@/components/ui/Badge.svelte';
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

    const getStatusBadgeTone = (statusColor: string): BadgeTone => {
        if (statusColor === 'red') return 'danger';
        if (statusColor === 'green') return 'success';
        if (statusColor === 'yellow') return 'warning';
        return 'neutral';
    };
</script>

<Card variant="flat" padding="none" class="overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border">
            <thead class="bg-surface-alt">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase"> Task </th>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase"> Schedule </th>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase"> Last Run </th>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase"> Next Run </th>
                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase"> Status </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border bg-surface">
                {#each monitoredTasks as task, index (index)}
                    {@const lastStarted = formatRelativeDateTime(task.last_started)}
                    {@const nextRun = formatFutureDateTime(task.next_run)}
                    {@const taskStatus = getTaskStatus(task)}
                    <tr class="group hover:bg-surface-alt">
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-fg">
                                {task.name}
                            </div>
                            <div class="flex items-center gap-2 text-xs text-fg-muted">
                                <span>{task.type}</span>
                                {#if task.grace_time > 0}
                                    <span class="text-xs">
                                        ({task.grace_time}m grace)
                                    </span>
                                {/if}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-fg-muted">
                                {task.schedule}
                                {#if task.timezone && task.timezone !== 'UTC'}
                                    <span class="block text-xs text-fg-faint">
                                        {task.timezone}
                                    </span>
                                {/if}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-fg-muted">
                                {#if lastStarted}
                                    <div>
                                        {lastStarted.timeAgo}
                                    </div>
                                    <div class="text-xs text-fg-faint">
                                        {lastStarted.formattedDate}
                                    </div>
                                    {#if task.last_finished}
                                        <div class="text-xs text-fg-faint">
                                            Duration: {getDuration(task.last_started!, task.last_finished)}s
                                        </div>
                                    {/if}
                                {:else}
                                    Never
                                {/if}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-fg-muted">
                                {#if nextRun}
                                    <div>{nextRun.timeUntil}</div>
                                    <div class="text-xs text-fg-faint">
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
                                    <Badge tone={getStatusBadgeTone(taskStatus.statusColor)}>
                                        {taskStatus.statusText}
                                    </Badge>
                                    {#if taskStatus.hasFailedRecently && taskStatus.lastFailed}
                                        <span class="text-xs text-fg-faint">
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
                                        <Badge tone="neutral" size="sm">Single Server</Badge>
                                    {/if}
                                    {#if task.runs_in_maintenance}
                                        <Badge tone="neutral" size="sm">Maintenance OK</Badge>
                                    {/if}
                                </div>
                            </div>
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
</Card>
