import { useQuery } from '@tanstack/react-query';

export interface DialogueSearchFilters {
  q?: string;
  language?: string;
  gameId?: number;
  versionId?: number;
  characterId?: string;
  context?: string;
  perPage?: number;
  page?: number;
  exactMatch?: boolean;
}

export interface DialoguePagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface DialogueSearchResult {
  id: number;
  text_content: string;
  highlighted_text: string;
  context: string | null;
  file_path: string | null;
  line_number: number | null;
  character_id: string | null;
  character_name: string | null;
  iso_code: string | null;
  game_version_id: number;
  game: { id: number; name: string } | null;
  version: { id: number; version: string } | null;
}

export interface DialogueLanguage {
  id: string;
  name: string;
  flag?: string | null;
}

export interface DialogueVersion {
  id: number;
  version: string;
  published_at?: string | null;
}

export interface DialogueCharacter {
  id: number;
  character_id: string;
  name: string;
}

export interface DialogueOptions {
  versions: DialogueVersion[];
  languages: DialogueLanguage[];
  characters: DialogueCharacter[];
  contexts: string[];
}

export interface DialogueVersionStats {
  totalLines: number;
  totalWords: number;
  uniqueCharacters: number;
  avgWordsPerLine: number;
}

export interface DuplicateExample {
  game_name: string;
  version: string;
  character_id: string | null;
  character_display_name?: string | null;
  iso_code: string;
  context?: string | null;
  file_path?: string | null;
  line_number?: number | null;
}

export interface DuplicateItem {
  text_id: number;
  text_content: string;
  usage_count: number;
  examples?: DuplicateExample[];
}

export interface WordFrequencyItem {
  text: string;
  value: number;
}

export const dialogueKeys = {
  all: ['dialogue'] as const,
  options: (gameId: number, versionId?: number | null, language?: string) =>
    [...dialogueKeys.all, 'options', gameId, versionId, language] as const,
  versionStats: (versionId: number) =>
    [...dialogueKeys.all, 'version-stats', versionId] as const,
  search: (filters: DialogueSearchFilters) =>
    [...dialogueKeys.all, 'search', filters] as const,
  duplicates: (params: DuplicatesParams) =>
    [...dialogueKeys.all, 'duplicates', params] as const,
  wordFrequency: (versionId: number, language: string) =>
    [...dialogueKeys.all, 'word-frequency', versionId, language] as const,
};

interface OptionsParams {
  gameId: number;
  versionId?: number | null;
  language?: string;
}

async function fetchDialogueOptions({ gameId, versionId, language }: OptionsParams): Promise<DialogueOptions> {
  const resp = await window.axios.get(route('react-api.dialogue.options'), {
    params: {
      gameId,
      versionId: versionId ?? undefined,
      language,
    },
  });

  if (!resp.data?.success) throw new Error('Failed to fetch options');

  return {
    versions: resp.data.versions || [],
    languages: resp.data.languages || [],
    characters: resp.data.characters || [],
    contexts: resp.data.contexts || [],
  };
}

async function fetchVersionStats(versionId: number): Promise<DialogueVersionStats> {
  const resp = await window.axios.get(route('react-api.dialogue.version-stats'), {
    params: { versionId },
  });

  if (!resp.data?.success) throw new Error('Failed to fetch version stats');

  const s = resp.data.data;
  return {
    totalLines: s.total_lines ?? 0,
    totalWords: s.total_words ?? 0,
    uniqueCharacters: s.unique_characters ?? 0,
    avgWordsPerLine: Number(s.avg_words_per_line ?? 0),
  };
}

interface SearchResponse {
  results: DialogueSearchResult[];
  pagination: DialoguePagination;
}

async function fetchDialogueSearch(filters: DialogueSearchFilters): Promise<SearchResponse> {
  const resp = await window.axios.get(route('react-api.dialogue.search'), {
    params: {
      q: filters.q,
      language: filters.language,
      gameId: filters.gameId ?? undefined,
      versionId: filters.versionId ?? undefined,
      characterId: filters.characterId || undefined,
      context: filters.context || undefined,
      perPage: filters.perPage,
      page: filters.page,
      exactMatch: filters.exactMatch ? 1 : undefined,
    },
  });

  if (!resp.data?.success) throw new Error('Search failed');

  return {
    results: resp.data.data || [],
    pagination: resp.data.pagination || {
      current_page: 1,
      per_page: filters.perPage || 25,
      total: 0,
      last_page: 0,
    },
  };
}

interface DuplicatesParams {
  language: string;
  gameId?: number;
  versionId?: number | null;
  characterId?: string;
  minLineLength: number;
  minDuplicateCount: number;
  limit: number;
}

async function fetchDuplicates(params: DuplicatesParams): Promise<DuplicateItem[]> {
  const resp = await window.axios.get(route('react-api.dialogue.duplicates'), {
    params: {
      language: params.language,
      gameId: params.gameId ?? undefined,
      versionId: params.versionId ?? undefined,
      characterId: params.characterId || undefined,
      minLineLength: params.minLineLength,
      minDuplicateCount: params.minDuplicateCount,
      limit: params.limit,
    },
  });

  if (!resp.data?.success) throw new Error('Failed to fetch duplicates');
  return resp.data.data || [];
}

interface WordFrequencyParams {
  versionId: number;
  language: string;
}

async function fetchWordFrequency({ versionId, language }: WordFrequencyParams): Promise<WordFrequencyItem[]> {
  const resp = await window.axios.get(route('react-api.dialogue.word-frequency'), {
    params: {
      versionId,
      language,
      limit: 100,
      includePhrases: true,
      minWordLength: 3,
    },
  });

  if (!resp.data?.success) throw new Error('Failed to fetch word frequency');
  return resp.data.data || [];
}

// Hooks

export function useDialogueOptions(gameId: number, versionId?: number | null, language?: string) {
  return useQuery({
    queryKey: dialogueKeys.options(gameId, versionId, language),
    queryFn: () => fetchDialogueOptions({ gameId, versionId, language }),
    enabled: !!gameId,
  });
}

export function useDialogueVersionStats(versionId: number | null) {
  return useQuery({
    queryKey: dialogueKeys.versionStats(versionId!),
    queryFn: () => fetchVersionStats(versionId!),
    enabled: !!versionId,
  });
}

export function useDialogueSearch(filters: DialogueSearchFilters, options?: { enabled?: boolean }) {
  const hasQuery = !!filters.q?.trim();
  const hasVersion = !!filters.versionId;

  return useQuery({
    queryKey: dialogueKeys.search(filters),
    queryFn: () => fetchDialogueSearch(filters),
    enabled: (options?.enabled ?? true) && hasQuery && hasVersion,
    placeholderData: (previousData) => previousData, // Keep previous data while loading
  });
}

export function useDialogueDuplicates(params: DuplicatesParams, options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: dialogueKeys.duplicates(params),
    queryFn: () => fetchDuplicates(params),
    enabled: options?.enabled ?? !!params.versionId,
  });
}

export function useWordFrequency(versionId: number | null, language: string) {
  return useQuery({
    queryKey: dialogueKeys.wordFrequency(versionId!, language),
    queryFn: () => fetchWordFrequency({ versionId: versionId!, language }),
    enabled: !!versionId,
  });
}
