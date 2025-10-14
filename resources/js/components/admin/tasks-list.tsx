import React from 'react';
import {formatFutureDateTime, getUserTimezone} from '@/utils/date-formatting';

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

interface TasksListProps {
    monitoredTasks: MonitoredTask[];
}

const TasksList: React.FC<TasksListProps> = ({monitoredTasks}) => {
    const userTimezone = getUserTimezone();

    const getTaskStatus = (task: MonitoredTask) => {
        // Prefer backend-provided status if available
        if (task.status_text && task.status_color) {
            return {
                statusColor: task.status_color,
                statusText: task.status_text,
                hasFailedRecently: task.status_text === 'Failed',
                lastFailed: task.last_failed
                    ? new Date(task.last_failed)
                    : null,
            };
        }

        const lastFailed = task.last_failed ? new Date(task.last_failed) : null;
        const lastFinished = task.last_finished
            ? new Date(task.last_finished)
            : null;
        const now = new Date();

        const hasFailedRecently =
            lastFailed && (!lastFinished || lastFailed > lastFinished);
        const hasRunRecently =
            lastFinished &&
            now.getTime() - lastFinished.getTime() < 24 * 60 * 60 * 1000;

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

        return {statusColor, statusText, hasFailedRecently, lastFailed};
    };

    const getDuration = (startTime: string, endTime: string) => {
        const start = new Date(startTime);
        const end = new Date(endTime);
        return Math.floor((end.getTime() - start.getTime()) / 1000);
    };

    return (
        <div className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
            <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                Scheduled Tasks
            </h2>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                            Task
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                            Schedule
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                            Last Run
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                            Next Run
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                            Status
                        </th>
                    </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                    {monitoredTasks.map((task, index) => {
                        const lastStarted = formatDate(task.last_started);
                        const nextRun = formatDate(task.next_run);
                        const {
                            statusColor,
                            statusText,
                            hasFailedRecently,
                            lastFailed,
                        } = getTaskStatus(task);

                        return (
                            <tr
                                key={index}
                                className="group hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td className="px-6 py-4 text-sm">
                                    <div className="font-medium text-gray-900 dark:text-gray-100">
                                        {task.name}
                                    </div>
                                    <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{task.type}</span>
                                        {task.grace_time > 0 && (
                                            <span className="text-xs">
                                                    ({task.grace_time}m grace)
                                                </span>
                                        )}
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-sm">
                                    <div className="text-gray-500 dark:text-gray-400">
                                        {task.schedule}
                                        {task.timezone &&
                                            task.timezone !== 'UTC' && (
                                                <span className="block text-xs text-gray-400 dark:text-gray-500">
                                                        {task.timezone}
                                                    </span>
                                            )}
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-sm">
                                    <div className="text-gray-500 dark:text-gray-400">
                                        {lastStarted ? (
                                            <>
                                                <div>
                                                    {lastStarted.timeAgo}
                                                </div>
                                                <div className="text-xs text-gray-400 dark:text-gray-500">
                                                    {
                                                        lastStarted.formattedDate
                                                    }
                                                </div>
                                                {task.last_finished && (
                                                    <div className="text-xs text-gray-400 dark:text-gray-500">
                                                        Duration:{' '}
                                                        {getDuration(
                                                            task.last_started!,
                                                            task.last_finished,
                                                        )}
                                                        s
                                                    </div>
                                                )}
                                            </>
                                        ) : (
                                            'Never'
                                        )}
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-sm">
                                    <div className="text-gray-500 dark:text-gray-400">
                                        {nextRun ? (
                                            <>
                                                <div>{nextRun.timeAgo}</div>
                                                <div className="text-xs text-gray-400 dark:text-gray-500">
                                                    {nextRun.formattedDate}
                                                </div>
                                            </>
                                        ) : (
                                            'Unknown'
                                        )}
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-sm">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                                <span
                                                    className={`inline-flex rounded-full px-2 text-xs leading-5 font-semibold ${
                                                        statusColor === 'red'
                                                            ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                            : statusColor ===
                                                            'green'
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                : statusColor ===
                                                                'yellow'
                                                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                                    }`}
                                                >
                                                    {statusText}
                                                </span>
                                            {hasFailedRecently &&
                                                lastFailed && (
                                                    <span className="text-xs text-gray-400 dark:text-gray-500">
                                                            {
                                                                formatDate(
                                                                    task.last_failed!,
                                                                )?.timeAgo
                                                            }
                                                        </span>
                                                )}
                                        </div>

                                        {task.latest_log?.meta
                                            ?.failure_message && (
                                            <div
                                                className="max-w-xs truncate text-xs text-red-600 group-hover:whitespace-normal dark:text-red-400">
                                                {
                                                    task.latest_log.meta
                                                        .failure_message
                                                }
                                            </div>
                                        )}

                                        <div className="flex flex-wrap gap-1">
                                            {task.runs_on_one_server && (
                                                <span
                                                    className="inline-flex items-center rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        Single Server
                                                    </span>
                                            )}
                                            {task.runs_in_maintenance && (
                                                <span
                                                    className="inline-flex items-center rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                                        Maintenance OK
                                                    </span>
                                            )}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default TasksList;
