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
    
    // Remove after announcement
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
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
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
    
    // Return cleanup function
    return () => {
        container.removeEventListener('keydown', handleKeyDown);
    };
};

/**
 * Manage ARIA expanded state
 */
export const setExpanded = (element: HTMLElement, expanded: boolean) => {
    element.setAttribute('aria-expanded', expanded.toString());
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
 * Check color contrast ratio (simplified version)
 */
export const getContrastRatio = (): number => {
    // This is a simplified version - in production, use a proper color contrast library
    return 4.5; // Placeholder value
};

/**
 * Keyboard navigation keys
 */
export const KEYS = {
    ENTER: 'Enter',
    SPACE: ' ',
    ESCAPE: 'Escape',
    TAB: 'Tab',
    ARROW_UP: 'ArrowUp',
    ARROW_DOWN: 'ArrowDown',
    ARROW_LEFT: 'ArrowLeft',
    ARROW_RIGHT: 'ArrowRight',
    HOME: 'Home',
    END: 'End',
    PAGE_UP: 'PageUp',
    PAGE_DOWN: 'PageDown',
} as const;

/**
 * ARIA roles
 */
export const ROLES = {
    ALERT: 'alert',
    ALERTDIALOG: 'alertdialog',
    APPLICATION: 'application',
    ARTICLE: 'article',
    BANNER: 'banner',
    BUTTON: 'button',
    CELL: 'cell',
    CHECKBOX: 'checkbox',
    COLUMNHEADER: 'columnheader',
    COMBOBOX: 'combobox',
    COMPLEMENTARY: 'complementary',
    CONTENTINFO: 'contentinfo',
    DEFINITION: 'definition',
    DIALOG: 'dialog',
    DIRECTORY: 'directory',
    DOCUMENT: 'document',
    FEED: 'feed',
    FIGURE: 'figure',
    FORM: 'form',
    GRID: 'grid',
    GRIDCELL: 'gridcell',
    GROUP: 'group',
    HEADING: 'heading',
    IMG: 'img',
    LINK: 'link',
    LIST: 'list',
    LISTBOX: 'listbox',
    LISTITEM: 'listitem',
    LOG: 'log',
    MAIN: 'main',
    MARQUEE: 'marquee',
    MATH: 'math',
    MENU: 'menu',
    MENUBAR: 'menubar',
    MENUITEM: 'menuitem',
    MENUITEMCHECKBOX: 'menuitemcheckbox',
    MENUITEMRADIO: 'menuitemradio',
    NAVIGATION: 'navigation',
    NONE: 'none',
    NOTE: 'note',
    OPTION: 'option',
    PRESENTATION: 'presentation',
    PROGRESSBAR: 'progressbar',
    RADIO: 'radio',
    RADIOGROUP: 'radiogroup',
    REGION: 'region',
    ROW: 'row',
    ROWGROUP: 'rowgroup',
    ROWHEADER: 'rowheader',
    SCROLLBAR: 'scrollbar',
    SEARCH: 'search',
    SEPARATOR: 'separator',
    SLIDER: 'slider',
    SPINBUTTON: 'spinbutton',
    STATUS: 'status',
    SWITCH: 'switch',
    TAB: 'tab',
    TABLE: 'table',
    TABLIST: 'tablist',
    TABPANEL: 'tabpanel',
    TERM: 'term',
    TEXTBOX: 'textbox',
    TIMER: 'timer',
    TOOLBAR: 'toolbar',
    TOOLTIP: 'tooltip',
    TREE: 'tree',
    TREEGRID: 'treegrid',
    TREEITEM: 'treeitem',
} as const;

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
 * Announce completion of an operation
 */
export const announceComplete = (message: string = 'Operation completed', priority: 'polite' | 'assertive' = 'polite') => {
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
    if (typeof document === 'undefined') return {
        update: () => {},
        complete: () => {},
        remove: () => {}
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
                // Update message for screen readers
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
            announceComplete('Progress complete');
        },
        remove: () => {
            progressBar.remove();
        }
    };
};

/**
 * Manage loading states with accessibility support (SSR-safe)
 */
export const createLoadingManager = () => {
    let loadingCount = 0;
    let loadingRegion: HTMLElement | null = null;
    
    const ensureLoadingRegion = () => {
        if (typeof document === 'undefined') return null;
        
        if (!loadingRegion) {
            loadingRegion = document.createElement('div');
            loadingRegion.setAttribute('aria-live', 'polite');
            loadingRegion.setAttribute('aria-atomic', 'true');
            loadingRegion.className = 'sr-only';
            document.body.appendChild(loadingRegion);
        }
        return loadingRegion;
    };
    
    return {
        start: (message?: string) => {
            loadingCount++;
            if (loadingCount === 1) {
                const region = ensureLoadingRegion();
                if (region) {
                    region.textContent = message || 'Loading content...';
                    announceLoading(message || 'Loading content...');
                }
            }
        },
        update: (message: string) => {
            if (loadingRegion) {
                loadingRegion.textContent = message;
                announceLoading(message);
            }
        },
        stop: (message?: string) => {
            loadingCount = Math.max(0, loadingCount - 1);
            if (loadingCount === 0 && loadingRegion) {
                loadingRegion.textContent = message || 'Loading complete';
                announceComplete(message || 'Loading complete');
                
                // Clear after a delay
                setTimeout(() => {
                    if (loadingCount === 0 && loadingRegion) {
                        loadingRegion.textContent = '';
                    }
                }, 1000);
            }
        },
        isLoading: () => loadingCount > 0
    };
};

/**
 * Track AJAX operations with accessibility
 */
export const createAjaxTracker = () => {
    const operations = new Map<string, { startTime: number; message: string }>();
    const loadingManager = createLoadingManager();
    
    return {
        start: (id: string, message: string = 'Processing...') => {
            operations.set(id, { startTime: Date.now(), message });
            loadingManager.start(message);
        },
        update: (id: string, message: string) => {
            const operation = operations.get(id);
            if (operation) {
                operation.message = message;
                loadingManager.update(message);
            }
        },
        complete: (id: string, message?: string) => {
            const operation = operations.get(id);
            if (operation) {
                const duration = Date.now() - operation.startTime;
                const completeMessage = message || `${operation.message} completed in ${Math.round(duration / 1000)} seconds`;
                operations.delete(id);
                loadingManager.stop(completeMessage);
            }
        },
        error: (id: string, error: string) => {
            const operation = operations.get(id);
            if (operation) {
                operations.delete(id);
                loadingManager.stop();
                announceError(`${operation.message} failed: ${error}`);
            }
        }
    };
};

// Global AJAX tracker instance
export const ajaxTracker = createAjaxTracker();