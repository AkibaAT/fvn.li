<script lang="ts" setup>
import {Link} from '@inertiajs/vue3';

defineProps<{
    links: Array<any>;
}>();
</script>

<template>
    <div v-if="links.length > 3">
        <div
            class="flex flex-wrap -mb-1"
        >
            <template
                v-for="(link, key) in links"
                :key="key"
            >
                <div
                    v-if="link.url === null"
                    class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded border-gray-200 text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-600 select-none"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :class="{ 'bg-blue-500 text-white dark:text-gray-600': link.active }"
                    :href="link.url"
                    class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded border-gray-200 hover:bg-blue-500 hover:text-white dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-blue-500 dark:text-gray-100"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
