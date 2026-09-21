<script lang="ts" module>
    import type { PaginationMeta } from '@/types';
</script>

<script lang="ts">
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
    import { Button, Select } from '@/components/ui';
    import { shouldIntercept } from '@inertiajs/core';

    interface Props {
        meta: PaginationMeta;
        loading?: boolean;
        label?: string;
        onChange: (page: number) => void;
        layout?: 'simple' | 'full' | 'pages';
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
    let selectWrapEl = $state<HTMLElement | null>(null);
    let lastAction: 'prev' | 'next' | 'select' | null = null;

    $effect(() => {
        void meta.current_page;

        if ((focusOnUpdate || layout === 'full') && lastAction) {
            const focusTarget = { prev: prevButtonEl, next: nextButtonEl, select: selectWrapEl?.querySelector('select') ?? null }[lastAction];
            if (focusTarget) setTimeout(() => focusTarget.focus(), 50);
            lastAction = null;
        }
    });

    const canPrev = $derived(meta.current_page > 1 && !loading);
    const canNext = $derived(meta.current_page < meta.last_page && !loading);
    const hasPages = $derived(meta.last_page > 1);
    const canChangePerPage = $derived(Boolean(onPerPageChange) && meta.total > Math.min(...perPageOptions));
    const shouldShow = $derived(alwaysShow || hasPages || (layout === 'full' && canChangePerPage));

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

    const pageItems = $derived.by(() => {
        const last = Math.max(1, meta.last_page);
        const current = Math.min(Math.max(1, meta.current_page), last);
        if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1);

        const items: Array<number | 'ellipsis'> = [1];
        const start = Math.max(2, current - 1);
        const end = Math.min(last - 1, current + 1);
        if (start > 2) items.push('ellipsis');
        for (let page = start; page <= end; page++) items.push(page);
        if (end < last - 1) items.push('ellipsis');
        items.push(last);
        return items;
    });

    function changePage(page: number, action: typeof lastAction) {
        lastAction = action;
        onChange(page);
    }

    function handlePerPageChange(event: Event) {
        onPerPageChange?.(Number.parseInt((event.target as HTMLSelectElement).value));
    }
</script>

{#snippet pageControl(
    page: number,
    disabled: boolean,
    action: typeof lastAction,
    ariaLabel: string,
    text: string,
    toneClass: string,
    current: boolean = false,
)}
    {@const controlClass = `inline-flex h-7 min-w-7 items-center justify-center rounded-md px-1.5 text-ui transition-colors ${toneClass}`}
    {#if buildPageUrl && !disabled}
        <a
            href={buildPageUrl(page)}
            aria-label={ariaLabel}
            aria-current={current ? 'page' : undefined}
            class={controlClass}
            onclick={(event) => {
                if (!shouldIntercept(event)) return;
                event.preventDefault();
                changePage(page, action);
            }}>{text}</a
        >
    {:else}
        <button
            type="button"
            {disabled}
            onclick={() => changePage(page, action)}
            aria-label={ariaLabel}
            aria-current={current ? 'page' : undefined}
            class="{controlClass} disabled:cursor-not-allowed disabled:opacity-40">{text}</button
        >
    {/if}
{/snippet}

{#snippet info()}
    <div class="flex items-center text-ui text-fg-muted">
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
                size="sm"
                onclick={(event) => {
                    if (!shouldIntercept(event)) return;
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
                size="sm"
                disabled={!canPrev}
                onclick={() => changePage(meta.current_page - 1, 'prev')}
                ariaLabel="Go to page {meta.current_page - 1}">Previous</Button
            >
        {/if}

        <div class="flex shrink-0 items-center gap-2">
            <span class="text-ui text-fg-muted">Page</span>
            <span bind:this={selectWrapEl}>
                <Select
                    value={meta.current_page}
                    onchange={(event) => changePage(Number.parseInt((event.currentTarget as HTMLSelectElement).value), 'select')}
                    disabled={loading}
                    class="w-auto py-1 text-ui"
                    aria-label="Select page number"
                >
                    {#each pageOptions as option (option.value)}<option value={option.value}>{option.label}</option>{/each}
                </Select>
            </span>
            <span class="text-ui text-fg-muted">of {meta.last_page}</span>
        </div>

        {#if buildPageUrl && canNext}
            <Button
                bind:ref={nextButtonEl}
                href={buildPageUrl(meta.current_page + 1)}
                inertia={false}
                variant="outline"
                tone="neutral"
                size="sm"
                onclick={(event) => {
                    if (!shouldIntercept(event)) return;
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
                size="sm"
                disabled={!canNext}
                onclick={() => changePage(meta.current_page + 1, 'next')}
                ariaLabel="Go to page {meta.current_page + 1}">Next</Button
            >
        {/if}
    </div>
{/snippet}

{#if shouldShow}
    {#if layout === 'full'}
        <div class="mt-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-border pt-4 {className}">
            {@render info()}
            {#if hasPages}
                {@render controls()}
            {/if}
            {#if canChangePerPage}
                <Select
                    aria-label={`Number of ${label} per page`}
                    value={meta.per_page || perPageOptions[0]}
                    onchange={handlePerPageChange}
                    disabled={loading}
                    class="w-auto py-1 text-ui"
                >
                    {#each perPageOptions as option (option)}<option value={option}>{option} per page</option>{/each}
                </Select>
            {/if}
        </div>
    {:else if layout === 'pages'}
        <div class="flex flex-wrap items-center gap-1 pt-2 {className}">
            {@render pageControl(meta.current_page - 1, !canPrev, 'prev', 'Previous page', '\u2039', 'text-fg-muted hover:text-fg')}

            {#each pageItems as item, index (index)}
                {#if item === 'ellipsis'}
                    <span class="inline-flex h-7 min-w-7 items-center justify-center text-ui text-fg-faint" aria-hidden="true">&hellip;</span>
                {:else}
                    {@render pageControl(
                        item,
                        false,
                        'select',
                        `Go to page ${item}`,
                        String(item),
                        item === meta.current_page ? 'bg-fg font-semibold text-surface' : 'text-fg-muted hover:text-fg',
                        item === meta.current_page,
                    )}
                {/if}
            {/each}

            {@render pageControl(meta.current_page + 1, !canNext, 'next', 'Next page', '\u203a', 'text-fg-muted hover:text-fg')}

            {#if canChangePerPage}
                <div class="ml-auto flex items-center">
                    <label for="per-page-select" class="sr-only">Items per page</label>
                    <span class="relative flex items-center">
                        <select
                            id="per-page-select"
                            value={meta.per_page || perPageOptions[0]}
                            onchange={handlePerPageChange}
                            disabled={loading}
                            class="h-7 cursor-pointer appearance-none rounded-md bg-transparent pr-5 pl-1 text-ui text-fg-muted hover:text-fg focus:outline-none"
                        >
                            {#each perPageOptions as option (option)}<option value={option}>{option} per page</option>{/each}
                        </select>
                        <ChevronDownIcon class="pointer-events-none absolute right-1 h-3.5 w-3.5 text-fg-muted" />
                    </span>
                </div>
            {/if}
        </div>
    {:else}
        <div class="flex flex-wrap items-center justify-between gap-4 {noDivider ? 'pt-0' : 'mt-6 border-t border-border pt-4'} {className}">
            {@render info()}
            {@render controls()}
        </div>
    {/if}
{/if}
