interface KeyboardNavigationOptions {
    enabled?: boolean;
    items?: Array<{ slug: string }>;
    currentIndex?: number;
    onNavigate?: (direction: 'next' | 'prev') => void;
}

/**
 * Hook for keyboard navigation shortcuts.
 * J/K to navigate items, Enter to open, / to focus search,
 * Escape to close modals, G then H for home.
 */
export function useKeyboardNavigation(options: KeyboardNavigationOptions = {}) {
    const { enabled = true, onNavigate } = options;

    const handleKeyDown = (e: KeyboardEvent) => {
        if (!enabled) return;

        // Don't handle if user is typing in an input, textarea, or contenteditable
        const target = e.target as HTMLElement;
        if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable) {
            return;
        }

        switch (e.key) {
            case 'j':
            case 'J':
                if (onNavigate) {
                    e.preventDefault();
                    onNavigate('next');
                }
                break;

            case 'k':
            case 'K':
                if (onNavigate) {
                    e.preventDefault();
                    onNavigate('prev');
                }
                break;

            case '/':
                e.preventDefault();
                {
                    const searchInput = document.querySelector<HTMLInputElement>(
                        'input[type="search"], input[name="search"], input[placeholder*="earch"]',
                    );
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                break;

            case 'Escape':
                if (document.activeElement instanceof HTMLElement) {
                    document.activeElement.blur();
                }
                break;
        }
    };

    $effect(() => {
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    });
}

export default useKeyboardNavigation;
