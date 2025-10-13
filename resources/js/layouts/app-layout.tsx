import Container from '@/components/container';
import Footer from '@/components/footer/footer';
import {NotificationContainer, notify} from '@/components/toast';
import FlashMessages from '@/components/layout/FlashMessages';
import {Head, usePage} from '@inertiajs/react';
import React, {ReactNode, useEffect} from 'react';
import { useRouteAccessibility } from '@/hooks/useAccessibility';

type InertiaPageProps = Record<string, unknown> & {
    flash?: {
        message?: string;
        error?: string;
    };
};

interface AppLayoutProps {
    children: ReactNode;
    title?: string;
}


export default function AppLayout({children, title}: AppLayoutProps) {
    const {
        flash = {},
    } = (usePage().props as InertiaPageProps) ?? {};

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
            });
    }, []);

    return (
        <>
            {title && <Head title={title}/>}

            {/* Skip to main content link for keyboard users */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:rounded-lg bg-blue-600 px-4 py-2 text-white font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-lg"
            >
                Skip to main content
            </a>

            <div
                className="flex min-h-screen flex-col bg-slate-50 dark:bg-gray-900">
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
