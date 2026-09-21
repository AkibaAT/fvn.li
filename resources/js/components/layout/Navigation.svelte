<script lang="ts">
    import BarsIcon from '@/components/icons/Bars.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import { useStableRoutes } from '@/hooks/useStableRoutes.svelte';
    import { router } from '@inertiajs/svelte';
    import { shouldIntercept } from '@inertiajs/core';

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
        if (!shouldIntercept(event)) return;
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
        class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md border border-border bg-surface text-fg-muted transition-colors hover:text-fg sm:hidden"
    >
        {#if showMobileMenu}
            <XMarkIcon class="h-4 w-4" />
        {:else}
            <BarsIcon class="h-4 w-4" />
        {/if}
    </button>

    <nav
        id="main-navigation-menu"
        class="{showMobileMenu
            ? 'flex'
            : 'hidden'} absolute top-full left-0 z-50 mt-2 w-48 flex-col gap-0.5 rounded-lg border border-border bg-surface p-1.5 sm:static sm:mt-0 sm:flex sm:w-auto sm:flex-row sm:items-center sm:gap-1 sm:border-0 sm:bg-transparent sm:p-0"
        aria-label="Main navigation"
    >
        {#each links as link (link.label)}
            <a
                href={link.route.path}
                onclick={(event) => navigate(event, link.route.path)}
                class="w-full rounded-md px-3 py-2 text-left text-sm font-medium transition-colors sm:w-auto sm:rounded-none sm:px-3 {link.route
                    .isActive
                    ? 'text-fg sm:relative sm:after:absolute sm:after:right-3 sm:after:bottom-0 sm:after:left-3 sm:after:h-0.5 sm:after:rounded-full sm:after:bg-accent'
                    : 'text-fg-muted hover:text-fg'}"
                aria-current={link.route.isActive ? 'page' : undefined}
            >
                {link.label}
            </a>
        {/each}
    </nav>
</div>
