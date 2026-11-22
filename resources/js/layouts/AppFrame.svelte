<script lang="ts">
    import { toast } from '@/utils/toast';
    import type { Snippet } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import Container from '@/components/Container.svelte';
    import Footer from '@/components/footer/Footer.svelte';
    import Toast from '@/components/Toast.svelte';
    import Header from '@/components/layout/Header.svelte';
    import FlashMessages from '@/components/layout/FlashMessages.svelte';
    import { useRouteAccessibility } from '@/hooks/useAccessibility.svelte';
    import { syncPushSubscription } from '@/utils/push';
    import type { SharedData } from '@/types';

    interface Props {
        children: Snippet;
    }

    let { children }: Props = $props();

    const FULL_WIDTH_PAGES = new Set(['games/route-map']);
    const sharedProps = $derived(page.props as unknown as SharedData);
    let isFullWidth = $derived(FULL_WIDTH_PAGES.has(page.component));
    const flash = $derived(sharedProps.flash ?? {});
    const userId = $derived(sharedProps.auth?.user?.id ?? null);
    let pushReady = $state(false);

    useRouteAccessibility();

    $effect(() => {
        if (flash?.message) toast.success(String(flash.message));
        if (flash?.error) toast.error(String(flash.error));
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

        const removeStart = router.on('start', onStart);
        const removeNavigate = router.on('navigate', onComplete);
        const removeFinish = router.on('finish', onComplete);

        return () => {
            removeStart();
            removeNavigate();
            removeFinish();
        };
    });
</script>

<a href="#main-content" class="skip-link rounded-lg border border-accent-fg bg-accent px-4 py-2 font-medium text-on-accent">Skip to main content</a>

<div class="flex min-h-screen flex-col bg-page text-fg">
    <Header />
    <FlashMessages message={flash?.message ?? undefined} error={flash?.error ?? undefined} />

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
</style>
