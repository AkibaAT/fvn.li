<script setup lang="ts">
import type { MonitoredTask } from '@/types/system';
import { timeDiff } from '@/utils/formatters';
import { computed } from 'vue';

const props = defineProps<{
    task: MonitoredTask;
}>();

const hasFailedRecently = computed(() => {
    if (!props.task.last_failed) return false;
    return (
        !props.task.last_finished ||
        new Date(props.task.last_failed) > new Date(props.task.last_finished)
    );
});

const hasRunRecently = computed(() => {
    if (!props.task.last_finished) return false;
    return (
        new Date(props.task.last_finished).getTime() >
        Date.now() - 24 * 60 * 60 * 1000
    );
});

const getStatusColor = computed(() => {
    if (hasFailedRecently.value) return 'red';
    if (hasRunRecently.value) return 'green';
    if (!props.task.last_started) return 'gray';
    return 'yellow';
});

const getStatusText = computed(() => {
    if (hasFailedRecently.value) return 'Failed';
    if (hasRunRecently.value) return 'Active';
    if (!props.task.last_started) return 'Never Run';
    return 'Inactive';
});
</script>

<template>
    <div class="space-y-1">
        <!-- Main status -->
        <div class="flex items-center gap-2">
            <span
                :class="{
                    'inline-flex rounded-full px-2 text-xs leading-5 font-semibold': true,
                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200':
                        getStatusColor === 'red',
                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':
                        getStatusColor === 'green',
                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200':
                        getStatusColor === 'gray',
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200':
                        getStatusColor === 'yellow',
                }"
            >
                {{ getStatusText }}
            </span>
            <span
                v-if="task.last_failed && hasFailedRecently"
                class="text-xs text-gray-400 dark:text-gray-500"
            >
                {{ timeDiff(task.last_failed) }}
            </span>
        </div>

        <!-- Error message -->
        <div
            v-if="task.latest_log?.meta?.failure_message"
            class="max-w-xs truncate text-xs text-red-600 group-hover:whitespace-normal dark:text-red-400"
        >
            {{ task.latest_log.meta.failure_message }}
        </div>

        <!-- Additional flags -->
        <div class="flex flex-wrap gap-1">
            <span
                v-if="task.runs_on_one_server"
                class="inline-flex rounded-full bg-blue-100 px-2 text-xs leading-5 font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200"
            >
                Single Server
            </span>

            <span
                v-if="task.runs_in_maintenance"
                class="inline-flex rounded-full bg-purple-100 px-2 text-xs leading-5 font-semibold text-purple-800 dark:bg-purple-900 dark:text-purple-200"
            >
                Maintenance OK
            </span>

            <span
                v-if="task.registered_on_oh_dear"
                class="inline-flex rounded-full bg-indigo-100 px-2 text-xs leading-5 font-semibold text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200"
            >
                Oh Dear
            </span>

            <span
                v-if="task.grace_time_in_minutes"
                class="inline-flex rounded-full bg-amber-100 px-2 text-xs leading-5 font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200"
            >
                Grace: {{ task.grace_time_in_minutes }}m
            </span>

            <span
                v-if="task.last_skipped"
                class="inline-flex rounded-full bg-gray-100 px-2 text-xs leading-5 font-semibold text-gray-800 dark:bg-gray-700 dark:text-gray-200"
            >
                Skipped
            </span>
        </div>
    </div>
</template>
