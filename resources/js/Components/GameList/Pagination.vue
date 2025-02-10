<script lang="ts" setup>
defineProps<{
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}>();

const emit = defineEmits<{
    (e: 'page-click', url: string): void;
}>();

const getPageFromUrl = (url: string): number => {
    try {
        const parsedUrl = new URL(url);
        const page = parsedUrl.searchParams.get('page');
        return page ? parseInt(page, 10) : 1;
    } catch (e) {
        console.error('Error parsing page from URL:', e);
        return 1;
    }
};
</script>

<template>
    <div v-if="links.length > 3">
        <div class="flex flex-wrap -mb-1">
            <template v-for="(link, key) in links" :key="key">
                <div
                    v-if="link.url === null"
                    class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded border-gray-200 text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-600 select-none"
                    v-html="link.label"
                />
                <button
                    v-else
                    :class="[
                        'mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded border-gray-200 hover:bg-blue-500 hover:text-white dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-blue-500 dark:text-gray-100',
                        { 'bg-blue-500 text-gray-400 dark:text-gray-600': link.active }
                    ]"
                    @click="emit('page-click', link.url)"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
