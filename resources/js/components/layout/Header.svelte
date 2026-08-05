<script lang="ts">
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
    import Container from '@/components/Container.svelte';
    import Logo from '@/components/layout/Logo.svelte';
    import Navigation from '@/components/layout/Navigation.svelte';
    import UserMenu from '@/components/layout/UserMenu.svelte';
    import AppearanceDropdown from '@/components/AppearanceDropdown.svelte';
    import { onMount } from 'svelte';

    let showMobileSearch = $state(false);
    let SearchBarComponent = $state<any>(null);
    let MobileSearchComponent = $state<any>(null);

    async function loadSearchBar() {
        SearchBarComponent ??= (await import('@/components/layout/SearchBar.svelte')).default;
    }

    async function loadMobileSearch() {
        MobileSearchComponent ??= (await import('@/components/layout/MobileSearch.svelte')).default;
    }

    onMount(() => {
        const mediaQuery = window.matchMedia('(min-width: 1024px)');
        const updateSearchBar = () => {
            if (mediaQuery.matches) void loadSearchBar();
        };

        updateSearchBar();
        mediaQuery.addEventListener('change', updateSearchBar);

        return () => {
            mediaQuery.removeEventListener('change', updateSearchBar);
        };
    });

    function toggleMobileSearch() {
        if (!showMobileSearch) void loadMobileSearch();
        showMobileSearch = !showMobileSearch;
    }

    function closeMobileSearch() {
        showMobileSearch = false;
    }
</script>

<header
    class="sticky top-0 z-50 border-b border-gray-200/50 bg-white/80 shadow-sm backdrop-blur-xl dark:border-zinc-800 dark:bg-[#0a0f1e]/80"
    aria-label="Main navigation"
>
    <Container>
        <div class="flex items-center justify-between py-4">
            <Logo />

            <Navigation />

            <div class="mx-8 hidden max-w-lg flex-1 lg:flex" role="search">
                {#if SearchBarComponent}
                    <SearchBarComponent />
                {/if}
            </div>

            <div class="flex items-center space-x-2 lg:hidden">
                <button
                    onclick={toggleMobileSearch}
                    aria-expanded={showMobileSearch}
                    aria-controls="mobile-search-bar"
                    aria-label={showMobileSearch ? 'Hide search' : 'Show search'}
                    class="cursor-pointer rounded-lg bg-gray-100 p-2 text-gray-700 transition-colors duration-200 hover:bg-gray-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    {#if showMobileSearch}
                        <XMarkIcon class="h-5 w-5" />
                    {:else}
                        <MagnifyingGlassIcon class="h-4 w-4" />
                    {/if}
                </button>
            </div>

            <div class="flex items-center space-x-3">
                <UserMenu />
                <AppearanceDropdown />
            </div>
        </div>
    </Container>
</header>

{#if MobileSearchComponent}
    <MobileSearchComponent isOpen={showMobileSearch} onClose={closeMobileSearch} />
{/if}
