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
    import { syncPushSubscription } from '@/utils/push';

    interface Props {
        children: Snippet;
    }

    let { children }: Props = $props();

    const FULL_WIDTH_PAGES = new Set(['games/route-map']);
    let isFullWidth = $derived(FULL_WIDTH_PAGES.has((page as any).component as string));
    const flash = $derived(((page.props as any)?.flash ?? {}) as { message?: string; error?: string });
    const userId = $derived((page.props as any)?.auth?.user?.id ?? null);
    let pushReady = $state(false);

    useRouteAccessibility();

    $effect(() => {
        if (flash?.message) notify(String(flash.message), 'success');
        if (flash?.error) notify(String(flash.error), 'error');
    });

    onMount(() => {
        if (!('serviceWorker' in navigator)) return;
        navigator.serviceWorker
            .getRegistration()
            .then((reg) => {
                return reg ?? navigator.serviceWorker.register('/service-worker.js');
            })
            .then(() => {
                pushReady = true;
            })
            .catch(() => {});
    });

    $effect(() => {
        if (pushReady && userId) void syncPushSubscription().catch(() => {});
    });

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
        document.addEventListener('inertia:navigate', onComplete as EventListener);
        document.addEventListener('inertia:finish', onComplete as EventListener);

        return () => {
            document.removeEventListener('inertia:start', onStart as EventListener);
            document.removeEventListener('inertia:navigate', onComplete as EventListener);
            document.removeEventListener('inertia:finish', onComplete as EventListener);
        };
    });
</script>

<a href="#main-content" class="skip-link rounded-lg bg-accent px-4 py-2 font-medium text-on-accent"> Skip to main content </a>

<div class="flex min-h-screen flex-col bg-page text-fg">
    <Header />
    <FlashMessages message={flash?.message} error={flash?.error} />

    <main
        id="main-content"
        tabindex="-1"
        class="main-content flex-1 scroll-mt-16 {isFullWidth ? 'full-width' : 'pt-5 pb-8 sm:pt-7 sm:pb-10 lg:pt-9 lg:pb-12'}"
        aria-label="Main content"
    >
        {#if isFullWidth}
            {@render children()}
        {:else}
            <Container>{@render children()}</Container>
        {/if}
    </main>

    <Footer />
</div>

<Toast />

<style>
    .skip-link {
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 9999;
        width: 1px;
        height: 1px;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border-width: 0;
    }

    .skip-link:focus,
    .skip-link:focus-visible {
        width: auto;
        height: auto;
        margin: 0;
        overflow: visible;
        clip: auto;
        white-space: normal;
    }

    .main-content:not(.full-width) :global(> div) {
        max-width: 1340px;
    }
</style>
