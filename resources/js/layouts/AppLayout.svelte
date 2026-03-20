<script lang="ts">
    import type { Snippet } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import Container from '@/components/Container.svelte';
    import Footer from '@/components/footer/Footer.svelte';
    import BackToTop from '@/components/ui/BackToTop.svelte';
    import Toast, { notify } from '@/components/Toast.svelte';
    import FlashMessages from '@/components/layout/FlashMessages.svelte';
    import { useRouteAccessibility } from '@/hooks/useAccessibility.svelte';

    interface Props {
        children: Snippet;
        title?: string;
    }

    let { children, title }: Props = $props();

    const flash = $derived((($page.props as any).flash ?? {}) as { message?: string; error?: string });

    // Initialize route accessibility for Inertia.js navigation announcements
    useRouteAccessibility();

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

<div class="flex min-h-screen flex-col bg-slate-50 dark:bg-gray-900">
    <!-- Flash Messages -->
    <FlashMessages message={flash?.message} error={flash?.error} />

    <!-- Modern Main Content -->
    <main id="main-content" class="flex-1 scroll-mt-28 py-8" aria-label="Main content">
        <Container>{@render children()}</Container>
    </main>

    <!-- Footer -->
    <Footer />
</div>

<!-- Back to Top Button -->
<BackToTop />

<!-- Global Toast Container -->
<Toast />
