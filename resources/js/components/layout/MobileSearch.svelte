<script lang="ts">
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
    import { Button } from '@/components/ui';
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
    <div class="border-b border-gray-200/50 bg-white/95 p-4 backdrop-blur-xl lg:hidden dark:border-gray-700/50 dark:bg-gray-900/95">
        <div class="relative">
            <form onsubmit={handleSubmit} class="w-full">
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        {#if search.isSearching}
                            <LoadingSpinner size="sm" label="Searching games" />
                        {:else}
                            <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" />
                        {/if}
                    </div>
                    <input
                        bind:this={inputEl}
                        type="text"
                        value={search.searchTerm}
                        oninput={search.handleSearchChange}
                        placeholder="Search games, authors, tags..."
                        class="w-full rounded-lg border border-gray-200 bg-white py-3 pr-32 pl-10 text-gray-900 placeholder-gray-500 transition-all duration-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400"
                        autocomplete="off"
                    />
                    {#if search.searchTerm}
                        <Button
                            type="button"
                            variant="ghost"
                            tone="neutral"
                            size="icon-sm"
                            onclick={search.handleSearchClear}
                            class="absolute top-1/2 right-20 -translate-y-1/2 transform"
                            ariaLabel="Clear search"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </Button>
                    {/if}
                    <Button type="submit" variant="solid" tone="primary" size="sm" class="absolute top-1/2 right-2 -translate-y-1/2 transform">
                        Search
                    </Button>
                </div>
            </form>
        </div>
    </div>
{/if}
