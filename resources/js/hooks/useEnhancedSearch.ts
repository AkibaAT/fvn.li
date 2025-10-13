import { useState, useCallback, useRef } from 'react';
import { route } from 'ziggy-js';

export interface SearchFilters {
    language?: string;
    gameNames?: string[];
    characterNames?: string[];
    status?: string[];
    is_nsfw?: boolean;
    is_paid?: boolean;
    has_demo?: boolean;
    game_engine?: string[];
    tags?: string[];
    supported_languages?: string[];
}

export interface SearchResult {
    id: number;
    [key: string]: unknown;
}

export interface SearchPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface SearchResponse {
    success: boolean;
    data: SearchResult[];
    pagination: SearchPagination;
    search_engine?: string;
    error?: string;
    message?: string;
}

export interface GlobalSearchResponse {
    success: boolean;
    data: {
        games: SearchResult[];
        dialogue: SearchResult[];
        total_games: number;
        total_dialogue: number;
    };
    search_engine?: string;
}

export interface UseEnhancedSearchOptions {
    type: 'games' | 'dialogue' | 'global';
    defaultPerPage?: number;
    debounceMs?: number;
}

export function useEnhancedSearch(options: UseEnhancedSearchOptions) {
    const { type, defaultPerPage = 20, debounceMs = 300 } = options;
    
    const [results, setResults] = useState<SearchResult[]>([]);
    const [globalResults, setGlobalResults] = useState<GlobalSearchResponse['data'] | null>(null);
    const [pagination, setPagination] = useState<SearchPagination>({
        current_page: 1,
        last_page: 1,
        per_page: defaultPerPage,
        total: 0,
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    
    const debounceRef = useRef<NodeJS.Timeout | undefined>(undefined);
    const abortControllerRef = useRef<AbortController | undefined>(undefined);

    const search = useCallback(async (
        query: string,
        filters: SearchFilters = {},
        page: number = 1,
        perPage: number = defaultPerPage
    ) => {
        // Clear previous debounce
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        // Abort previous request
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }

        if (!query.trim()) {
            setResults([]);
            setGlobalResults(null);
            setError(null);
            return;
        }

        debounceRef.current = setTimeout(async () => {
            setLoading(true);
            setError(null);

            // Create new abort controller
            abortControllerRef.current = new AbortController();

            try {
                let endpoint: string;
                let params: Record<string, unknown> = {
                    q: query.trim(),
                    page,
                    perPage,
                    ...filters,
                };

                switch (type) {
                    case 'games':
                        endpoint = route('api.games.search-enhanced');
                        break;
                    case 'dialogue':
                        endpoint = route('react-api.dialogue.search-enhanced');
                        break;
                    case 'global':
                        endpoint = route('api.search.global');
                        params = { q: query.trim(), limit: perPage };
                        break;
                    default:
                        throw new Error(`Unknown search type: ${type}`);
                }

                const response = await window.axios.get(endpoint, {
                    params,
                    signal: abortControllerRef.current.signal,
                });

                if (response.data?.success) {
                    if (type === 'global') {
                        setGlobalResults(response.data.data);
                        setResults([]);
                    } else {
                        setResults(response.data.data || []);
                        setPagination(response.data.pagination || {
                            current_page: 1,
                            last_page: 1,
                            per_page: defaultPerPage,
                            total: 0,
                        });
                        setGlobalResults(null);
                    }
                } else {
                    setError(response.data?.message || 'Search failed');
                }
            } catch (err: unknown) {
                if ((err as Error)?.name !== 'AbortError') {
                    console.error('Search failed:', err);
                    setError((err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'An error occurred while searching');
                }
            } finally {
                setLoading(false);
            }
        }, debounceMs);
    }, [type, defaultPerPage, debounceMs]);

    const searchInstant = useCallback(async (
        query: string,
        filters: SearchFilters = {},
        page: number = 1,
        perPage: number = defaultPerPage
    ) => {
        // Clear debounce for instant search
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        // Abort previous request
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }

        if (!query.trim()) {
            setResults([]);
            setGlobalResults(null);
            setError(null);
            return;
        }

        setLoading(true);
        setError(null);

        // Create new abort controller
        abortControllerRef.current = new AbortController();

        try {
            let endpoint: string;
            let params: Record<string, unknown> = {
                q: query.trim(),
                page,
                perPage,
                ...filters,
            };

            switch (type) {
                case 'games':
                    endpoint = route('api.games.search-enhanced');
                    break;
                case 'dialogue':
                    endpoint = route('react-api.dialogue.search-enhanced');
                    break;
                case 'global':
                    endpoint = route('api.search.global');
                    params = { q: query.trim(), limit: perPage };
                    break;
                default:
                    throw new Error(`Unknown search type: ${type}`);
            }

            const response = await window.axios.get(endpoint, {
                params,
                signal: abortControllerRef.current.signal,
            });

            if (response.data?.success) {
                if (type === 'global') {
                    setGlobalResults(response.data.data);
                    setResults([]);
                } else {
                    setResults(response.data.data || []);
                    setPagination(response.data.pagination || {
                        current_page: 1,
                        last_page: 1,
                        per_page: defaultPerPage,
                        total: 0,
                    });
                    setGlobalResults(null);
                }
            } else {
                setError(response.data?.message || 'Search failed');
            }
        } catch (err: unknown) {
            if ((err as Error)?.name !== 'AbortError') {
                console.error('Search failed:', err);
                setError((err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'An error occurred while searching');
            }
        } finally {
            setLoading(false);
        }
    }, [type, defaultPerPage]);

    const clear = useCallback(() => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }
        setResults([]);
        setGlobalResults(null);
        setError(null);
        setLoading(false);
    }, []);

    return {
        results,
        globalResults,
        pagination,
        loading,
        error,
        search,
        searchInstant,
        clear,
    };
}
