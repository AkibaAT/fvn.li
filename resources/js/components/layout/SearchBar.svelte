<script lang="ts">
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
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

    function clearSearch() {
        search.handleSearchClear();
        searchInputEl?.focus();
    }
</script>

<form onsubmit={search.handleSearchSubmit} class="flex w-full items-center gap-1.5 {className}">
    <div class="relative min-w-0 flex-1">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5">
            {#if search.isSearching}
                <LoadingSpinner size="sm" label="Searching games" />
            {:else}
                <MagnifyingGlassIcon class="h-4 w-4 text-fg-faint" />
            {/if}
        </div>
        <input
            id="global-search-input"
            bind:this={searchInputEl}
            type="text"
            value={search.searchTerm}
            oninput={search.handleSearchChange}
            name="search"
            placeholder="Search titles, authors, tags"
            class="h-8 w-full rounded-md border border-border bg-page pr-8 pl-8 text-[13px] text-fg transition-colors placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
            autocomplete="off"
            aria-label="Search titles, authors, and tags"
        />
        {#if search.searchTerm}
            <button
                type="button"
                onclick={clearSearch}
                class="absolute top-1/2 right-1.5 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded text-fg-faint transition-colors hover:text-fg"
                aria-label="Clear search"
            >
                <XMarkIcon class="h-4 w-4" />
            </button>
        {/if}
    </div>
    <button
        type="submit"
        class="inline-flex h-8 shrink-0 items-center justify-center rounded-md bg-fg px-3 text-[13px] font-medium text-surface transition-opacity hover:opacity-90"
    >
        Search
    </button>
</form>
