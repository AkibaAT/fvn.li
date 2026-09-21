<script lang="ts">
    import Pagination from '@/components/Pagination.svelte';
    import RatingRow from '@/components/ratings/RatingRow.svelte';
    import type { RatingRowData } from '@/components/ratings/types';
    import { Card } from '@/components/ui';
    import type { PaginationMeta } from '@/types';
    import type { Snippet } from 'svelte';

    type Props = {
        /** Already-mapped rows, rendered one per RatingRow. */
        rows: RatingRowData[];
        /** Inline style string applied to each row's review container. */
        reviewStyle: string;
        /** Forwarded to RatingRow. */
        showRater?: boolean;
        meta: PaginationMeta;
        onChange: (page: number) => void;
        onPerPageChange?: (perPage: number) => void;
        loading?: boolean;
        label?: string;
        perPageOptions?: number[];
        buildPageUrl?: (page: number) => string;
        /** Extra classes for the wrapping Card (e.g. "overflow-hidden"). */
        class?: string;
        /** Content rendered above the list, inside the Card. */
        header?: Snippet;
        /** Rendered inside the divide container when there are no rows. */
        empty?: Snippet;
    };

    let {
        rows,
        reviewStyle,
        showRater = false,
        meta,
        onChange,
        onPerPageChange,
        loading,
        label,
        perPageOptions,
        buildPageUrl,
        class: className = '',
        header,
        empty,
    }: Props = $props();
</script>

<Card variant="flat" padding="none" class={className}>
    {#if header}
        {@render header()}
    {/if}
    <div class="divide-y divide-border">
        {#if rows.length === 0 && empty}
            {@render empty()}
        {:else}
            {#each rows as row (row.id)}<RatingRow {row} {reviewStyle} {showRater} />{/each}
        {/if}
    </div>
    <div class="p-4">
        <Pagination layout="full" {meta} {onChange} {onPerPageChange} {loading} {label} {perPageOptions} {buildPageUrl} />
    </div>
</Card>
