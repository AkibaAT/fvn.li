<script lang="ts" setup>
import DialogHeader from '@/Components/DialogHeader.vue';
import DialogFooter from '@/Components/DialogFooter.vue';
import {ref} from "vue";

interface Props {
    sortField: string;
    sortDirection: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'sort', field: string): void;
}>();

const sortOptions = {
    latest_version_published_at: 'Latest Update',
    initially_published_at: 'Initial Release',
    english_word_count: 'Word Count',
    rating_count: 'Review Count',
    name: 'Name'
};

const dialogElement = ref<HTMLDialogElement | null>(null);

const show = () => {
    dialogElement.value?.showModal();
};

const close = () => {
    dialogElement.value?.close();
};

defineExpose({show, close});
</script>

<template>
    <dialog
        ref="dialogElement"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-sm dark:text-gray-100 backdrop:backdrop-blur-md"
        @click.self="close"
    >
        <DialogHeader title="Sort Games"/>

        <div class="space-y-2">
            <button
                v-for="(label, field) in sortOptions"
                :key="field"
                :class="{ 'bg-gray-50 dark:bg-gray-700': sortField === field }"
                class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between"
                @click="emit('sort', field)"
            >
                <span>{{ label }}</span>
                <span v-if="sortField === field">
          {{ sortDirection === 'asc' ? '↑' : '↓' }}
        </span>
            </button>
        </div>

        <DialogFooter/>
    </dialog>
</template>
