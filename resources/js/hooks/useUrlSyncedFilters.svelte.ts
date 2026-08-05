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
    let didMount = false;

    $effect(() => {
        const desired = serializeUrlFilters(getParams());

        if (!didMount) {
            didMount = true;
            return;
        }

        if (typeof window === 'undefined') return;
        if (desired.toString() === new SvelteURLSearchParams(window.location.search).toString()) return;

        isLoading = true;
        router.get(targetRoute, Object.fromEntries(desired.entries()), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
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
