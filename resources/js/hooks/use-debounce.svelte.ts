/**
 * Svelte 5 rune-based debounce for function calls.
 * Returns a debounced version of the callback function.
 */
export function useDebounce<TArgs extends unknown[], TReturn>(callback: (...args: TArgs) => TReturn, delay: number): (...args: TArgs) => void {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;
    let latestCallback = callback;

    // Keep the callback reference up to date
    $effect(() => {
        latestCallback = callback;
    });

    // Cleanup timeout on teardown
    $effect(() => {
        return () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        };
    });

    return (...args: TArgs) => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => {
            latestCallback(...args);
        }, delay);
    };
}

/**
 * Svelte 5 rune-based debounced value.
 * Returns a reactive getter for the debounced value.
 */
export function useDebouncedValue<T>(getValue: () => T, delay: number) {
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
