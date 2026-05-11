import { router } from '@inertiajs/svelte';
import { SvelteURLSearchParams, SvelteURL } from 'svelte/reactivity';
import { route } from 'ziggy-js';

interface UseSearchProps {
    isGamesPage?: boolean;
    debounceMs?: number;
}

const SEARCH_CHANGE_RESET_PARAMS = new Set(['search', 'page']);

export function getSearchFilterParams(search: string): Record<string, string> {
    const urlParams = new SvelteURLSearchParams(search);
    const params: Record<string, string> = {};

    for (const [key, value] of urlParams.entries()) {
        if (!SEARCH_CHANGE_RESET_PARAMS.has(key)) {
            params[key] = value;
        }
    }

    return params;
}

export function useSearch({ isGamesPage = false, debounceMs = 500 }: UseSearchProps = {}) {
    let searchTerm = $state('');
    let isSearching = $state(false);
    let lastSearchQuery = '';

    const getPathname = (urlOrPath: string) => {
        try {
            const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';

            return new URL(urlOrPath, base).pathname.replace(/\/+$/, '') || '/';
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

        const urlParams = new SvelteURLSearchParams(window.location.search);
        searchTerm = urlParams.get('search') ?? '';
        lastSearchQuery = searchTerm.trim();
    };

    // Get search term from URL if on games index page
    const initializeSearchFromUrl = () => {
        syncSearchTermFromCurrentRoute();
    };

    const updateGamesSearchUrl = (value: string) => {
        if (typeof window === 'undefined' || !isCurrentGamesIndexPage()) {
            return;
        }

        const url = new SvelteURL(window.location.href);
        if (value.trim()) {
            url.searchParams.set('search', value.trim());
        } else {
            url.searchParams.delete('search');
        }
        window.history.replaceState({}, '', url.toString());
    };

    // Keep local searchTerm in sync with the URL after navigations and back/forward
    $effect(() => {
        window.addEventListener('popstate', syncSearchTermFromCurrentRoute);
        document.addEventListener('inertia:complete', syncSearchTermFromCurrentRoute as EventListener);

        return () => {
            window.removeEventListener('popstate', syncSearchTermFromCurrentRoute);
            document.removeEventListener('inertia:complete', syncSearchTermFromCurrentRoute as EventListener);
        };
    });

    // Helper function to get current filter parameters from URL
    const getCurrentFilterParams = () => {
        if (typeof window === 'undefined') return {};

        return getSearchFilterParams(window.location.search);
    };

    // Live search functionality
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    const performLiveSearch = (searchQuery: string) => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => {
            // Prevent duplicate searches for the same query
            if (searchQuery.trim() === lastSearchQuery) {
                return;
            }

            lastSearchQuery = searchQuery.trim();

            if (searchQuery.trim().length >= 2) {
                isSearching = true;
                if (isCurrentGamesIndexPage()) {
                    if (typeof window !== 'undefined') {
                        window.dispatchEvent(new CustomEvent('fvn:search:start'));
                    }

                    const currentParams = getCurrentFilterParams();
                    const params = new SvelteURLSearchParams(currentParams);
                    params.set('search', searchQuery.trim());

                    router.visit(gamesIndexUrl(params), {
                        replace: true,
                        preserveState: true,
                        onFinish: () => {
                            isSearching = false;
                            if (typeof window !== 'undefined') {
                                window.dispatchEvent(new CustomEvent('fvn:search:finish'));
                            }
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

    // Handle search form submission
    const handleSearchSubmit = (e: Event) => {
        e.preventDefault();

        const currentParams = getCurrentFilterParams();
        const params = new SvelteURLSearchParams(currentParams);
        if (searchTerm.trim()) {
            params.set('search', searchTerm.trim());
        } else {
            params.delete('search');
        }

        router.visit(gamesIndexUrl(params));
    };

    // Handle search input change with live search
    const handleSearchChange = (e: Event) => {
        const value = (e.target as HTMLInputElement).value;
        searchTerm = value;

        updateGamesSearchUrl(value);

        performLiveSearch(value);
    };

    // Handle search clear
    const handleSearchClear = () => {
        searchTerm = '';
        lastSearchQuery = '';

        updateGamesSearchUrl('');

        if (isCurrentGamesIndexPage()) {
            isSearching = true;

            const currentParams = getCurrentFilterParams();
            const params = new SvelteURLSearchParams(currentParams);

            router.visit(gamesIndexUrl(params), {
                replace: true,
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
