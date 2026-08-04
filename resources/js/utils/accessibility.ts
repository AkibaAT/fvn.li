/**
 * Accessibility utilities and helper functions
 */

/**
 * Announce a message to screen readers (SSR-safe)
 */
export const announceToScreenReader = (message: string, priority: 'polite' | 'assertive' = 'polite') => {
    if (typeof document === 'undefined') return;

    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', priority);
    announcement.setAttribute('aria-atomic', 'true');
    announcement.setAttribute('aria-hidden', 'false');
    announcement.style.position = 'absolute';
    announcement.style.left = '-10000px';
    announcement.style.width = '1px';
    announcement.style.height = '1px';
    announcement.style.overflow = 'hidden';

    document.body.appendChild(announcement);
    announcement.textContent = message;

    setTimeout(() => {
        if (document.body.contains(announcement)) {
            document.body.removeChild(announcement);
        }
    }, 1000);
};

/**
 * Trap focus within a container (for modals, dropdowns, etc.) (SSR-safe)
 */
export const trapFocus = (container: HTMLElement) => {
    if (typeof document === 'undefined') return () => {};

    const focusableElements = container.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    ) as NodeListOf<HTMLElement>;

    if (focusableElements.length === 0) return () => {};

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    const handleKeyDown = (e: KeyboardEvent) => {
        if (e.key !== 'Tab') return;

        if (e.shiftKey) {
            if (document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            if (document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    };

    container.addEventListener('keydown', handleKeyDown);

    // Focus first element
    firstElement.focus();

    return () => {
        container.removeEventListener('keydown', handleKeyDown);
    };
};

/**
 * Check if an element is visible (SSR-safe)
 */
export const isVisible = (element: HTMLElement) => {
    if (typeof window === 'undefined') return false;

    const rect = element.getBoundingClientRect();
    return (
        rect.width > 0 &&
        rect.height > 0 &&
        window.getComputedStyle(element).display !== 'none' &&
        window.getComputedStyle(element).visibility !== 'hidden'
    );
};

/**
 * Generate unique ID for ARIA attributes
 */
export const generateId = (prefix: string = 'id') => {
    return `${prefix}-${Math.random().toString(36).substr(2, 9)}`;
};

/**
 * AJAX and Loading Accessibility Utilities
 */

/**
 * Announce loading state to screen readers
 */
export const announceLoading = (message: string = 'Loading content...', priority: 'polite' | 'assertive' = 'polite') => {
    announceToScreenReader(message, priority);
};

/**
 * Announce error during AJAX operation
 */
export const announceError = (message: string = 'An error occurred', priority: 'assertive' = 'assertive') => {
    announceToScreenReader(message, priority);
};

/**
 * Set aria-busy state on an element
 */
export const setBusy = (element: HTMLElement, busy: boolean) => {
    element.setAttribute('aria-busy', busy.toString());
};

/**
 * Create and manage a progress bar (SSR-safe)
 */
export const createProgressBar = (container: HTMLElement, minValue: number = 0, maxValue: number = 100) => {
    if (typeof document === 'undefined')
        return {
            update: () => {},
            complete: () => {},
            remove: () => {},
        };

    const progressBar = document.createElement('div');
    progressBar.setAttribute('role', 'progressbar');
    progressBar.setAttribute('aria-valuemin', minValue.toString());
    progressBar.setAttribute('aria-valuemax', maxValue.toString());
    progressBar.setAttribute('aria-valuenow', minValue.toString());
    progressBar.className = 'sr-only'; // Hidden visually but available to screen readers

    container.appendChild(progressBar);

    return {
        update: (value: number, message?: string) => {
            const clampedValue = Math.max(minValue, Math.min(maxValue, value));
            progressBar.setAttribute('aria-valuenow', clampedValue.toString());

            if (message) {
                const existingMessage = progressBar.querySelector('.sr-only-message');
                if (existingMessage) {
                    existingMessage.textContent = message;
                } else {
                    const messageElement = document.createElement('span');
                    messageElement.className = 'sr-only-message';
                    messageElement.textContent = message;
                    progressBar.appendChild(messageElement);
                }
            }
        },
        complete: () => {
            progressBar.setAttribute('aria-valuenow', maxValue.toString());
            announceToScreenReader('Progress complete');
        },
        remove: () => {
            progressBar.remove();
        },
    };
};
