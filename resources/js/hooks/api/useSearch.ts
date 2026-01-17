import { useQuery } from '@tanstack/react-query';

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

export interface GlobalSearchData {
  games: SearchResult[];
  dialogue: SearchResult[];
  total_games: number;
  total_dialogue: number;
}

export const searchKeys = {
  all: ['search'] as const,
  games: (query: string, filters: SearchFilters, page: number, perPage: number) =>
    [...searchKeys.all, 'games', query, filters, page, perPage] as const,
  dialogue: (query: string, filters: SearchFilters, page: number, perPage: number) =>
    [...searchKeys.all, 'dialogue', query, filters, page, perPage] as const,
  global: (query: string, limit: number) =>
    [...searchKeys.all, 'global', query, limit] as const,
};

interface SearchParams {
  query: string;
  filters?: SearchFilters;
  page?: number;
  perPage?: number;
}

interface SearchResponse {
  results: SearchResult[];
  pagination: SearchPagination;
  searchEngine?: string;
}

async function fetchGameSearch({ query, filters = {}, page = 1, perPage = 20 }: SearchParams): Promise<SearchResponse> {
  const response = await window.axios.get(route('api.games.search-enhanced'), {
    params: {
      q: query.trim(),
      page,
      perPage,
      ...filters,
    },
  });

  if (!response.data?.success) {
    throw new Error(response.data?.message || 'Search failed');
  }

  return {
    results: response.data.data || [],
    pagination: response.data.pagination || {
      current_page: 1,
      last_page: 1,
      per_page: perPage,
      total: 0,
    },
    searchEngine: response.data.search_engine,
  };
}

async function fetchDialogueSearch({ query, filters = {}, page = 1, perPage = 20 }: SearchParams): Promise<SearchResponse> {
  const response = await window.axios.get(route('react-api.dialogue.search-enhanced'), {
    params: {
      q: query.trim(),
      page,
      perPage,
      ...filters,
    },
  });

  if (!response.data?.success) {
    throw new Error(response.data?.message || 'Search failed');
  }

  return {
    results: response.data.data || [],
    pagination: response.data.pagination || {
      current_page: 1,
      last_page: 1,
      per_page: perPage,
      total: 0,
    },
    searchEngine: response.data.search_engine,
  };
}

async function fetchGlobalSearch(query: string, limit: number): Promise<GlobalSearchData> {
  const response = await window.axios.get(route('api.search.global'), {
    params: { q: query.trim(), limit },
  });

  if (!response.data?.success) {
    throw new Error(response.data?.message || 'Search failed');
  }

  return response.data.data;
}

// Hooks

export function useGameSearch(
  query: string,
  filters: SearchFilters = {},
  page = 1,
  perPage = 20,
  options?: { enabled?: boolean }
) {
  const trimmedQuery = query.trim();

  return useQuery({
    queryKey: searchKeys.games(trimmedQuery, filters, page, perPage),
    queryFn: () => fetchGameSearch({ query: trimmedQuery, filters, page, perPage }),
    enabled: (options?.enabled ?? true) && trimmedQuery.length > 0,
    placeholderData: (previousData) => previousData,
    staleTime: 1000 * 60 * 5, // 5 minutes
  });
}

export function useDialogueEnhancedSearch(
  query: string,
  filters: SearchFilters = {},
  page = 1,
  perPage = 20,
  options?: { enabled?: boolean }
) {
  const trimmedQuery = query.trim();

  return useQuery({
    queryKey: searchKeys.dialogue(trimmedQuery, filters, page, perPage),
    queryFn: () => fetchDialogueSearch({ query: trimmedQuery, filters, page, perPage }),
    enabled: (options?.enabled ?? true) && trimmedQuery.length > 0,
    placeholderData: (previousData) => previousData,
    staleTime: 1000 * 60 * 5, // 5 minutes
  });
}

export function useGlobalSearch(query: string, limit = 20, options?: { enabled?: boolean }) {
  const trimmedQuery = query.trim();

  return useQuery({
    queryKey: searchKeys.global(trimmedQuery, limit),
    queryFn: () => fetchGlobalSearch(trimmedQuery, limit),
    enabled: (options?.enabled ?? true) && trimmedQuery.length > 0,
    placeholderData: (previousData) => previousData,
    staleTime: 1000 * 60 * 5, // 5 minutes
  });
}
