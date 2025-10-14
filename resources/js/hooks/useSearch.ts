import {useCallback, useMemo, useState, useRef, useEffect} from 'react';
import {router} from '@inertiajs/react';

interface UseSearchProps {
    isGamesPage?: boolean;
    debounceMs?: number;
}

export function useSearch({isGamesPage = false, debounceMs = 500}: UseSearchProps = {}) {
    const [searchTerm, setSearchTerm] = useState('');
    const [isSearching, setIsSearching] = useState(false);
    const isSearchingRef = useRef(false);
    const lastSearchQueryRef = useRef('');

    // Get search term from URL if on games page
    const initializeSearchFromUrl = useCallback(() => {
        if (isGamesPage) {
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            if (searchParam) {
                setSearchTerm(searchParam);
            }
        }
    }, [isGamesPage]);


    // Keep local searchTerm in sync with the URL after Inertia navigations and back/forward
    useEffect(() => {
        const syncFromUrl = () => {
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search') ?? '';
            setSearchTerm(searchParam);
        };
        // Listen to multiple Inertia events for reliability across versions
        document.addEventListener('inertia:success', syncFromUrl);
        document.addEventListener('inertia:finish', syncFromUrl);
        document.addEventListener('inertia:complete', syncFromUrl);
        window.addEventListener('popstate', syncFromUrl);
        return () => {
            document.removeEventListener('inertia:success', syncFromUrl);
            document.removeEventListener('inertia:finish', syncFromUrl);
            document.removeEventListener('inertia:complete', syncFromUrl);
            window.removeEventListener('popstate', syncFromUrl);
        };
    }, []);

    // Helper function to get current filter parameters from URL
    const getCurrentFilterParams = useCallback(() => {
        if (typeof window === 'undefined') return {};

        const urlParams = new URLSearchParams(window.location.search);
        const params: Record<string, string> = {};

        // Copy all existing URL parameters except 'search'
        for (const [key, value] of urlParams.entries()) {
            if (key !== 'search') {
                params[key] = value;
            }
        }

        return params;
    }, []);

    // Live search functionality - memoized to prevent re-renders
    const performLiveSearch = useMemo(() => {
        let timeoutId: NodeJS.Timeout | null = null;

        return (searchQuery: string) => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            timeoutId = setTimeout(() => {
                // Prevent duplicate searches for the same query
                if (searchQuery.trim() === lastSearchQueryRef.current) {
                    return;
                }

                lastSearchQueryRef.current = searchQuery.trim();
                isSearchingRef.current = true;

                if (searchQuery.trim().length >= 2) {
                    setIsSearching(true);
                    if (isGamesPage) {
                        // If on games page, update with live filtering
                        if (typeof window !== 'undefined') {
                            window.dispatchEvent(new CustomEvent('fvn:search:start'));
                        }

                        // Preserve existing filter parameters
                        const currentParams = getCurrentFilterParams();
                        const params = {
                            ...currentParams,
                            search: searchQuery.trim(),
                        };

                        router.get(
                            route('games.index'),
                            params,
                            {
                                preserveState: true,
                                preserveScroll: true,
                                only: ['games', 'currentFilters'],
                                onFinish: () => {
                                    setIsSearching(false);
                                    isSearchingRef.current = false;
                                    if (typeof window !== 'undefined') {
                                        window.dispatchEvent(new CustomEvent('fvn:search:finish'));
                                    }
                                }
                            }
                        );
                    } else {
                        // If not on games page, navigate to games page with search only
                        // (since we're coming from outside the games page, we don't preserve existing filters)
                        router.get(
                            route('games.index'),
                            {search: searchQuery.trim()},
                            {
                                preserveState: false,
                                onFinish: () => {
                                    setIsSearching(false);
                                    isSearchingRef.current = false;
                                }
                            }
                        );
                    }
                } else if (searchQuery.trim().length === 0 && isGamesPage) {
                    // Clear search if empty and we're on games page
                    setIsSearching(true);

                    // Preserve existing filter parameters but remove search
                    const currentParams = getCurrentFilterParams();

                    router.get(
                        route('games.index'),
                        currentParams,
                        {
                            preserveState: true,
                            preserveScroll: true,
                            only: ['games', 'currentFilters'],
                            onFinish: () => {
                                setIsSearching(false);
                                isSearchingRef.current = false;
                            }
                        }
                    );
                }
            }, debounceMs);
        };
    }, [isGamesPage, debounceMs, getCurrentFilterParams]);

    // Handle search form submission
    const handleSearchSubmit = useCallback((e: React.FormEvent) => {
        e.preventDefault();

        // Preserve existing filter parameters
        const currentParams = getCurrentFilterParams();
        const params = {
            ...currentParams,
            search: searchTerm,
        };

        router.get(
            route('games.index'),
            params,
            {preserveState: false},
        );
    }, [searchTerm, getCurrentFilterParams]);

    // Handle search input change with live search
    const handleSearchChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setSearchTerm(value);

        // Always update URL search parameter to keep it in sync
        const url = new URL(window.location.href);
        if (value.trim()) {
            url.searchParams.set('search', value.trim());
        } else {
            url.searchParams.delete('search');
        }
        window.history.replaceState({}, '', url.toString());

        performLiveSearch(value);
    }, [performLiveSearch]);

    // Handle search clear
    const handleSearchClear = useCallback(() => {
        setSearchTerm('');
        lastSearchQueryRef.current = '';

        // Remove search parameter from URL
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        window.history.replaceState({}, '', url.toString());

        if (isGamesPage) {
            setIsSearching(true);
            isSearchingRef.current = true;

            // Preserve existing filter parameters but remove search
            const currentParams = getCurrentFilterParams();

            router.get(
                route('games.index'),
                currentParams,
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['games', 'currentFilters'],
                    onFinish: () => {
                        setIsSearching(false);
                        isSearchingRef.current = false;
                    }
                }
            );
        }
    }, [isGamesPage, getCurrentFilterParams]);

    return {
        searchTerm,
        setSearchTerm,
        isSearching,
        setIsSearching,
        handleSearchSubmit,
        handleSearchChange,
        handleSearchClear,
        initializeSearchFromUrl,
        performLiveSearch,
    };
}