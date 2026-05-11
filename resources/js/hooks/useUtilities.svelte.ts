/**
 * Hook for managing boolean toggle states
 */
export function useToggle(initialValue: boolean = false) {
    let value = $state(initialValue);

    const toggle = () => {
        value = !value;
    };

    return {
        get value() {
            return value;
        },
        set value(v: boolean) {
            value = v;
        },
        toggle,
    };
}

/**
 * Hook for managing local storage with reactive state
 */
export function useLocalStorage<T>(key: string, initialValue: T) {
    const getInitialValue = (): T => {
        if (typeof window === 'undefined') {
            return initialValue;
        }
        try {
            const item = window.localStorage.getItem(key);
            return item ? JSON.parse(item) : initialValue;
        } catch (error) {
            console.warn(`Error reading localStorage key "${key}":`, error);
            return initialValue;
        }
    };

    let storedValue = $state<T>(getInitialValue());

    const setValue = (value: T | ((val: T) => T)) => {
        try {
            const valueToStore = value instanceof Function ? value(storedValue) : value;
            storedValue = valueToStore;
            if (typeof window !== 'undefined') {
                window.localStorage.setItem(key, JSON.stringify(valueToStore));
            }
        } catch (error) {
            console.warn(`Error setting localStorage key "${key}":`, error);
        }
    };

    return {
        get value() {
            return storedValue;
        },
        setValue,
    };
}

/**
 * Hook for managing window size
 */
export function useWindowSize() {
    let width = $state(typeof window !== 'undefined' ? window.innerWidth : 0);
    let height = $state(typeof window !== 'undefined' ? window.innerHeight : 0);

    $effect(() => {
        function handleResize() {
            width = window.innerWidth;
            height = window.innerHeight;
        }

        window.addEventListener('resize', handleResize);
        handleResize();

        return () => window.removeEventListener('resize', handleResize);
    });

    return {
        get width() {
            return width;
        },
        get height() {
            return height;
        },
    };
}

/**
 * Hook for managing click outside detection.
 * Pass a getter for the element and a handler function.
 */
export function useClickOutside(getElement: () => HTMLElement | null, handler: () => void) {
    $effect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            const el = getElement();
            if (el && !el.contains(event.target as Node)) {
                handler();
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    });
}

/**
 * Hook for managing keyboard shortcuts
 */
export function useKeyboardShortcut(
    key: string,
    callback: () => void,
    options: { ctrl?: boolean; alt?: boolean; shift?: boolean; meta?: boolean } = {},
) {
    $effect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            const { ctrl, alt, shift, meta } = options;

            if (
                event.key === key &&
                (ctrl === undefined || event.ctrlKey === ctrl) &&
                (alt === undefined || event.altKey === alt) &&
                (shift === undefined || event.shiftKey === shift) &&
                (meta === undefined || event.metaKey === meta)
            ) {
                event.preventDefault();
                callback();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
        };
    });
}

/**
 * Hook for managing copy to clipboard
 */
export function useCopyToClipboard() {
    let isCopied = $state(false);

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            isCopied = true;
            setTimeout(() => {
                isCopied = false;
            }, 2000);
        } catch (error) {
            console.error('Failed to copy text: ', error);
            isCopied = false;
        }
    };

    return {
        get isCopied() {
            return isCopied;
        },
        copyToClipboard,
    };
}

/**
 * Hook for managing debounced values
 */
export function useDebounce<T>(getValue: () => T, delay: number) {
    let debouncedValue = $state<T>(getValue());

    $effect(() => {
        const value = getValue();
        const handler = setTimeout(() => {
            debouncedValue = value;
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    });

    return {
        get value() {
            return debouncedValue;
        },
    };
}
