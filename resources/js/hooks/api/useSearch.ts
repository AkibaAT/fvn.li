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

export interface GlobalSearchData {
  games: SearchResult[];
  dialogue: SearchResult[];
  total_games: number;
  total_dialogue: number;
}

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

export async function fetchGameSearch({ query, filters = {}, page = 1, perPage = 20 }: SearchParams): Promise<SearchResponse> {
  const response = await http.get(route('api.games.search-enhanced'), {
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

export async function fetchDialogueEnhancedSearch({ query, filters = {}, page = 1, perPage = 20 }: SearchParams): Promise<SearchResponse> {
  const response = await http.get(route('browser-api.dialogue.search-enhanced'), {
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

export async function fetchGlobalSearch(query: string, limit: number = 20): Promise<GlobalSearchData> {
  const response = await http.get(route('api.search.global'), {
    params: { q: query.trim(), limit },
  });

  if (!response.data?.success) {
    throw new Error(response.data?.message || 'Search failed');
  }

  return response.data.data;
}
