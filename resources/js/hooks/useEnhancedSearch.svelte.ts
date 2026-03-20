import { route } from 'ziggy-js';
import http from '@/utils/http';

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

export function useEnhancedSearch(getOptions: () => UseEnhancedSearchOptions) {
    const options = getOptions();
    const { type, defaultPerPage = 20, debounceMs = 300 } = options;

    let results = $state<SearchResult[]>([]);
    let globalResults = $state<GlobalSearchResponse['data'] | null>(null);
    let pagination = $state<SearchPagination>({
        current_page: 1,
        last_page: 1,
        per_page: defaultPerPage,
        total: 0,
    });
    let loading = $state(false);
    let error = $state<string | null>(null);

    let debounceTimeout: ReturnType<typeof setTimeout> | undefined = undefined;
    let abortController: AbortController | undefined = undefined;

    // Cleanup on teardown
    $effect(() => {
        return () => {
            if (debounceTimeout) clearTimeout(debounceTimeout);
            if (abortController) abortController.abort();
        };
    });

    const performSearch = async (query: string, filters: SearchFilters, page: number, perPage: number, signal: AbortSignal) => {
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

        const response = await http.get(endpoint, {
            params,
            signal,
        });

        if (response.data?.success) {
            if (type === 'global') {
                globalResults = response.data.data;
                results = [];
            } else {
                results = response.data.data || [];
                pagination = response.data.pagination || {
                    current_page: 1,
                    last_page: 1,
                    per_page: defaultPerPage,
                    total: 0,
                };
                globalResults = null;
            }
        } else {
            error = response.data?.message || 'Search failed';
        }
    };

    const search = async (query: string, filters: SearchFilters = {}, page: number = 1, perPage: number = defaultPerPage) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        if (abortController) abortController.abort();

        if (!query.trim()) {
            results = [];
            globalResults = null;
            error = null;
            return;
        }

        debounceTimeout = setTimeout(async () => {
            loading = true;
            error = null;
            abortController = new AbortController();

            try {
                await performSearch(query, filters, page, perPage, abortController.signal);
            } catch (err: unknown) {
                if ((err as Error)?.name !== 'AbortError') {
                    console.error('Search failed:', err);
                    error = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'An error occurred while searching';
                }
            } finally {
                loading = false;
            }
        }, debounceMs);
    };

    const searchInstant = async (query: string, filters: SearchFilters = {}, page: number = 1, perPage: number = defaultPerPage) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        if (abortController) abortController.abort();

        if (!query.trim()) {
            results = [];
            globalResults = null;
            error = null;
            return;
        }

        loading = true;
        error = null;
        abortController = new AbortController();

        try {
            await performSearch(query, filters, page, perPage, abortController.signal);
        } catch (err: unknown) {
            if ((err as Error)?.name !== 'AbortError') {
                console.error('Search failed:', err);
                error = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'An error occurred while searching';
            }
        } finally {
            loading = false;
        }
    };

    const clear = () => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        if (abortController) abortController.abort();
        results = [];
        globalResults = null;
        error = null;
        loading = false;
    };

    return {
        get results() {
            return results;
        },
        get globalResults() {
            return globalResults;
        },
        get pagination() {
            return pagination;
        },
        get loading() {
            return loading;
        },
        get error() {
            return error;
        },
        search,
        searchInstant,
        clear,
    };
}
