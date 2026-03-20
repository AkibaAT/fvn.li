/**
 * Hook to prefetch game pages on hover for instant navigation.
 * Uses link preload for prefetching.
 */
import { SvelteSet } from 'svelte/reactivity';

export function usePrefetch() {
    const prefetchedUrls = new SvelteSet<string>();

    const prefetch = (url: string) => {
        if (prefetchedUrls.has(url)) return;
        prefetchedUrls.add(url);

        // Create a prefetch link
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.as = 'document';
        document.head.appendChild(link);
    };

    const onMouseEnter = (url: string) => {
        // Delay prefetch slightly to avoid prefetching on quick mouse movements
        const timeout = setTimeout(() => prefetch(url), 100);
        return () => clearTimeout(timeout);
    };

    return { prefetch, onMouseEnter };
}

export default usePrefetch;
