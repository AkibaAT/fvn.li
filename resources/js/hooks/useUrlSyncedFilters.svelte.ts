import { router } from '@inertiajs/svelte';
import { SvelteURL, SvelteURLSearchParams } from 'svelte/reactivity';

type FilterValue = string | number | boolean | null | undefined;

type Options = {
    route: string;
    only: string[];
    getParams: () => Record<string, FilterValue>;
};

export function serializeUrlFilters(values: Record<string, FilterValue>): URLSearchParams {
    const params = new SvelteURLSearchParams();
    for (const [key, value] of Object.entries(values)) {
        if (value !== undefined && value !== null && value !== '') params.set(key, String(value));
    }
    return params;
}

export function useUrlSyncedFilters({ route: targetRoute, only, getParams }: Options) {
    let isLoading = $state(false);
    let lastQuery: string | undefined;

    // Equal-but-new param objects re-run this effect; only a change in the serialized query navigates.
    $effect(() => {
        const desired = serializeUrlFilters(getParams());
        const query = desired.toString();
        const previous = lastQuery;
        lastQuery = query;
        if (previous === undefined || previous === query) return;

        if (typeof window === 'undefined') return;
        if (query === new SvelteURLSearchParams(window.location.search).toString()) return;

        isLoading = true;
        router.get(targetRoute, Object.fromEntries(desired.entries()), {
            preserveScroll: true,
            preserveState: true,
            only,
            onFinish: () => {
                isLoading = false;
            },
        });
    });

    function buildPageUrl(page: number): string {
        const params = serializeUrlFilters({ ...getParams(), page });
        const url = new SvelteURL(targetRoute, typeof window === 'undefined' ? 'http://localhost' : window.location.origin);
        url.search = params.toString();
        return `${url.pathname}${url.search}`;
    }

    return {
        get isLoading() {
            return isLoading;
        },
        buildPageUrl,
    };
}
