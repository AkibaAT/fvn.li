<script lang="ts" setup>
defineProps<{
    title: string;
}>();
</script>

<template>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-medium">{{ title }}</h3>
        <button
            class="text-gray-400 hover:text-gray-500"
            @click="$el.closest('dialog')?.close()"
        >
            <span class="sr-only">Close</span>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>
</template>
