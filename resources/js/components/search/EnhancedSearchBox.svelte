<script lang="ts">
    import { Button } from '@/components/ui';
    import { useEnhancedSearch, type SearchFilters, type GlobalSearchResponse, type SearchResponse } from '@/hooks/useEnhancedSearch.svelte';

    interface Props {
        type: 'games' | 'dialogue' | 'global';
        placeholder?: string;
        filters?: SearchFilters;
        onResults?: (results: SearchResponse | GlobalSearchResponse) => void;
        onError?: (error: string) => void;
        class?: string;
        showClearButton?: boolean;
        autoFocus?: boolean;
        debounceMs?: number;
    }

    let {
        type,
        placeholder = 'Search...',
        filters = {},
        onResults,
        onError,
        class: className = '',
        showClearButton = true,
        autoFocus = false,
        debounceMs = 300,
    }: Props = $props();

    let query = $state('');
    let isFocused = $state(false);
    let inputRef = $state<HTMLInputElement | undefined>(undefined);

    $effect(() => {
        if (autoFocus && inputRef) {
            inputRef.focus();
        }
    });

    const searchHook = useEnhancedSearch(() => ({ type, debounceMs }));

    $effect(() => {
        if (onResults) {
            if (type === 'global' && searchHook.globalResults) {
                onResults({ success: true, data: searchHook.globalResults, search_engine: 'meilisearch' });
            } else if (searchHook.results.length > 0 || query.trim() === '') {
                onResults({ success: true, data: searchHook.results, pagination: searchHook.pagination, search_engine: 'meilisearch' });
            }
        }
    });

    $effect(() => {
        if (searchHook.error && onError) {
            onError(searchHook.error);
        }
    });

    function handleInputChange(e: Event) {
        const value = (e.target as HTMLInputElement).value;
        query = value;
        if (value.trim()) {
            searchHook.search(value, filters);
        } else {
            searchHook.clear();
        }
    }

    function handleClear() {
        query = '';
        searchHook.clear();
    }

    function handleSubmit(e: Event) {
        e.preventDefault();
        if (query.trim()) {
            searchHook.search(query, filters);
        }
    }
</script>

<form onsubmit={handleSubmit} class="relative {className}">
    <div class="relative">
        <!-- Search Icon -->
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <svg
                class="h-5 w-5 transition-colors duration-200 {isFocused || query ? 'text-blue-500' : 'text-gray-400'}"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>

        <!-- Input Field -->
        <input
            bind:this={inputRef}
            type="text"
            value={query}
            oninput={handleInputChange}
            onfocus={() => (isFocused = true)}
            onblur={() => (isFocused = false)}
            {placeholder}
            class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pr-12 pl-10 text-gray-900 placeholder-gray-500 transition-all duration-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 dark:focus:border-blue-500 dark:focus:ring-blue-500 {searchHook.loading
                ? 'pr-16'
                : ''}"
        />

        <!-- Loading Spinner -->
        {#if searchHook.loading}
            <div class="absolute inset-y-0 right-10 flex items-center">
                <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>
            </div>
        {/if}

        <!-- Clear Button -->
        {#if showClearButton && query && !searchHook.loading}
            <Button
                type="button"
                onclick={handleClear}
                variant="ghost"
                tone="neutral"
                size="icon-sm"
                ariaLabel="Clear search"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors duration-200 hover:text-gray-600 dark:hover:text-gray-300"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </Button>
        {/if}
    </div>

    <!-- Error Message -->
    {#if searchHook.error}
        <div
            class="absolute top-full right-0 left-0 mt-1 rounded-md border border-red-200 bg-red-50 p-2 text-sm text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            {searchHook.error}
        </div>
    {/if}

    <!-- Search Engine Badge -->
    {#if searchHook.results.length > 0 || searchHook.globalResults}
        <div class="absolute top-full right-0 mt-1">
            <span
                class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/20 dark:text-blue-400"
            >
                Meilisearch
            </span>
        </div>
    {/if}
</form>
