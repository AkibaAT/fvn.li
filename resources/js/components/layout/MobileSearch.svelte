<script lang="ts">
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { useSearch } from '@/hooks/useSearch.svelte';
    import { onMount } from 'svelte';

    interface Props {
        isOpen: boolean;
        onClose: () => void;
    }

    let { isOpen, onClose }: Props = $props();

    const currentUrl = typeof window !== 'undefined' ? (window.location?.href ?? '') : '';
    const detectedIsGamesPage = (currentUrl.endsWith('/games') && !currentUrl.includes('/my/games')) || currentUrl.includes('/games?');

    const search = useSearch({ isGamesPage: detectedIsGamesPage });

    let inputEl: HTMLInputElement | undefined = $state();

    onMount(() => {
        search.initializeSearchFromUrl();
    });

    $effect(() => {
        if (isOpen && inputEl) {
            setTimeout(() => {
                inputEl?.focus();
            }, 100);
        }
    });

    function handleSubmit(e: Event) {
        search.handleSearchSubmit(e);
        onClose();
    }
</script>

{#if isOpen}
    <div class="border-b border-border bg-surface p-4 lg:hidden">
        <form onsubmit={handleSubmit} class="w-full">
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5">
                    {#if search.isSearching}
                        <LoadingSpinner size="sm" label="Searching games" />
                    {:else}
                        <MagnifyingGlassIcon class="h-4 w-4 text-fg-faint" />
                    {/if}
                </div>
                <input
                    bind:this={inputEl}
                    type="text"
                    value={search.searchTerm}
                    oninput={search.handleSearchChange}
                    placeholder="Search titles, authors, tags"
                    class="h-9 w-full rounded-md border border-border bg-page pr-20 pl-8 text-[13px] text-fg transition-colors placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
                    autocomplete="off"
                    aria-label="Search titles, authors, and tags"
                />
                {#if search.searchTerm}
                    <button
                        type="button"
                        onclick={search.handleSearchClear}
                        class="absolute top-1/2 right-14 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded text-fg-faint transition-colors hover:text-fg"
                        aria-label="Clear search"
                    >
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                {/if}
                <button
                    type="submit"
                    class="absolute top-1/2 right-1 -translate-y-1/2 rounded-md bg-fg px-3 py-1.5 text-[13px] font-medium text-surface"
                >
                    Search
                </button>
            </div>
        </form>
    </div>
{/if}
