import { router } from '@inertiajs/svelte';
import { SvelteURL } from 'svelte/reactivity';

type Options = {
    /** Treat `#tab-name` as a tab source in addition to `?tab=`. */
    readHash?: boolean;
};

/**
 * URL-synced tab selection: reads the `tab` query param once, and pushes a
 * debounced-free history update on change (the default tab removes the param).
 */
export function useUrlTab<T extends string>(validTabs: readonly T[], defaultTab: T, { readHash = false }: Options = {}) {
    function readInitial(): T {
        if (typeof window === 'undefined') return defaultTab;
        const url = new SvelteURL(window.location.href);
        const tab = url.searchParams.get('tab') ?? (readHash ? url.hash.slice(1) : null);
        return (validTabs as readonly string[]).includes(tab ?? '') ? (tab as T) : defaultTab;
    }

    let activeTab = $state<T>(readInitial());

    function setTab(tab: T) {
        if (tab === activeTab) return;
        activeTab = tab;
        if (typeof window === 'undefined') return;

        const url = new SvelteURL(window.location.href);
        if (tab === defaultTab) url.searchParams.delete('tab');
        else url.searchParams.set('tab', tab);
        url.hash = '';
        router.push({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true });
    }

    return {
        get activeTab() {
            return activeTab;
        },
        setTab,
    };
}
