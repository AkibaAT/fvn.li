import React, {ReactNode, useEffect} from 'react';
import Container from '@/components/container';
import Footer from '@/components/footer/footer';
import {NotificationContainer, notify} from '@/components/toast';
import Header from '@/components/layout/Header';
import FlashMessages from '@/components/layout/FlashMessages';
import { Head, usePage } from '@inertiajs/react';
import { useRouteAccessibility } from '@/hooks/useAccessibility';

type InertiaPageProps = Record<string, unknown> & {
    flash?: {
        message?: string;
        error?: string;
    };
};

interface PersistentLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function PersistentLayout({children, title}: PersistentLayoutProps) {
    const page = usePage();
    const { component: pageComponent } = (page as unknown as { component?: string }) ?? {};
    const {
        flash = {},
    } = (page.props as InertiaPageProps) ?? {};

    // Initialize route accessibility for Inertia.js navigation announcements
    useRouteAccessibility();

    // Emit toasts from flash props when they change
    useEffect(() => {
        if (flash?.message) {
            notify(String(flash.message), 'success');
        }
        if (flash?.error) {
            notify(String(flash.error), 'error');
        }
    }, [flash?.message, flash?.error]);

    // Minimal service worker registration for push support
    useEffect(() => {
        if (typeof window === 'undefined') return;
        if (!('serviceWorker' in navigator)) return;
        // Register only once; ignore errors silently
        navigator.serviceWorker
            .getRegistration()
            .then((reg) => {
                if (!reg) {
                    navigator.serviceWorker
                        .register('/service-worker.js')
                        .catch(() => {
                        });
                }
            })
            .catch(() => {

    // Lightweight focus handling: only restore focus if it was previously focused
    // and is still present; do not steal focus during partial reloads.
    useEffect(() => {
        if (typeof document === 'undefined') return;
        let lastFocusedId: string | null = null;
        const onStart = () => {
            const active = document.activeElement as HTMLElement | null;
            lastFocusedId = active?.id || null;
        };
        const onComplete = (e: Event) => {
            // If the page component did not change, this may have been a partial reload
            // (e.g. search on the same page). In that case, if focus is already on an
            // interactive element, don't override it.
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
    }, []);

    // If a search is in progress on the games page, avoid overriding focus
    useEffect(() => {
        if (typeof window === 'undefined' || typeof document === 'undefined') return;
        let searching = false;
        const onSearchStart = () => { searching = true; };
        const onSearchFinish = () => { searching = false; };
        const onComplete = () => {
            if (!searching) return;
            const active = document.activeElement as HTMLElement | null;
            const tag = active?.tagName ?? '';
            const isInteractiveTag = ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(tag);
            const isFocusable = !!active && active !== document.body && (isInteractiveTag || (active?.tabIndex ?? -1) >= 0);
            if (isFocusable) return; // keep current focus during search updates
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
    }, []);

            });
    }, []);

    return (
        <>
            {title && <Head title={title}/>}

            {/* Skip to main content link for keyboard users */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:rounded-lg bg-[var(--color-brand-primary)] px-4 py-2 text-white font-medium focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-secondary)] focus:ring-offset-2 shadow-lg"
            >
                Skip to main content
            </a>

            <div
                className="app-shell flex min-h-screen flex-col">
                {/* Header remounts per page component to avoid persistent state issues, but not for partial reloads */}
                <Header key={pageComponent || 'header'} />

                {/* Flash Messages */}
                <FlashMessages message={flash?.message} error={flash?.error}/>

                {/* Modern Main Content */}
                <main
                    id="main-content"
                    className="flex-1 py-8 scroll-mt-28"
                    role="main"
                    aria-label="Main content"
                >
                    <Container>{children}</Container>
                </main>

                {/* Footer */}
                <Footer/>
            </div>

            {/* Global Toast Container */}
            <NotificationContainer/>
        </>
    );
}
