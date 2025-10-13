import { useEffect, useRef, useState } from 'react';
import { announceToScreenReader, trapFocus, generateId } from '@/utils/accessibility';

/**
 * Hook for managing accessible announcements
 */
export function useAnnouncement() {
    const announce = (message: string, priority: 'polite' | 'assertive' = 'polite') => {
        announceToScreenReader(message, priority);
    };

    return { announce };
}

/**
 * Hook for managing focus trapping in modals and dialogs
 */
export function useFocusTrap(isActive: boolean = true) {
    const containerRef = useRef<HTMLDivElement>(null);
    const cleanupRef = useRef<(() => void) | undefined | null>(null);

    useEffect(() => {
        if (!isActive || !containerRef.current) return;

        if (containerRef.current) {
            cleanupRef.current = trapFocus(containerRef.current);
        }

        return () => {
            if (cleanupRef.current) {
                cleanupRef.current();
                cleanupRef.current = null;
            }
        };
    }, [isActive]);

    return containerRef;
}

/**
 * Hook for managing ARIA attributes
 */
export function useAria() {
    const generateUniqueId = (prefix: string = 'element') => {
        return generateId(prefix);
    };

    const createAriaProps = (
        role?: string,
        label?: string,
        describedBy?: string,
        expanded?: boolean,
        selected?: boolean,
        checked?: boolean,
        disabled?: boolean
    ) => {
        const props: Record<string, string> = {};
        
        if (role) props['role'] = role;
        if (label) props['aria-label'] = label;
        if (describedBy) props['aria-describedby'] = describedBy;
        if (expanded !== undefined) props['aria-expanded'] = expanded.toString();
        if (selected !== undefined) props['aria-selected'] = selected.toString();
        if (checked !== undefined) props['aria-checked'] = checked.toString();
        if (disabled !== undefined) props['aria-disabled'] = disabled.toString();

        return props;
    };

    return { generateUniqueId, createAriaProps };
}

/**
 * Hook for managing keyboard navigation
 */
export function useKeyboardNavigation(
    onEnter?: () => void,
    onSpace?: () => void,
    onEscape?: () => void,
    onArrowUp?: () => void,
    onArrowDown?: () => void,
    onArrowLeft?: () => void,
    onArrowRight?: () => void,
    onTab?: (e: KeyboardEvent) => void
) {
    const handleKeyDown = (e: KeyboardEvent) => {
        switch (e.key) {
            case 'Enter':
                e.preventDefault();
                onEnter?.();
                break;
            case ' ':
                e.preventDefault();
                onSpace?.();
                break;
            case 'Escape':
                e.preventDefault();
                onEscape?.();
                break;
            case 'ArrowUp':
                e.preventDefault();
                onArrowUp?.();
                break;
            case 'ArrowDown':
                e.preventDefault();
                onArrowDown?.();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                onArrowLeft?.();
                break;
            case 'ArrowRight':
                e.preventDefault();
                onArrowRight?.();
                break;
            case 'Tab':
                onTab?.(e);
                break;
        }
    };

    const getKeyDownHandler = () => {
        return handleKeyDown;
    };

    return { getKeyDownHandler };
}

/**
 * Hook for managing live regions
 */
export function useLiveRegion() {
    const [announcement, setAnnouncement] = useState<string>('');
    const [priority, setPriority] = useState<'polite' | 'assertive'>('polite');

    useEffect(() => {
        if (announcement) {
            announceToScreenReader(announcement, priority);
        }
    }, [announcement, priority]);

    const announce = (message: string, newPriority: 'polite' | 'assertive' = 'polite') => {
        setAnnouncement(message);
        setPriority(newPriority);
    };

    return { announce };
}

/**
 * Hook for managing accessible tabs
 */
export function useTabs(initialTab: string = '') {
    const [activeTab, setActiveTab] = useState(initialTab);
    const tabRefs = useRef<Record<string, HTMLButtonElement | null>>({});

    const registerTab = (tabId: string, element: HTMLButtonElement | null) => {
        tabRefs.current[tabId] = element;
    };

    const activateTab = (tabId: string) => {
        setActiveTab(tabId);
        // Focus the activated tab
        setTimeout(() => {
            tabRefs.current[tabId]?.focus();
        }, 0);
    };

    const handleTabKeyDown = (e: KeyboardEvent, tabId: string) => {
        const tabs = Object.keys(tabRefs.current);
        const currentIndex = tabs.indexOf(tabId);

        switch (e.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                e.preventDefault();
                {
                    const nextIndex = (currentIndex + 1) % tabs.length;
                    activateTab(tabs[nextIndex]);
                }
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                e.preventDefault();
                {
                    const prevIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                    activateTab(tabs[prevIndex]);
                }
                break;
            case 'Home':
                e.preventDefault();
                activateTab(tabs[0]);
                break;
            case 'End':
                e.preventDefault();
                activateTab(tabs[tabs.length - 1]);
                break;
        }
    };

    return {
        activeTab,
        setActiveTab: activateTab,
        registerTab,
        handleTabKeyDown,
    };
}

/**
 * Hook for managing Inertia.js route change accessibility
 */
export function useRouteAccessibility() {
    const { announce } = useAnnouncement();
    const navigationAnnouncedRef = useRef(false);

    useEffect(() => {
        // Listen for Inertia.js navigation events
        const handleStart = () => {
            announce('Navigating to new page...', 'polite');
            navigationAnnouncedRef.current = true;
        };

        const handleProgress = (event: CustomEvent<{ progress: ProgressEvent | undefined }>) => {
            if (event.detail.progress?.loaded && event.detail.progress?.total) {
                const progress = Math.round((event.detail.progress.loaded / event.detail.progress.total) * 100);
                announce(`Loading progress: ${progress}%`, 'polite');
            }
        };

        const handleSuccess = () => {
            if (navigationAnnouncedRef.current) {
                announce('Page loaded successfully', 'polite');
                navigationAnnouncedRef.current = false;

                // Focus management after navigation: do NOT steal focus
                // if a focusable element (like an input) is already focused.
                setTimeout(() => {
                    if (typeof document === 'undefined') return;
                    const active = document.activeElement as HTMLElement | null;
                    const tag = active?.tagName ?? '';
                    const isInteractiveTag = ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(tag);
                    const isFocusable = !!active && active !== document.body && (isInteractiveTag || (active?.tabIndex ?? -1) >= 0);
                    if (isFocusable) return;

                    const mainContent = document.querySelector('[role="main"], main, #main-content');
                    if (mainContent instanceof HTMLElement) {
                        mainContent.focus();
                    }
                }, 50);
            }
        };


        const handleError = () => {
            announce('Failed to load page. Please try again.', 'assertive');
            navigationAnnouncedRef.current = false;
        };

        // Add event listeners for Inertia.js (SSR-safe)
        if (typeof document !== 'undefined') {
            document.addEventListener('inertia:start', handleStart as EventListener);
            document.addEventListener('inertia:progress', handleProgress as EventListener);
            document.addEventListener('inertia:success', handleSuccess as EventListener);
            document.addEventListener('inertia:error', handleError as EventListener);

            return () => {
                document.removeEventListener('inertia:start', handleStart as EventListener);
                document.removeEventListener('inertia:progress', handleProgress as EventListener);
                document.removeEventListener('inertia:success', handleSuccess as EventListener);
                document.removeEventListener('inertia:error', handleError as EventListener);
            };
        }
        
        return () => {};
    }, [announce]);

    return {
        announceNavigation: (message: string) => {
            announce(message, 'polite');
            navigationAnnouncedRef.current = true;
        }
    };
}

/**
 * Hook for managing form submission accessibility
 */
export function useFormSubmission() {
    const { announce } = useAnnouncement();
    const formStateRef = useRef<'idle' | 'submitting' | 'success' | 'error'>('idle');

    const announceSubmission = (message: string = 'Submitting form...') => {
        formStateRef.current = 'submitting';
        announce(message, 'polite');
    };

    const announceSuccess = (message: string = 'Form submitted successfully') => {
        formStateRef.current = 'success';
        announce(message, 'polite');
        
        // Reset state after announcement
        setTimeout(() => {
            formStateRef.current = 'idle';
        }, 3000);
    };

    const announceError = (message: string = 'Form submission failed. Please check your input and try again.') => {
        formStateRef.current = 'error';
        announce(message, 'assertive');
        
        // Reset state after announcement
        setTimeout(() => {
            formStateRef.current = 'idle';
        }, 3000);
    };

    return {
        announceSubmission,
        announceSuccess,
        announceError,
        isSubmitting: formStateRef.current === 'submitting',
        formState: formStateRef.current
    };
}

/**
 * Hook for managing progress tracking accessibility
 */
export function useProgressTracking() {
    const { announce } = useAnnouncement();
    const progressRef = useRef<{
        current: number;
        min: number;
        max: number;
        message: string;
    } | null>(null);

    const startProgress = (min: number = 0, max: number = 100, message: string = 'Progress started') => {
        progressRef.current = { current: min, min, max, message };
        announce(`${message}: 0%`, 'polite');
    };

    const updateProgress = (value: number, message?: string) => {
        if (!progressRef.current) return;

        const { min, max } = progressRef.current;
        const clampedValue = Math.max(min, Math.min(max, value));
        const percentage = Math.round(((clampedValue - min) / (max - min)) * 100);
        
        progressRef.current.current = clampedValue;
        
        const progressMessage = message || progressRef.current.message;
        announce(`${progressMessage}: ${percentage}%`, 'polite');
    };

    const completeProgress = (message: string = 'Progress completed') => {
        if (progressRef.current) {
            announce(message, 'polite');
            progressRef.current = null;
        }
    };

    const failProgress = (error: string = 'Progress failed') => {
        if (progressRef.current) {
            announce(`Error: ${error}`, 'assertive');
            progressRef.current = null;
        }
    };

    return {
        announce,
        startProgress,
        updateProgress,
        completeProgress,
        failProgress,
        isActive: progressRef.current !== null
    };
}