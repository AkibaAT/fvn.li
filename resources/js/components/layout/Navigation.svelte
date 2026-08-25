<script lang="ts">
    import BarsIcon from '@/components/icons/Bars.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import { useStableRoutes } from '@/hooks/useStableRoutes.svelte';
    import { router } from '@inertiajs/svelte';

    const routes = useStableRoutes();
    let showMobileMenu = $state(false);
    let mobileMenuRef: HTMLDivElement | undefined = $state();
    let mobileMenuButton: HTMLButtonElement | undefined = $state();

    const links = $derived([
        { label: 'Games', route: routes.games },
        { label: 'Lists', route: routes.lists },
        { label: 'Ratings', route: routes.ratings },
    ]);

    $effect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (showMobileMenu && mobileMenuRef && !mobileMenuRef.contains(event.target as Node)) showMobileMenu = false;
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    });

    function navigate(event: MouseEvent, path: string): void {
        event.preventDefault();
        showMobileMenu = false;
        router.visit(path, { preserveState: false });
    }

    function handleKeydown(event: KeyboardEvent): void {
        if (event.key !== 'Escape' || !showMobileMenu) return;
        showMobileMenu = false;
        mobileMenuButton?.focus();
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<div class="relative" bind:this={mobileMenuRef}>
    <button
        bind:this={mobileMenuButton}
        type="button"
        onclick={() => (showMobileMenu = !showMobileMenu)}
        aria-expanded={showMobileMenu}
        aria-controls="main-navigation-menu"
        aria-label={showMobileMenu ? 'Close navigation menu' : 'Open navigation menu'}
        class="cursor-pointer rounded-lg bg-gray-100 p-2 text-gray-700 transition-colors duration-200 hover:bg-gray-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none sm:hidden dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
    >
        {#if showMobileMenu}
            <XMarkIcon class="h-5 w-5" />
        {:else}
            <BarsIcon class="h-5 w-5" />
        {/if}
    </button>

    <nav
        id="main-navigation-menu"
        class="{showMobileMenu
            ? 'flex'
            : 'hidden'} absolute top-full left-0 z-50 mt-2 w-48 flex-col gap-1 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg sm:static sm:mt-0 sm:flex sm:w-auto sm:flex-row sm:items-center sm:gap-0 sm:space-x-1 sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none dark:border-gray-700 dark:bg-gray-800 sm:dark:border-0 sm:dark:bg-transparent"
        aria-label="Main navigation"
    >
        {#each links as link (link.label)}
            <a
                href={link.route.path}
                onclick={(event) => navigate(event, link.route.path)}
                class="w-full rounded-lg px-4 py-2 text-left font-medium transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none sm:w-auto dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white {link
                    .route.isActive
                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                    : 'text-gray-700'}"
                aria-current={link.route.isActive ? 'page' : undefined}
            >
                {link.label}
            </a>
        {/each}
    </nav>
</div>
