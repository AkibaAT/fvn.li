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
    let {
        meta,
        loading = false,
        label = 'items',
        onChange,
        noDivider = false,
        class: className = '',
        variant = 'full',
        focusOnUpdate = false,
        alwaysShow = false,
        pageSelectLimit = 200,
        buildPageUrl,
    }: {
        meta: PaginationMeta;
        loading?: boolean;
        label?: string;
        onChange: (page: number) => void;
        noDivider?: boolean;
        class?: string;
        variant?: 'full' | 'info' | 'controls';
        focusOnUpdate?: boolean;
        alwaysShow?: boolean;
        pageSelectLimit?: number;
        buildPageUrl?: (page: number) => string;
    } = $props();

    let prevButtonEl = $state<HTMLButtonElement | HTMLAnchorElement | undefined>(undefined);
    let nextButtonEl = $state<HTMLButtonElement | HTMLAnchorElement | undefined>(undefined);
    let selectEl = $state<HTMLSelectElement | undefined>(undefined);
    let lastAction: 'prev' | 'next' | 'select' | null = null;

    $effect(() => {
        // Track current_page changes for focus management
        void meta.current_page;

        if (focusOnUpdate && lastAction) {
            const focusTarget = {
                prev: prevButtonEl,
                next: nextButtonEl,
                select: selectEl,
            }[lastAction];

            if (focusTarget) {
                setTimeout(() => {
                    (focusTarget as HTMLElement).focus();
                }, 50);
            }
            lastAction = null;
        }
    });

    const canPrev = $derived(meta.current_page > 1 && !loading);
    const canNext = $derived(meta.current_page < meta.last_page && !loading);
    const containerBase = 'flex items-center justify-between';
    const framed = 'mt-6 border-t border-gray-200 dark:border-gray-700 pt-4';
    const unframed = 'pt-0';
    const containerClass = $derived(`${containerBase} ${noDivider ? unframed : framed} ${className}`.trim());

    const shouldShow = $derived(alwaysShow || (meta && meta.last_page > 1));

    function handlePrevious() {
        lastAction = 'prev';
        onChange(meta.current_page - 1);
    }

    function handleNext() {
        lastAction = 'next';
        onChange(meta.current_page + 1);
    }

    function handleSelectChange(e: Event) {
        lastAction = 'select';
        onChange(parseInt((e.target as HTMLSelectElement).value));
    }

    // Compute page options for the select
    const pageOptions = $derived.by(() => {
        const totalPages = meta.last_page;
        if (totalPages <= 0) return [];

        const limit = Math.max(10, pageSelectLimit);
        if (totalPages <= limit) {
            return Array.from({ length: totalPages }, (_, i) => ({
                value: i + 1,
                label: `${i + 1}`,
                isEllipsis: false,
            }));
        }

        const half = Math.floor(limit / 2);
        let start = Math.max(1, meta.current_page - half);
        const end = Math.min(totalPages, start + limit - 1);
        start = Math.max(1, end - limit + 1);

        const out: Array<{ value: number; label: string; isEllipsis: boolean }> = [];
        if (start > 1) {
            out.push({ value: 1, label: '1', isEllipsis: false });
            if (start > 2) {
                out.push({ value: start - 1, label: '\u2026', isEllipsis: true });
            }
        }

        for (let p = start; p <= end; p++) {
            out.push({ value: p, label: `${p}`, isEllipsis: false });
        }

        if (end < totalPages) {
            if (end < totalPages - 1) {
                out.push({ value: end + 1, label: '\u2026', isEllipsis: true });
            }
            out.push({ value: totalPages, label: `${totalPages}`, isEllipsis: false });
        }

        return out;
    });
</script>

{#if shouldShow}
    {#if variant === 'info'}
        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
            {#if typeof meta.from === 'number' && typeof meta.to === 'number'}
                <span>Showing {meta.from} to {meta.to} of {meta.total} {label}</span>
            {:else}
                <span>Page {meta.current_page} of {meta.last_page}</span>
            {/if}
        </div>
    {:else if variant === 'controls'}
        <div class="flex items-center space-x-3 {className}">
            {#if buildPageUrl && canPrev}
                <a
                    bind:this={prevButtonEl}
                    href={buildPageUrl(meta.current_page - 1)}
                    onclick={(e) => {
                        e.preventDefault();
                        handlePrevious();
                    }}
                    class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label="Go to page {meta.current_page - 1}"
                >
                    Previous
                </a>
            {:else}
                <button
                    bind:this={prevButtonEl}
                    onclick={handlePrevious}
                    disabled={!canPrev}
                    class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label="Go to page {meta.current_page - 1}"
                >
                    Previous
                </button>
            {/if}
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">Page</span>
                <select
                    bind:this={selectEl}
                    value={meta.current_page}
                    onchange={handleSelectChange}
                    disabled={loading}
                    class="cursor-pointer rounded border border-gray-200 bg-white px-3 py-1 text-sm text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    aria-label="Select page number"
                >
                    {#each pageOptions as opt (opt.value)}
                        <option value={opt.value}>{opt.label}</option>
                    {/each}
                </select>
                <span class="text-sm text-gray-500 dark:text-gray-400">of {meta.last_page}</span>
            </div>
            {#if buildPageUrl && canNext}
                <a
                    bind:this={nextButtonEl}
                    href={buildPageUrl(meta.current_page + 1)}
                    onclick={(e) => {
                        e.preventDefault();
                        handleNext();
                    }}
                    class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label="Go to page {meta.current_page + 1}"
                >
                    Next
                </a>
            {:else}
                <button
                    bind:this={nextButtonEl}
                    onclick={handleNext}
                    disabled={!canNext}
                    class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label="Go to page {meta.current_page + 1}"
                >
                    Next
                </button>
            {/if}
        </div>
    {:else}
        <!-- full variant -->
        <div class={containerClass}>
            <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                {#if typeof meta.from === 'number' && typeof meta.to === 'number'}
                    <span>Showing {meta.from} to {meta.to} of {meta.total} {label}</span>
                {:else}
                    <span>Page {meta.current_page} of {meta.last_page}</span>
                {/if}
            </div>
            <div class="flex items-center space-x-3">
                {#if buildPageUrl && canPrev}
                    <a
                        bind:this={prevButtonEl}
                        href={buildPageUrl(meta.current_page - 1)}
                        onclick={(e) => {
                            e.preventDefault();
                            handlePrevious();
                        }}
                        class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                        aria-label="Go to page {meta.current_page - 1}"
                    >
                        Previous
                    </a>
                {:else}
                    <button
                        bind:this={prevButtonEl}
                        onclick={handlePrevious}
                        disabled={!canPrev}
                        class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                        aria-label="Go to page {meta.current_page - 1}"
                    >
                        Previous
                    </button>
                {/if}
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Page</span>
                    <select
                        bind:this={selectEl}
                        value={meta.current_page}
                        onchange={handleSelectChange}
                        disabled={loading}
                        class="cursor-pointer rounded border border-gray-200 bg-white px-3 py-1 text-sm text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        aria-label="Select page number"
                    >
                        {#each pageOptions as opt (opt.value)}
                            <option value={opt.value}>{opt.label}</option>
                        {/each}
                    </select>
                    <span class="text-sm text-gray-500 dark:text-gray-400">of {meta.last_page}</span>
                </div>
                {#if buildPageUrl && canNext}
                    <a
                        bind:this={nextButtonEl}
                        href={buildPageUrl(meta.current_page + 1)}
                        onclick={(e) => {
                            e.preventDefault();
                            handleNext();
                        }}
                        class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                        aria-label="Go to page {meta.current_page + 1}"
                    >
                        Next
                    </a>
                {:else}
                    <button
                        bind:this={nextButtonEl}
                        onclick={handleNext}
                        disabled={!canNext}
                        class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                        aria-label="Go to page {meta.current_page + 1}"
                    >
                        Next
                    </button>
                {/if}
            </div>
        </div>
    {/if}
{/if}
