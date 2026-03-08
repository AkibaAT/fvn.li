import {useEffect, useCallback} from 'react';
import {router} from '@inertiajs/react';

interface KeyboardNavigationOptions {
    enabled?: boolean;
    items?: Array<{slug: string}>;
    currentIndex?: number;
    onNavigate?: (direction: 'next' | 'prev') => void;
}

/**
 * Hook for keyboard navigation shortcuts.
 * J/K to navigate items, Enter to open, / to focus search,
 * Escape to close modals, G then H for home.
 */
export function useKeyboardNavigation(options: KeyboardNavigationOptions = {}) {
    const {enabled = true, items = [], currentIndex = -1, onNavigate} = options;

    const handleKeyDown = useCallback(
        (e: KeyboardEvent) => {
            if (!enabled) return;

            // Don't handle if user is typing in an input, textarea, or contenteditable
            const target = e.target as HTMLElement;
            if (
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT' ||
                target.isContentEditable
            ) {
                return;
            }

            switch (e.key) {
                case 'j':
                case 'J':
                    // Navigate to next item
                    if (onNavigate) {
                        e.preventDefault();
                        onNavigate('next');
                    }
                    break;

                case 'k':
                case 'K':
                    // Navigate to previous item
                    if (onNavigate) {
                        e.preventDefault();
                        onNavigate('prev');
                    }
                    break;

                case '/':
                    // Focus search bar
                    e.preventDefault();
                    const searchInput = document.querySelector<HTMLInputElement>(
                        'input[type="search"], input[name="search"], input[placeholder*="earch"]'
                    );
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                    break;

                case 'Escape':
                    // Blur active element / close modals
                    if (document.activeElement instanceof HTMLElement) {
                        document.activeElement.blur();
                    }
                    break;
            }
        },
        [enabled, onNavigate]
    );

    useEffect(() => {
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [handleKeyDown]);
}

export default useKeyboardNavigation;
