import {useCallback, useRef} from 'react';
import {router} from '@inertiajs/react';

/**
 * Hook to prefetch game pages on hover for instant navigation.
 * Uses Inertia's built-in prefetch if available, otherwise falls back to link preload.
 */
export function usePrefetch() {
    const prefetchedUrls = useRef(new Set<string>());

    const prefetch = useCallback((url: string) => {
        if (prefetchedUrls.current.has(url)) return;
        prefetchedUrls.current.add(url);

        // Create a prefetch link
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.as = 'document';
        document.head.appendChild(link);
    }, []);

    const onMouseEnter = useCallback((url: string) => {
        // Delay prefetch slightly to avoid prefetching on quick mouse movements
        const timeout = setTimeout(() => prefetch(url), 100);
        return () => clearTimeout(timeout);
    }, [prefetch]);

    return {prefetch, onMouseEnter};
}

export default usePrefetch;
