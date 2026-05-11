<script lang="ts">
    import Pagination from '@/components/Pagination.svelte';
    import type { PaginationMeta } from '@/components/Pagination.svelte';

    let {
        meta,
        onPageChange,
        onPerPageChange,
        isLoading = false,
        label = 'results',
        perPageOptions = [5, 10, 25, 50],
        class: className = '',
        buildPageUrl,
    }: {
        meta: PaginationMeta;
        onPageChange: (page: number) => void;
        onPerPageChange: (perPage: number) => void;
        isLoading?: boolean;
        label?: string;
        perPageOptions?: number[];
        class?: string;
        buildPageUrl?: (page: number) => string;
    } = $props();
</script>

<div class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700 {className}">
    <div class="grid grid-cols-1 items-center gap-4 sm:grid-cols-3">
        <div class="justify-self-start">
            <Pagination
                {meta}
                {label}
                noDivider
                variant="info"
                alwaysShow
                onChange={onPageChange}
                loading={isLoading}
                focusOnUpdate={false}
                {buildPageUrl}
            />
        </div>
        <div class="justify-self-center">
            <Pagination
                {meta}
                noDivider
                variant="controls"
                alwaysShow
                onChange={onPageChange}
                loading={isLoading}
                focusOnUpdate={true}
                {buildPageUrl}
            />
        </div>
        <div class="justify-self-end">
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-700 dark:text-gray-300">Show:</span>
                <select
                    aria-label={`Number of ${label} per page`}
                    value={meta.per_page || perPageOptions[0]}
                    onchange={(e) => onPerPageChange(parseInt((e.target as HTMLSelectElement).value))}
                    disabled={isLoading}
                    class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    {#each perPageOptions as option (option)}
                        <option value={option}>{option} per page</option>
                    {/each}
                </select>
            </div>
        </div>
    </div>
</div>
