<template>
    <div class="mt-6 flex justify-end">
        <button
            class="rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-xs ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
            type="button"
            @click="$el.closest('dialog')?.close()"
        >
            Close
        </button>
    </div>
</template>
