<script lang="ts">
    import Pagination from '@/components/Pagination.svelte';
    import VnListCard from '@/components/VnListCard.svelte';
    import { EmptyState } from '@/components/ui';
    import { buildPageMeta } from '@/utils/pagination';
    import type { VnList } from '@/types/lists';

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
    <EmptyState title="No public lists found" description={emptyMessage} />
{/if}

<Pagination
    layout="full"
    meta={buildPageMeta(lists, lists.data)}
    onChange={onPageChange}
    {onPerPageChange}
    loading={isLoading}
    label="results"
    perPageOptions={[8, 16, 24, 32]}
    {buildPageUrl}
/>
