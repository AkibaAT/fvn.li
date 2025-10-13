import { useState, useEffect } from 'react';

/**
 * Hook for managing boolean toggle states
 */
export function useToggle(initialValue: boolean = false): [boolean, () => void] {
    const [value, setValue] = useState(initialValue);
    const toggle = () => setValue(!value);
    return [value, toggle];
}

/**
 * Hook for managing local storage
 */
export function useLocalStorage<T>(key: string, initialValue: T) {
    const [storedValue, setStoredValue] = useState<T>(() => {
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
    });

    const setValue = (value: T | ((val: T) => T)) => {
        try {
            const valueToStore = value instanceof Function ? value(storedValue) : value;
            setStoredValue(valueToStore);
            if (typeof window !== 'undefined') {
                window.localStorage.setItem(key, JSON.stringify(valueToStore));
            }
        } catch (error) {
            console.warn(`Error setting localStorage key "${key}":`, error);
        }
    };

    return [storedValue, setValue] as const;
}

/**
 * Hook for managing window size
 */
export function useWindowSize() {
    const [windowSize, setWindowSize] = useState({
        width: typeof window !== 'undefined' ? window.innerWidth : 0,
        height: typeof window !== 'undefined' ? window.innerHeight : 0,
    });

    useEffect(() => {
        function handleResize() {
            setWindowSize({
                width: window.innerWidth,
                height: window.innerHeight,
            });
        }

        window.addEventListener('resize', handleResize);
        handleResize();

        return () => window.removeEventListener('resize', handleResize);
    }, []);

    return windowSize;
}

/**
 * Hook for managing click outside detection
 */
export function useClickOutside<T extends HTMLElement = HTMLElement>(
    ref: React.RefObject<T>,
    handler: () => void
) {
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                handler();
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [ref, handler]);
}

/**
 * Hook for managing keyboard shortcuts
 */
export function useKeyboardShortcut(
    key: string,
    callback: () => void,
    options: { ctrl?: boolean; alt?: boolean; shift?: boolean; meta?: boolean } = {}
) {
    useEffect(() => {
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
    }, [key, callback, options]);
}

/**
 * Hook for managing copy to clipboard
 */
export function useCopyToClipboard(): [boolean, (text: string) => Promise<void>] {
    const [isCopied, setIsCopied] = useState(false);

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setIsCopied(true);
            setTimeout(() => setIsCopied(false), 2000);
        } catch (error) {
            console.error('Failed to copy text: ', error);
            setIsCopied(false);
        }
    };

    return [isCopied, copyToClipboard];
}

/**
 * Hook for managing debounced values
 */
export function useDebounce<T>(value: T, delay: number): T {
    const [debouncedValue, setDebouncedValue] = useState<T>(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    }, [value, delay]);

    return debouncedValue;
}