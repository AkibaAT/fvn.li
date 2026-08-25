<script lang="ts" module>
    export type PaginationMeta = {
        current_page: number;
        last_page: number;
        per_page?: number;
        total: number;
        from?: number | null;
        to?: number | null;
    };
</script>

<script lang="ts">
    import { Button } from '@/components/ui';

    interface Props {
        meta: PaginationMeta;
        loading?: boolean;
        label?: string;
        onChange: (page: number) => void;
        layout?: 'simple' | 'full';
        onPerPageChange?: (perPage: number) => void;
        perPageOptions?: number[];
        noDivider?: boolean;
        class?: string;
        focusOnUpdate?: boolean;
        alwaysShow?: boolean;
        pageSelectLimit?: number;
        buildPageUrl?: (page: number) => string;
    }

    let {
        meta,
        loading = false,
        label = 'items',
        onChange,
        layout = 'simple',
        onPerPageChange,
        perPageOptions = [5, 10, 25, 50],
        noDivider = false,
        class: className = '',
        focusOnUpdate = false,
        alwaysShow = false,
        pageSelectLimit = 200,
        buildPageUrl,
    }: Props = $props();

    let prevButtonEl = $state<HTMLElement | null>(null);
    let nextButtonEl = $state<HTMLElement | null>(null);
    let selectEl = $state<HTMLSelectElement | undefined>(undefined);
    let lastAction: 'prev' | 'next' | 'select' | null = null;

    $effect(() => {
        void meta.current_page;

        if ((focusOnUpdate || layout === 'full') && lastAction) {
            const focusTarget = { prev: prevButtonEl, next: nextButtonEl, select: selectEl }[lastAction];
            if (focusTarget) setTimeout(() => focusTarget.focus(), 50);
            lastAction = null;
        }
    });

    const canPrev = $derived(meta.current_page > 1 && !loading);
    const canNext = $derived(meta.current_page < meta.last_page && !loading);
    const shouldShow = $derived(layout === 'full' || alwaysShow || meta.last_page > 1);

    const pageOptions = $derived.by(() => {
        const totalPages = meta.last_page;
        if (totalPages <= 0) return [];

        const limit = Math.max(10, pageSelectLimit);
        if (totalPages <= limit) return Array.from({ length: totalPages }, (_, index) => ({ value: index + 1, label: `${index + 1}` }));

        const half = Math.floor(limit / 2);
        let start = Math.max(1, meta.current_page - half);
        const end = Math.min(totalPages, start + limit - 1);
        start = Math.max(1, end - limit + 1);

        const options: Array<{ value: number; label: string }> = [];
        if (start > 1) {
            options.push({ value: 1, label: '1' });
            if (start > 2) options.push({ value: start - 1, label: '\u2026' });
        }
        for (let page = start; page <= end; page++) options.push({ value: page, label: `${page}` });
        if (end < totalPages) {
            if (end < totalPages - 1) options.push({ value: end + 1, label: '\u2026' });
            options.push({ value: totalPages, label: `${totalPages}` });
        }
        return options;
    });

    function changePage(page: number, action: typeof lastAction) {
        lastAction = action;
        onChange(page);
    }

    function handlePerPageChange(event: Event) {
        onPerPageChange?.(Number.parseInt((event.target as HTMLSelectElement).value));
    }
</script>

{#snippet info()}
    <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
        {#if typeof meta.from === 'number' && typeof meta.to === 'number'}
            <span>Showing {meta.from} to {meta.to} of {meta.total} {label}</span>
        {:else}
            <span>Page {meta.current_page} of {meta.last_page}</span>
        {/if}
    </div>
{/snippet}

{#snippet controls()}
    <div class="flex flex-wrap items-center justify-center gap-3">
        {#if buildPageUrl && canPrev}
            <Button
                bind:ref={prevButtonEl}
                href={buildPageUrl(meta.current_page - 1)}
                inertia={false}
                variant="outline"
                tone="neutral"
                onclick={(event) => {
                    event.preventDefault();
                    changePage(meta.current_page - 1, 'prev');
                }}
                ariaLabel="Go to page {meta.current_page - 1}">Previous</Button
            >
        {:else}
            <Button
                bind:ref={prevButtonEl}
                type="button"
                variant="outline"
                tone="neutral"
                disabled={!canPrev}
                onclick={() => changePage(meta.current_page - 1, 'prev')}
                ariaLabel="Go to page {meta.current_page - 1}">Previous</Button
            >
        {/if}

        <div class="flex shrink-0 items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">Page</span>
            <select
                bind:this={selectEl}
                value={meta.current_page}
                onchange={(event) => changePage(Number.parseInt((event.target as HTMLSelectElement).value), 'select')}
                disabled={loading}
                class="cursor-pointer rounded border border-gray-200 bg-white px-3 py-1 text-sm text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                aria-label="Select page number"
            >
                {#each pageOptions as option (option.value)}<option value={option.value}>{option.label}</option>{/each}
            </select>
            <span class="text-sm text-gray-500 dark:text-gray-400">of {meta.last_page}</span>
        </div>

        {#if buildPageUrl && canNext}
            <Button
                bind:ref={nextButtonEl}
                href={buildPageUrl(meta.current_page + 1)}
                inertia={false}
                variant="outline"
                tone="neutral"
                onclick={(event) => {
                    event.preventDefault();
                    changePage(meta.current_page + 1, 'next');
                }}
                ariaLabel="Go to page {meta.current_page + 1}">Next</Button
            >
        {:else}
            <Button
                bind:ref={nextButtonEl}
                type="button"
                variant="outline"
                tone="neutral"
                disabled={!canNext}
                onclick={() => changePage(meta.current_page + 1, 'next')}
                ariaLabel="Go to page {meta.current_page + 1}">Next</Button
            >
        {/if}
    </div>
{/snippet}

{#if shouldShow}
    {#if layout === 'full'}
        <div class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700 {className}">
            <div class="grid grid-cols-1 items-center gap-4 lg:grid-cols-[1fr_auto_1fr]">
                <div class="justify-self-center text-center lg:justify-self-start lg:text-left">{@render info()}</div>
                <div class="justify-self-center">{@render controls()}</div>
                <div class="justify-self-center lg:justify-self-end">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Show:</span>
                        <select
                            aria-label={`Number of ${label} per page`}
                            value={meta.per_page || perPageOptions[0]}
                            onchange={handlePerPageChange}
                            disabled={loading || !onPerPageChange}
                            class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            {#each perPageOptions as option (option)}<option value={option}>{option} per page</option>{/each}
                        </select>
                    </div>
                </div>
            </div>
        </div>
    {:else}
        <div
            class="flex flex-wrap items-center justify-between gap-4 {noDivider
                ? 'pt-0'
                : 'mt-6 border-t border-gray-200 pt-4 dark:border-gray-700'} {className}"
        >
            {@render info()}
            {@render controls()}
        </div>
    {/if}
{/if}
