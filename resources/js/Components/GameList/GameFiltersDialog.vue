<script lang="ts" setup>
import {computed, onMounted, onUnmounted, ref} from 'vue';
import DialogHeader from '@/Components/DialogHeader.vue';
import DialogFooter from '@/Components/DialogFooter.vue'; // Correct import
import type {FilterOptions} from '@/types';

interface Props {
    filterOptions: FilterOptions;
    selectedPlatforms: string[];
    selectedStatuses: string[];
    selectedEngines: string[];
    selectedLanguages: string[];
    nsfw: boolean;
    sfw: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'toggleFilter', type: string, value?: string): void;
    (e: 'toggleNsfw'): void;
    (e: 'toggleSfw'): void;
    (e: 'reset-filters'): void; // Define the new event
}>();

interface FilterSection {
    title: string;
    type: string;
    items: Record<string, string | { ref_name: string; flag_code: string }>;
    selected: string[];
    class: string;
}

// Use a computed property for filterSections, so it reacts to prop changes
const filterSections = computed<FilterSection[]>(() => [
    {
        title: 'Languages',
        type: 'language',
        items: props.filterOptions.languages,
        selected: props.selectedLanguages, // Directly use props
        class: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300'
    },
    {
        title: 'Platforms',
        type: 'platform',
        items: props.filterOptions.platforms,
        selected: props.selectedPlatforms, // Directly use props
        class: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
    },
    {
        title: 'Status',
        type: 'status',
        items: props.filterOptions.statuses,
        selected: props.selectedStatuses, // Directly use props
        class: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
    },
    {
        title: 'Game Engine',
        type: 'engine',
        items: props.filterOptions.gameEngines,
        selected: props.selectedEngines, // Directly use props
        class: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'
    }
]);

const isLanguageItem = (
    item: string | { ref_name: string; flag_code: string },
): item is { ref_name: string; flag_code: string } => {
    return typeof item === 'object' && item !== null && 'ref_name' in item && 'flag_code' in item;
};

// Listen for the reset-filters event using onMounted and a standard event listener
onMounted(() => {
    window.addEventListener('reset-filters', handleResetFilters);
});

onUnmounted(() => {
    window.removeEventListener('reset-filters', handleResetFilters);
});

function handleResetFilters() {
    // Reset selected arrays in filterSections by reassigning (for reactivity)
    filterSections.value.forEach(section => {
        section.selected = [];
    });
}

const dialogElement = ref<HTMLDialogElement | null>(null);

const show = () => {
    dialogElement.value?.showModal();
};

const close = () => {
    dialogElement.value?.close();
};

defineExpose({ show, close });
</script>

<template>
    <dialog
        ref="dialogElement"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-2xl dark:text-gray-100 backdrop:backdrop-blur-md"
        @click.self="close"
    >
        <DialogHeader title="Filter Games"/>

        <div class="space-y-6">
            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content Rating</div>
                <div class="flex flex-wrap gap-2">
                    <button :class="[
                                'px-3 py-1 rounded-lg text-sm',
                                props.sfw ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                            ]"
                            @click="emit('toggleSfw')"
                    >
                        SFW
                    </button>
                    <button :class="[
                                'px-3 py-1 rounded-lg text-sm',
                                props.nsfw ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                            ]"
                            @click="emit('toggleNsfw')"
                    >
                        NSFW
                    </button>
                </div>
            </div>

            <template v-for="section in filterSections" :key="section.title">
                <div>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ section.title }}</div>
                    <div class="flex flex-wrap gap-2">
                        <template v-for="(item, value) in section.items" :key="value">
                            <button
                                :class="[
                                    'px-3 py-1 rounded-lg text-sm',
                                    section.selected.includes(value) ? section.class
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                                ]"
                                @click="emit('toggleFilter', section.type, value)"
                            >
                                <template v-if="section.type === 'language' && isLanguageItem(item)">
                                    <span :class="['fi', `fi-${item.flag_code}`, 'rounded-xs mr-1']"/>
                                    {{ item.ref_name }}
                                </template>
                                <template v-else>
                                    {{ typeof item === 'string' ? item : item.ref_name }}
                                </template>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <DialogFooter/>
    </dialog>
</template>
