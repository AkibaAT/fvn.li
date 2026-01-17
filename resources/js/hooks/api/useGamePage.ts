import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getCsrfToken } from './client';

// Types
export interface Review {
  id: number;
  rating: number;
  review?: string;
  published_at: string;
  is_visible: boolean;
  is_reviewed: boolean;
  event_id?: string;
  rater: {
    id: number;
    name: string;
    external_platform?: string;
  };
}

export interface GamePageLanguage {
  id: number;
  iso_code: string;
  ref_name: string;
  flag_code: string;
}

export interface SupportedLanguage {
  iso_code: string;
  language: GamePageLanguage;
  is_available: boolean;
}

export interface LanguageStats {
  words?: number;
  language: GamePageLanguage;
}

export interface GamePageVersion {
  id: number;
  version: string;
  published_at: string;
  devlog?: string;
  is_windows?: boolean;
  is_linux?: boolean;
  is_mac?: boolean;
  is_android?: boolean;
  is_web?: boolean;
  supportedLanguages?: SupportedLanguage[];
  languageStats?: LanguageStats[];
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

export interface CharacterStatsData {
  languages: { id: string; flag: string; name: string; count: number }[];
  characters: string[];
  totalCharacters: number;
}

export interface FileStatsData {
  version?: { version?: string };
  file_categories?: {
    category: string;
    total_count: number;
    total_size: number;
    file_types: { extension: string; count: number; size: number }[];
  }[];
}

// Query keys
export const gamePageKeys = {
  all: ['game-page'] as const,
  reviews: (gameId: number, params: ReviewsParams) =>
    [...gamePageKeys.all, 'reviews', gameId, params] as const,
  versions: (gameId: number, page: number, perPage: number) =>
    [...gamePageKeys.all, 'versions', gameId, page, perPage] as const,
  characterStats: (gameSlug: string, versionId: number) =>
    [...gamePageKeys.all, 'character-stats', gameSlug, versionId] as const,
  fileStats: (gameSlug: string, versionId: number) =>
    [...gamePageKeys.all, 'file-stats', gameSlug, versionId] as const,
};

// Params
interface ReviewsParams {
  showAllRatings: boolean;
  selectedRating: number | null;
  page: number;
  perPage: number;
}

interface ReviewsResponse {
  reviews: Review[];
  pagination: PaginationMeta;
  availableRatings: number[];
}

interface VersionsResponse {
  versions: GamePageVersion[];
  pagination: PaginationMeta;
}

// API functions
async function fetchReviews(gameId: number, params: ReviewsParams): Promise<ReviewsResponse> {
  const response = await window.axios.get(route('react-api.games.reviews', { game: gameId }), {
    params: {
      showAllRatings: params.showAllRatings,
      selectedRating: params.selectedRating,
      perPage: params.perPage,
      page: params.page,
    },
  });

  if (!response.data.success) {
    throw new Error('Failed to fetch reviews');
  }

  return {
    reviews: response.data.reviews.data,
    pagination: {
      current_page: response.data.reviews.current_page,
      last_page: response.data.reviews.last_page,
      per_page: response.data.reviews.per_page,
      total: response.data.reviews.total,
      from: response.data.reviews.from,
      to: response.data.reviews.to,
    },
    availableRatings: response.data.availableRatings,
  };
}

async function fetchVersions(gameId: number, page: number, perPage: number): Promise<VersionsResponse> {
  const response = await window.axios.get(route('react-api.games.versions', { game: gameId }), {
    params: { page, perPage },
  });

  if (!response.data.success) {
    throw new Error('Failed to fetch versions');
  }

  return {
    versions: response.data.versions.data,
    pagination: {
      current_page: response.data.versions.current_page,
      last_page: response.data.versions.last_page,
      per_page: response.data.versions.per_page,
      total: response.data.versions.total,
      from: response.data.versions.from,
      to: response.data.versions.to,
    },
  };
}

async function fetchCharacterStats(gameSlug: string, versionId: number): Promise<CharacterStatsData> {
  const response = await window.axios.get(
    route('react-api.games.version.character-stats', {
      game: gameSlug,
      version: versionId,
    })
  );

  if (!response.data.success) {
    throw new Error('Failed to fetch character stats');
  }

  return response.data.data;
}

async function fetchFileStats(gameSlug: string, versionId: number): Promise<FileStatsData> {
  const response = await window.axios.get(
    route('react-api.games.version.file-stats', {
      game: gameSlug,
      version: versionId,
    })
  );

  if (!response.data.success) {
    throw new Error('Failed to fetch file stats');
  }

  return response.data.data;
}

interface UploadThumbnailParams {
  gameSlug: string;
  file: File;
}

async function uploadThumbnail({ gameSlug, file }: UploadThumbnailParams): Promise<{ thumbnail_url: string }> {
  const formData = new FormData();
  formData.append('thumbnail', file);

  const response = await window.axios.post(
    route('react-api.my-games.thumbnail.update', { game: gameSlug }),
    formData
  );

  if (!response.data.success) {
    throw new Error(response.data.message || 'Failed to upload thumbnail');
  }

  return { thumbnail_url: response.data.thumbnail_url };
}

// Hooks
export function useGameReviews(
  gameId: number,
  params: ReviewsParams,
  options?: { enabled?: boolean }
) {
  return useQuery({
    queryKey: gamePageKeys.reviews(gameId, params),
    queryFn: () => fetchReviews(gameId, params),
    enabled: options?.enabled ?? !!gameId,
    placeholderData: (previousData) => previousData,
  });
}

export function useGameVersionsPaginated(
  gameId: number,
  page: number,
  perPage: number,
  options?: { enabled?: boolean }
) {
  return useQuery({
    queryKey: gamePageKeys.versions(gameId, page, perPage),
    queryFn: () => fetchVersions(gameId, page, perPage),
    enabled: options?.enabled ?? !!gameId,
    placeholderData: (previousData) => previousData,
  });
}

export function useCharacterStats(
  gameSlug: string,
  versionId: number | null,
  options?: { enabled?: boolean }
) {
  return useQuery({
    queryKey: gamePageKeys.characterStats(gameSlug, versionId!),
    queryFn: () => fetchCharacterStats(gameSlug, versionId!),
    enabled: (options?.enabled ?? true) && !!versionId && !!gameSlug,
  });
}

export function useFileStats(
  gameSlug: string,
  versionId: number | null,
  options?: { enabled?: boolean }
) {
  return useQuery({
    queryKey: gamePageKeys.fileStats(gameSlug, versionId!),
    queryFn: () => fetchFileStats(gameSlug, versionId!),
    enabled: (options?.enabled ?? true) && !!versionId && !!gameSlug,
  });
}

export function useUploadThumbnail() {
  return useMutation({
    mutationFn: uploadThumbnail,
  });
}
