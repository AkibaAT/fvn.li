@php use Carbon\Carbon; @endphp
@props(['monitoredTasks', 'dateFormat'])

<div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
    <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
        Scheduled Tasks
    </h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
            <tr>
                <th
                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    Task
                </th>
                <th
                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    Schedule
                </th>
                <th
                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    Last Run
                </th>
                <th
                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    Next Run
                </th>
                <th
                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                >
                    Status
                </th>
            </tr>
            </thead>
            <tbody
                class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"
            >
            @foreach ($monitoredTasks as $task)
                <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-6 py-4 text-sm">
                        <div class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $task['name'] }}
                        </div>
                        <div
                            class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"
                        >
                            <span>{{ $task['type'] }}</span>
                            @if ($task['grace_time'])
                                <span class="text-xs"
                                >({{ $task['grace_time'] }}m grace)</span
                                >
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-500 dark:text-gray-400">
                            {{ $task['schedule'] }}
                            @if ($task['timezone'] && $task['timezone'] !== 'UTC')
                                <span
                                    class="block text-xs text-gray-400 dark:text-gray-500"
                                >
                                        {{ $task['timezone'] }}
                                    </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-500 dark:text-gray-400">
                            @if ($task['last_started'])
                                <div>{{ Carbon::parse($task['last_started'])->diffForHumans() }}</div>
                                <div
                                    class="text-xs text-gray-400 dark:text-gray-500"
                                >
                                    {{ Carbon::parse($task['last_started'])->format($dateFormat) }}
                                </div>
                                @if ($task['last_finished'])
                                    <div
                                        class="text-xs text-gray-400 dark:text-gray-500"
                                    >
                                        Duration: {{ Carbon::parse($task['last_started'])->diffInSeconds(Carbon::parse($task['last_finished'])) }}
                                        s
                                    </div>
                                @endif
                            @else
                                Never
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-500 dark:text-gray-400">
                            @if ($task['next_run'])
                                <div>{{ Carbon::parse($task['next_run'])->diffForHumans() }}</div>
                                <div
                                    class="text-xs text-gray-400 dark:text-gray-500"
                                >
                                    {{ Carbon::parse($task['next_run'])->format($dateFormat) }}
                                </div>
                            @else
                                Unknown
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <x-admin::task-status :task="$task"/>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
