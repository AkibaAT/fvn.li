<script lang="ts">
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import VnListCard from '@/components/VnListCard.svelte';
    import type { VnList } from '@/components/VnListCard.svelte';

    let {
        lists,
        showUser = false,
        emptyMessage,
        isLoading,
        onPageChange,
        onPerPageChange,
        buildPageUrl,
    }: {
        lists: { data: VnList[]; current_page: number; last_page: number; per_page: number; total: number };
        showUser?: boolean;
        emptyMessage: string;
        isLoading: boolean;
        onPageChange: (page: number) => void;
        onPerPageChange: (perPage: number) => void;
        buildPageUrl: (page: number) => string;
    } = $props();
</script>

{#if lists.data.length > 0}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {#each lists.data as list (list.id)}
            <VnListCard {list} {showUser} />
        {/each}
    </div>
{:else}
    <div class="py-12 text-center">
        <h2 class="mb-2 text-lg font-medium text-gray-900 dark:text-white">No public lists found</h2>
        <p class="text-gray-600 dark:text-gray-400">{emptyMessage}</p>
    </div>
{/if}

<AdvancedPagination
    meta={{
        current_page: lists.current_page,
        last_page: lists.last_page,
        total: lists.total,
        from: lists.data.length ? (lists.current_page - 1) * lists.per_page + 1 : 0,
        to: lists.data.length ? (lists.current_page - 1) * lists.per_page + lists.data.length : 0,
        per_page: lists.per_page,
    }}
    {onPageChange}
    {onPerPageChange}
    {isLoading}
    label="results"
    perPageOptions={[8, 16, 24, 32]}
    {buildPageUrl}
/>
