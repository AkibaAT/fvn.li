<script lang="ts">
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
    import Container from '@/components/Container.svelte';
    import Logo from '@/components/layout/Logo.svelte';
    import Navigation from '@/components/layout/Navigation.svelte';
    import UserMenu from '@/components/layout/UserMenu.svelte';
    import AppearanceDropdown from '@/components/AppearanceDropdown.svelte';
    import SearchBar from '@/components/layout/SearchBar.svelte';

    let showMobileSearch = $state(false);
    let MobileSearchComponent = $state<any>(null);

    async function loadMobileSearch() {
        MobileSearchComponent ??= (await import('@/components/layout/MobileSearch.svelte')).default;
    }

    function toggleMobileSearch() {
        if (!showMobileSearch) void loadMobileSearch();
        showMobileSearch = !showMobileSearch;
    }

    function closeMobileSearch() {
        showMobileSearch = false;
    }
</script>

<header class="sticky top-0 z-50 h-14 border-b border-border bg-surface" aria-label="Main navigation">
    <Container class="h-full">
        <div class="flex h-full items-center gap-3">
            <Logo />

            <Navigation />

            <div class="ml-2 hidden w-full max-w-100 flex-1 lg:flex" role="search">
                <SearchBar />
            </div>

            <div class="ml-auto flex items-center gap-2">
                <button
                    onclick={toggleMobileSearch}
                    aria-expanded={showMobileSearch}
                    aria-controls="mobile-search-bar"
                    aria-label={showMobileSearch ? 'Hide search' : 'Show search'}
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md border border-border bg-surface text-fg-muted transition-colors hover:text-fg lg:hidden"
                >
                    {#if showMobileSearch}
                        <XMarkIcon class="h-4 w-4" />
                    {:else}
                        <MagnifyingGlassIcon class="h-4 w-4" />
                    {/if}
                </button>

                <UserMenu />
                <AppearanceDropdown />
            </div>
        </div>
    </Container>
</header>

{#if MobileSearchComponent}
    <MobileSearchComponent isOpen={showMobileSearch} onClose={closeMobileSearch} />
{/if}
