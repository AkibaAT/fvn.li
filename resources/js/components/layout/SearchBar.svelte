<script lang="ts">
    import { Button } from '@/components/ui';
    import { useSearch } from '@/hooks/useSearch.svelte';
    import { onMount } from 'svelte';

    interface Props {
        class?: string;
    }

    let { class: className = '' }: Props = $props();

    const currentUrl = typeof window !== 'undefined' ? (window.location?.href ?? '') : '';
    const detectedIsGamesPage = (currentUrl.endsWith('/games') && !currentUrl.includes('/my/games')) || currentUrl.includes('/games?');

    const search = useSearch({ isGamesPage: detectedIsGamesPage });

    let searchInputEl: HTMLInputElement | undefined = $state();

    onMount(() => {
        search.initializeSearchFromUrl();
    });

    function handleFocus() {}

    function handleBlur() {}

    function clearSearch() {
        search.handleSearchClear();
        searchInputEl?.focus();
    }
</script>

<form onsubmit={search.handleSearchSubmit} class="w-full {className}">
    <div class="group relative">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            {#if search.isSearching}
                <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-600 border-t-transparent"></div>
            {:else}
                <i class="icon-magnifier text-gray-400" aria-hidden="true"></i>
            {/if}
        </div>
        <input
            id="global-search-input"
            bind:this={searchInputEl}
            type="text"
            value={search.searchTerm}
            oninput={search.handleSearchChange}
            onfocus={handleFocus}
            onblur={handleBlur}
            name="search"
            placeholder="Search games, authors, tags..."
            class="w-full rounded-lg border border-gray-200 bg-white/80 py-2 pr-32 pl-10 text-sm text-gray-900 placeholder-gray-500 transition-all duration-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700/80 dark:text-white dark:placeholder-gray-400"
            autocomplete="off"
            aria-label="Search games, authors, and tags"
        />
        {#if search.searchTerm}
            <Button
                type="button"
                variant="ghost"
                tone="neutral"
                size="icon-sm"
                onclick={clearSearch}
                class="absolute top-1/2 right-20 -translate-y-1/2 transform rounded-full p-1 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                ariaLabel="Clear search"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </Button>
        {/if}
        <Button
            type="submit"
            variant="solid"
            tone="primary"
            size="sm"
            class="absolute top-1/2 right-1 -translate-y-1/2 transform cursor-pointer rounded-md bg-blue-600 px-3 py-1 text-sm font-medium text-white transition-all duration-200 hover:bg-blue-700"
        >
            Search
        </Button>
    </div>
</form>
