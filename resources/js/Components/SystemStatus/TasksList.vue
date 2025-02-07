<script lang="ts" setup>
import type {MonitoredTask} from '@/types/system';
import {calculateDurationSeconds, formatDate, timeDiff} from '@/utils/formatters';
import TaskStatus from './TaskStatus.vue';

defineProps<{
    monitoredTasks: MonitoredTask[];
    dateFormat: string;
}>();

</script>

<template>
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
                <tr
                    v-for="task in monitoredTasks"
                    :key="task.name"
                    class="group hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                    <td class="px-6 py-4 text-sm">
                        <div class="font-medium text-gray-900 dark:text-gray-100">
                            {{ task.name }}
                        </div>
                        <div
                            class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"
                        >
                            <span>{{ task.type }}</span>
                            <span v-if="task.grace_time_in_minutes" class="text-xs"
                            >(Grace: {{ task.grace_time_in_minutes }}m)</span
                            >
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-500 dark:text-gray-400">
                            {{ task.schedule }}
                            <span
                                v-if="task.timezone && task.timezone !== 'UTC'"
                                class="block text-xs text-gray-400 dark:text-gray-500"
                            >
                                    {{ task.timezone }}
                                </span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-500 dark:text-gray-400">
                            <template v-if="task.last_started">
                                <div>{{ timeDiff(task.last_started) }}</div>
                                <div
                                    class="text-xs text-gray-400 dark:text-gray-500"
                                >
                                    {{ formatDate(task.last_started) }}
                                </div>
                                <div
                                    v-if="task.last_finished"
                                    class="text-xs text-gray-400 dark:text-gray-500"
                                >
                                    Duration:
                                    {{ calculateDurationSeconds(task.last_started, task.last_finished) }}s
                                </div>
                            </template>
                            <template v-else>Never</template>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-500 dark:text-gray-400">
                            <template v-if="task.next_run">
                                <div>{{ timeDiff(task.next_run) }}</div>
                                <div
                                    class="text-xs text-gray-400 dark:text-gray-500"
                                >
                                    {{ formatDate(task.next_run) }}
                                </div>
                            </template>
                            <template v-else>Unknown</template>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <TaskStatus :task="task"/>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
