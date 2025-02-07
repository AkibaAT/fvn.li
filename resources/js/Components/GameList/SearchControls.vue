<script lang="ts" setup>
interface Props {
    modelValue: string;
    sortField: string;
    sortDirection: string;
    hasFilters: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'openSort'): void;
    (e: 'openFilters'): void;
}>();
</script>

<template>
    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <input
                :value="modelValue"
                class="px-4 py-3 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs"
                placeholder="Search games, authors, or tags..."
                type="search"
                @input="e => emit('update:modelValue', (e.target as HTMLInputElement).value)"
            >
        </div>
        <div class="flex gap-2">
            <button
                class="px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 shadow-xs"
                @click="emit('openSort')"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 4H16M3 8H12M3 12H12M17 8V20M17 20L13 16M17 20L21 16" stroke="currentColor"
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                </svg>
                Sort
                <span v-if="sortField !== 'latest_version_published_at' || sortDirection !== 'desc'"
                      class="w-2 h-2 rounded-full bg-blue-500"/>
            </button>
            <button
                class="px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 shadow-xs"
                @click="emit('openFilters')"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                        stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                </svg>
                Filters
                <span v-if="hasFilters" class="w-2 h-2 rounded-full bg-blue-500"/>
            </button>
        </div>
    </div>
</template>
