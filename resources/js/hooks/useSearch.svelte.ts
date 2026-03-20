import { router } from '@inertiajs/svelte';
import { SvelteURLSearchParams, SvelteURL } from 'svelte/reactivity';

interface UseSearchProps {
    isGamesPage?: boolean;
    debounceMs?: number;
}

export function useSearch({ isGamesPage = false, debounceMs = 500 }: UseSearchProps = {}) {
    let searchTerm = $state('');
    let isSearching = $state(false);
    let lastSearchQuery = '';

    // Get search term from URL if on games page
    const initializeSearchFromUrl = () => {
        if (isGamesPage) {
            const urlParams = new SvelteURLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            if (searchParam) {
                searchTerm = searchParam;
            }
        }
    };

    // Keep local searchTerm in sync with the URL after navigations and back/forward
    $effect(() => {
        const syncFromUrl = () => {
            const urlParams = new SvelteURLSearchParams(window.location.search);
            const searchParam = urlParams.get('search') ?? '';
            searchTerm = searchParam;
        };

        window.addEventListener('popstate', syncFromUrl);
        return () => {
            window.removeEventListener('popstate', syncFromUrl);
        };
    });

    // Helper function to get current filter parameters from URL
    const getCurrentFilterParams = () => {
        if (typeof window === 'undefined') return {};

        const urlParams = new SvelteURLSearchParams(window.location.search);
        const params: Record<string, string> = {};

        for (const [key, value] of urlParams.entries()) {
            if (key !== 'search') {
                params[key] = value;
            }
        }

        return params;
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
                if (isGamesPage) {
                    if (typeof window !== 'undefined') {
                        window.dispatchEvent(new CustomEvent('fvn:search:start'));
                    }

                    const currentParams = getCurrentFilterParams();
                    const params = new SvelteURLSearchParams(currentParams);
                    params.set('search', searchQuery.trim());

                    router.visit(`/games?${params.toString()}`, {
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
                    router.visit(`/games?search=${encodeURIComponent(searchQuery.trim())}`, {
                        onFinish: () => {
                            isSearching = false;
                        },
                    });
                }
            } else if (searchQuery.trim().length === 0 && isGamesPage) {
                isSearching = true;

                const currentParams = getCurrentFilterParams();
                const params = new SvelteURLSearchParams(currentParams);
                params.delete('search');

                router.visit(`/games?${params.toString()}`, {
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
        params.set('search', searchTerm);

        router.visit(`/games?${params.toString()}`);
    };

    // Handle search input change with live search
    const handleSearchChange = (e: Event) => {
        const value = (e.target as HTMLInputElement).value;
        searchTerm = value;

        // Always update URL search parameter to keep it in sync
        const url = new SvelteURL(window.location.href);
        if (value.trim()) {
            url.searchParams.set('search', value.trim());
        } else {
            url.searchParams.delete('search');
        }
        window.history.replaceState({}, '', url.toString());

        performLiveSearch(value);
    };

    // Handle search clear
    const handleSearchClear = () => {
        searchTerm = '';
        lastSearchQuery = '';

        // Remove search parameter from URL
        const url = new SvelteURL(window.location.href);
        url.searchParams.delete('search');
        window.history.replaceState({}, '', url.toString());

        if (isGamesPage) {
            isSearching = true;

            const currentParams = getCurrentFilterParams();
            const params = new SvelteURLSearchParams(currentParams);

            router.visit(`/games?${params.toString()}`, {
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
