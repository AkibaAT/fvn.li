<script lang="ts">
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

<!-- Modern Header -->
<header
    class="sticky top-0 z-50 border-b border-gray-200/50 bg-white/80 shadow-sm backdrop-blur-xl dark:border-zinc-800 dark:bg-[#0a0f1e]/80"
    aria-label="Main navigation"
>
    <Container>
        <div class="flex items-center justify-between py-4">
            <!-- Logo & Brand -->
            <Logo />

            <!-- Navigation -->
            <Navigation />

            <!-- Search Bar -->
            <div class="mx-8 hidden max-w-lg flex-1 lg:flex" role="search">
                {#if SearchBarComponent}
                    <SearchBarComponent />
                {/if}
            </div>

            <!-- Mobile Search Button (toggle) -->
            <div class="flex items-center space-x-2 lg:hidden">
                <button
                    onclick={toggleMobileSearch}
                    aria-expanded={showMobileSearch}
                    aria-controls="mobile-search-bar"
                    aria-label={showMobileSearch ? 'Hide search' : 'Show search'}
                    class="cursor-pointer rounded-lg bg-gray-100 p-2 text-gray-700 transition-colors duration-200 hover:bg-gray-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    {#if showMobileSearch}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    {:else}
                        <i class="icon-magnifier" aria-hidden="true"></i>
                    {/if}
                </button>
            </div>

            <!-- User Menu -->
            <div class="flex items-center space-x-3">
                <UserMenu />
                <AppearanceDropdown />
            </div>
        </div>
    </Container>
</header>

<!-- Mobile Search Modal -->
{#if MobileSearchComponent}
    <MobileSearchComponent isOpen={showMobileSearch} onClose={closeMobileSearch} />
{/if}
