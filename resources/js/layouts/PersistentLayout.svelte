<script lang="ts">
    import type { Snippet } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import Container from '@/components/Container.svelte';
    import Footer from '@/components/footer/Footer.svelte';
    import Toast, { notify } from '@/components/Toast.svelte';
    import Header from '@/components/layout/Header.svelte';
    import FlashMessages from '@/components/layout/FlashMessages.svelte';
    import { useRouteAccessibility } from '@/hooks/useAccessibility.svelte';

    interface Props {
        children: Snippet;
        title?: string;
    }

    let { children, title }: Props = $props();

    const FULL_WIDTH_PAGES = new Set(['home', 'games/route-map']);
    let isFullWidth = $derived(FULL_WIDTH_PAGES.has(((page as any)?.component ?? '') as string));

    const flash = $derived((((page as any)?.props?.flash as any) ?? {}) as { message?: string; error?: string });

    // Initialize route accessibility for Inertia.js navigation announcements
    useRouteAccessibility();

    // Central title suffix — ensures every page title ends with " - FVN.li"
    const SITE_SUFFIX = ' - FVN.li';
    onMount(() => {
        const ensureSuffix = () => {
            if (document.title && !document.title.endsWith(SITE_SUFFIX)) {
                document.title = `${document.title}${SITE_SUFFIX}`;
            }
        };
        ensureSuffix();

        const observer = new MutationObserver(ensureSuffix);
        const titleEl = document.querySelector('title');
        if (titleEl) {
            observer.observe(titleEl, { childList: true, characterData: true, subtree: true });
        }
        return () => observer.disconnect();
    });

    // Emit toasts from flash props when they change
    $effect(() => {
        if (flash?.message) {
            notify(String(flash.message), 'success');
        }
        if (flash?.error) {
            notify(String(flash.error), 'error');
        }
    });

    // Minimal service worker registration for push support
    onMount(() => {
        if (!('serviceWorker' in navigator)) return;
        navigator.serviceWorker
            .getRegistration()
            .then((reg) => {
                if (!reg) {
                    navigator.serviceWorker.register('/service-worker.js').catch(() => {});
                }
            })
            .catch(() => {});
    });

    // Lightweight focus handling: only restore focus if it was previously focused
    // and is still present; do not steal focus during partial reloads.
    onMount(() => {
        let lastFocusedId: string | null = null;

        const onStart = () => {
            const active = document.activeElement as HTMLElement | null;
            lastFocusedId = active?.id || null;
        };

        const onComplete = () => {
            const active = document.activeElement as HTMLElement | null;
            const tag = active?.tagName ?? '';
            const isInteractiveTag = ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(tag);
            const isFocusable = !!active && active !== document.body && (isInteractiveTag || (active?.tabIndex ?? -1) >= 0);
            if (isFocusable) return;

            if (lastFocusedId) {
                const el = document.getElementById(lastFocusedId) as HTMLElement | null;
                el?.focus?.();
            }
            lastFocusedId = null;
        };

        document.addEventListener('inertia:start', onStart as EventListener);
        document.addEventListener('inertia:complete', onComplete as EventListener);

        return () => {
            document.removeEventListener('inertia:start', onStart as EventListener);
            document.removeEventListener('inertia:complete', onComplete as EventListener);
        };
    });

    // If a search is in progress on the games page, avoid overriding focus
    onMount(() => {
        let searching = false;

        const onSearchStart = () => {
            searching = true;
        };
        const onSearchFinish = () => {
            searching = false;
        };
        const onComplete = () => {
            if (!searching) return;
            const active = document.activeElement as HTMLElement | null;
            const tag = active?.tagName ?? '';
            const isInteractiveTag = ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(tag);
            const isFocusable = !!active && active !== document.body && (isInteractiveTag || (active?.tabIndex ?? -1) >= 0);
            if (isFocusable) return;
            const el = document.getElementById('global-search-input') as HTMLElement | null;
            el?.focus?.();
        };

        window.addEventListener('fvn:search:start', onSearchStart as EventListener);
        window.addEventListener('fvn:search:finish', onSearchFinish as EventListener);
        document.addEventListener('inertia:complete', onComplete as EventListener);

        return () => {
            window.removeEventListener('fvn:search:start', onSearchStart as EventListener);
            window.removeEventListener('fvn:search:finish', onSearchFinish as EventListener);
            document.removeEventListener('inertia:complete', onComplete as EventListener);
        };
    });
</script>

<svelte:head>
    {#if title}
        <title>{title}</title>
    {/if}
</svelte:head>

<!-- Skip to main content link for keyboard users -->
<a
    href="#main-content"
    class="sr-only bg-blue-600 px-4 py-2 font-medium text-white shadow-lg focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:rounded-lg focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
>
    Skip to main content
</a>

<div class="flex min-h-screen flex-col bg-gray-50 dark:bg-[#060a16]">
    <!-- Header -->
    <Header />

    <!-- Flash Messages -->
    <FlashMessages message={flash?.message} error={flash?.error} />

    <!-- Modern Main Content -->
    <main id="main-content" class="main-content flex-1 scroll-mt-28 {isFullWidth ? 'full-width' : 'py-8'}" aria-label="Main content">
        {#if isFullWidth}
            {@render children()}
        {:else}
            <Container>{@render children()}</Container>
        {/if}
    </main>

    <!-- Footer -->
    <Footer />
</div>

<!-- Global Toast Container -->
<Toast />

<style>
    .main-content:not(.full-width) :global(> div) {
        max-width: 1340px;
    }
</style>
