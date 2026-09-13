import { router } from '@inertiajs/svelte';
import { SvelteURLSearchParams, SvelteURL } from 'svelte/reactivity';
import { route } from 'ziggy-js';

interface UseSearchProps {
    isGamesPage?: boolean;
    debounceMs?: number;
}

const SEARCH_CHANGE_RESET_PARAMS = new Set(['search', 'page']);

export function getSearchFilterParams(search: string): SvelteURLSearchParams {
    const urlParams = new SvelteURLSearchParams(search);
    for (const key of SEARCH_CHANGE_RESET_PARAMS) urlParams.delete(key);
    return urlParams;
}

export function useSearch({ isGamesPage = false, debounceMs = 500 }: UseSearchProps = {}) {
    let searchTerm = $state('');
    let isSearching = $state(false);
    let lastSearchQuery = '';

    const getPathname = (urlOrPath: string) => {
        try {
            const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';

            return new SvelteURL(urlOrPath, base).pathname.replace(/\/+$/, '') || '/';
        } catch {
            return urlOrPath.replace(/\/+$/, '') || '/';
        }
    };

    const isCurrentGamesIndexPage = () => {
        if (typeof window === 'undefined') return isGamesPage;

        return getPathname(window.location.pathname) === getPathname(route('games.index'));
    };

    const gamesIndexUrl = (params?: SvelteURLSearchParams) => {
        const query = params?.toString();

        return query ? `${route('games.index')}?${query}` : route('games.index');
    };

    const syncSearchTermFromCurrentRoute = () => {
        if (typeof window === 'undefined') return;

        if (!isCurrentGamesIndexPage()) {
            searchTerm = '';
            lastSearchQuery = '';
            isSearching = false;

            return;
        }

        if (timeoutId !== null) return;
        const urlParams = new SvelteURLSearchParams(window.location.search);
        searchTerm = urlParams.get('search') ?? '';
        lastSearchQuery = searchTerm.trim();
    };

    const initializeSearchFromUrl = () => {
        syncSearchTermFromCurrentRoute();
    };

    // Keep local searchTerm in sync with the URL after navigations and back/forward
    $effect(() => {
        const stopNavigationListener = router.on('start', cancelPendingSearch);
        const stopNavigateListener = router.on('navigate', syncSearchTermFromCurrentRoute);
        const stopSuccessListener = router.on('success', syncSearchTermFromCurrentRoute);
        window.addEventListener('popstate', handlePopstate);

        return () => {
            cancelPendingSearch();
            stopNavigationListener();
            stopNavigateListener();
            stopSuccessListener();
            window.removeEventListener('popstate', handlePopstate);
        };
    });

    const getCurrentFilterParams = () => {
        if (typeof window === 'undefined' || !isCurrentGamesIndexPage()) return new SvelteURLSearchParams();

        return getSearchFilterParams(window.location.search);
    };

    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    const cancelPendingSearch = () => {
        if (timeoutId !== null) clearTimeout(timeoutId);
        timeoutId = null;
    };

    const handlePopstate = () => {
        cancelPendingSearch();
        syncSearchTermFromCurrentRoute();
    };

    const performLiveSearch = (searchQuery: string) => {
        cancelPendingSearch();

        timeoutId = setTimeout(() => {
            timeoutId = null;
            if (searchQuery.trim() === lastSearchQuery) {
                return;
            }

            lastSearchQuery = searchQuery.trim();

            if (searchQuery.trim().length >= 2) {
                isSearching = true;
                if (isCurrentGamesIndexPage()) {
                    const currentParams = getCurrentFilterParams();
                    const params = new SvelteURLSearchParams(currentParams);
                    params.set('search', searchQuery.trim());

                    router.visit(gamesIndexUrl(params), {
                        replace: true,
                        preserveState: true,
                        onFinish: () => {
                            isSearching = false;
                        },
                    });
                } else {
                    const params = new SvelteURLSearchParams();
                    params.set('search', searchQuery.trim());

                    router.visit(gamesIndexUrl(params), {
                        onFinish: () => {
                            isSearching = false;
                        },
                    });
                }
            } else if (searchQuery.trim().length === 0 && isCurrentGamesIndexPage()) {
                isSearching = true;

                const currentParams = getCurrentFilterParams();
                const params = new SvelteURLSearchParams(currentParams);
                params.delete('search');

                router.visit(gamesIndexUrl(params), {
                    replace: true,
                    preserveState: true,
                    onFinish: () => {
                        isSearching = false;
                    },
                });
            }
        }, debounceMs);
    };

    const handleSearchSubmit = (e: Event) => {
        e.preventDefault();
        cancelPendingSearch();
        lastSearchQuery = searchTerm.trim();

        const currentParams = getCurrentFilterParams();
        const params = new SvelteURLSearchParams(currentParams);
        if (searchTerm.trim()) {
            params.set('search', searchTerm.trim());
        } else {
            params.delete('search');
        }

        router.visit(gamesIndexUrl(params));
    };

    const handleSearchChange = (e: Event) => {
        const value = (e.target as HTMLInputElement).value;
        searchTerm = value;

        performLiveSearch(value);
    };

    const handleSearchClear = () => {
        cancelPendingSearch();
        searchTerm = '';
        lastSearchQuery = '';

        if (isCurrentGamesIndexPage()) {
            isSearching = true;

            const currentParams = getCurrentFilterParams();
            const params = new SvelteURLSearchParams(currentParams);

            router.visit(gamesIndexUrl(params), {
                preserveState: true,
                onFinish: () => {
                    isSearching = false;
                },
            });
        }
    };

    return {
        get searchTerm() {
            return searchTerm;
        },
        set searchTerm(value: string) {
            searchTerm = value;
        },
        get isSearching() {
            return isSearching;
        },
        set isSearching(value: boolean) {
            isSearching = value;
        },
        handleSearchSubmit,
        handleSearchChange,
        handleSearchClear,
        initializeSearchFromUrl,
        performLiveSearch,
    };
}
