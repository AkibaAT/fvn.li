<script lang="ts">
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
    import { Button } from '@/components/ui';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { useSearch } from '@/hooks/useSearch.svelte';
    import { onMount } from 'svelte';

    interface Props {
        class?: string;
    }

    let { class: className = '' }: Props = $props();

    const search = useSearch();

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
                <LoadingSpinner size="sm" label="Searching games" />
            {:else}
                <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" />
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
                class="absolute top-1/2 right-20 -translate-y-1/2 transform"
                ariaLabel="Clear search"
            >
                <XMarkIcon class="h-4 w-4" />
            </Button>
        {/if}
        <Button type="submit" variant="solid" tone="primary" size="sm" class="absolute top-1/2 right-1 -translate-y-1/2 transform">Search</Button>
    </div>
</form>
