import { router } from '@inertiajs/svelte';
import { announceToScreenReader } from '@/utils/accessibility';

/**
 * Hook for managing accessible announcements
 */
function useAnnouncement() {
    const announce = (message: string, priority: 'polite' | 'assertive' = 'polite') => {
        announceToScreenReader(message, priority);
    };

    return { announce };
}

/**
 * Hook for managing Inertia.js route change accessibility
 */
export function useRouteAccessibility() {
    const { announce } = useAnnouncement();
    let navigationAnnounced = false;

    $effect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        let focusTimerId: ReturnType<typeof setTimeout> | null = null;

        const handleStart = () => {
            announce('Navigating to new page...', 'polite');
            navigationAnnounced = true;
        };

        const handleProgress = (event: { detail: { progress?: { percentage?: number } | null } }) => {
            const percentage = event.detail.progress?.percentage;
            if (typeof percentage === 'number') {
                announce(`Loading progress: ${Math.round(percentage)}%`, 'polite');
            }
        };

        const handleSuccess = () => {
            if (navigationAnnounced) {
                announce('Page loaded successfully', 'polite');
                navigationAnnounced = false;

                if (focusTimerId !== null) {
                    clearTimeout(focusTimerId);
                }

                focusTimerId = setTimeout(() => {
                    focusTimerId = null;
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
            navigationAnnounced = false;
        };

        const removeStart = router.on('start', handleStart);
        const removeProgress = router.on('progress', handleProgress);
        const removeSuccess = router.on('success', handleSuccess);
        const removeError = router.on('error', handleError);

        return () => {
            removeStart();
            removeProgress();
            removeSuccess();
            removeError();
            if (focusTimerId !== null) {
                clearTimeout(focusTimerId);
            }
        };
    });

    return {
        announceNavigation: (message: string) => {
            announce(message, 'polite');
            navigationAnnounced = true;
        },
    };
}
