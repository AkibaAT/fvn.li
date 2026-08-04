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
        const handleStart = () => {
            announce('Navigating to new page...', 'polite');
            navigationAnnounced = true;
        };

        const handleProgress = (event: CustomEvent<{ progress: ProgressEvent | undefined }>) => {
            if (event.detail.progress?.loaded && event.detail.progress?.total) {
                const progress = Math.round((event.detail.progress.loaded / event.detail.progress.total) * 100);
                announce(`Loading progress: ${progress}%`, 'polite');
            }
        };

        const handleSuccess = () => {
            if (navigationAnnounced) {
                announce('Page loaded successfully', 'polite');
                navigationAnnounced = false;

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
            navigationAnnounced = false;
        };

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
    });

    return {
        announceNavigation: (message: string) => {
            announce(message, 'polite');
            navigationAnnounced = true;
        },
    };
}
