export interface DebouncedFunction<A extends unknown[]> {
    (...args: A): void;
    /** Cancels a pending invocation, e.g. in an $effect cleanup. */
    cancel(): void;
}

/** Trailing-edge debounce. The timer resets on every call; `cancel()` clears it. */
export function debounce<A extends unknown[]>(fn: (...args: A) => void, delayMs: number): DebouncedFunction<A> {
    let timer: ReturnType<typeof setTimeout> | undefined;

    const debounced = (...args: A) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delayMs);
    };

    debounced.cancel = () => clearTimeout(timer);

    return debounced;
}
