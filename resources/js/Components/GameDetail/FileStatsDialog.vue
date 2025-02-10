<script lang="ts" setup>
import DialogHeader from '@/Components/DialogHeader.vue';
import DialogFooter from '@/Components/DialogFooter.vue';
import {ref} from 'vue';

interface Props {
    stats: Array<{
        category: string;
        total_count: number;
        total_size: number;
        file_types: Array<{
            extension: string;
            count: number;
            size: number;
        }>;
    }> | null;
    loading: boolean;
}

const props = defineProps<Props>();

const dialogElement = ref<HTMLDialogElement | null>(null);

const show = () => {
    dialogElement.value?.showModal();
};

const close = () => {
    dialogElement.value?.close();
};

defineExpose({show, close});

const formatBytes = (bytes: number): string => {
    if (bytes === 0) return '0 B';
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`;
};
</script>

<template>
    <dialog
        ref="dialogElement"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl min-w-80 max-w-6xl dark:text-gray-100 backdrop:backdrop-blur-md"
        @click.self="close"
    >
        <DialogHeader title="File Statistics"/>

        <div v-if="loading" class="flex items-center justify-center p-4">
            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-gray-900 dark:border-gray-100"/>
        </div>

        <template v-else-if="stats">
            <div class="space-y-6">
                <!-- Summary -->
                <div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div
                            v-for="category in stats"
                            :key="category.category"
                            class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg"
                        >
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ category.category.charAt(0).toUpperCase() + category.category.slice(1) }}
                            </div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ category.total_count.toLocaleString() }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ formatBytes(category.total_size) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Breakdown -->
                <div class="space-y-6">
                    <div v-for="category in stats" :key="category.category">
                        <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ category.category.charAt(0).toUpperCase() + category.category.slice(1) }} Files
                        </h4>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Count
                                    </th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Size
                                    </th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                <tr v-for="type in category.file_types" :key="type.extension">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                        {{ type.extension }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">
                                        {{ type.count.toLocaleString() }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">
                                        {{ formatBytes(type.size) }}
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <DialogFooter/>
    </dialog>
</template>
