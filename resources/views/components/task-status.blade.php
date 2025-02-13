<div>

</div>
@props(['task'])

@php
    $hasFailedRecently = $task['last_failed'] && (!$task['last_finished'] || \Carbon\Carbon::parse($task['last_failed'])->gt(\Carbon\Carbon::parse($task['last_finished'])));
    $hasRunRecently = $task['last_finished'] && \Carbon\Carbon::parse($task['last_finished'])->gt(now()->subDay());

    $statusColor = $hasFailedRecently ? 'red' : ($hasRunRecently ? 'green' : ($task['last_started'] ? 'yellow' : 'gray'));
    $statusText = $hasFailedRecently ? 'Failed' : ($hasRunRecently ? 'Active' : ($task['last_started'] ? 'Inactive' : 'Never Run'));
@endphp

<div class="space-y-1">
    <div class="flex items-center gap-2">
        <span @class([
            'inline-flex rounded-full px-2 text-xs leading-5 font-semibold',
            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => $statusColor === 'red',
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $statusColor === 'green',
            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' => $statusColor === 'gray',
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => $statusColor === 'yellow',
        ])>
            {{ $statusText }}
        </span>
        @if ($task['last_failed'] && $hasFailedRecently)
            <span class="text-xs text-gray-400 dark:text-gray-500">
                {{ \Carbon\Carbon::parse($task['last_failed'])->diffForHumans() }}
            </span>
        @endif
    </div>

    @if ($task['latest_log']?->meta['failure_message'] ?? false)
        <div class="max-w-xs truncate text-xs text-red-600 group-hover:whitespace-normal dark:text-red-400">
            {{ $task['latest_log']['meta']['failure_message'] }}
        </div>
    @endif

    {{-- Flags --}}
    <div class="flex flex-wrap gap-1">
        @if ($task['runs_on_one_server'])
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                Single Server
            </span>
        @endif
        @if ($task['runs_in_maintenance'])
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                Maintenance OK
            </span>
        @endif
    </div>
</div>
