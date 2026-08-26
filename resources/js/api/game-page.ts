import http from '@/utils/http';

// Types
interface Review {
    id: number;
    rating: number;
    review?: string;
    published_at: string;
    is_visible: boolean;
    is_reviewed: boolean;
    has_spoilers?: boolean;
    event_id?: string;
    source_platform?: string;
    rater?: {
        id: number;
        name: string;
        external_platform?: string;
    } | null;
    user?: {
        id: number;
        name: string;
        avatar?: string;
    } | null;
}

interface GamePageLanguage {
    id: number;
    iso_code: string;
    ref_name: string;
    flag_code: string;
}

interface SupportedLanguage {
    iso_code: string;
    language: GamePageLanguage;
    is_available: boolean;
}

interface LanguageStats {
    words?: number;
    language: GamePageLanguage;
}

interface GamePageVersion {
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

interface PaginationMeta {
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
    versionHasFileStats: Record<number, boolean>;
    versionOptimizedArchiveAvailability: Record<number, boolean>;
}

// API functions
export async function fetchReviews(gameId: number, params: ReviewsParams): Promise<ReviewsResponse> {
    const response = await http.get(route('browser-api.games.reviews', { game: gameId }), {
        params: {
            showAllRatings: params.showAllRatings,
            selectedRating: params.selectedRating,
            perPage: params.perPage,
            page: params.page,
            _refresh: Date.now(),
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

export async function fetchVersions(gameId: number, page: number, perPage: number): Promise<VersionsResponse> {
    const response = await http.get(route('browser-api.games.versions', { game: gameId }), {
        params: { page, perPage },
    });

    if (!response.data.success) {
        throw new Error('Failed to fetch versions');
    }

    return {
        versions: response.data.versions.data,
        versionHasFileStats: response.data.versionHasFileStats ?? {},
        versionOptimizedArchiveAvailability: response.data.versionOptimizedArchiveAvailability ?? {},
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

export async function fetchCharacterStats(gameSlug: string, versionId: number): Promise<CharacterStatsData> {
    const response = await http.get(
        route('browser-api.games.version.character-stats', {
            game: gameSlug,
            version: versionId,
        }),
    );

    if (!response.data.success) {
        throw new Error('Failed to fetch character stats');
    }

    return response.data.data;
}

export async function fetchFileStats(gameSlug: string, versionId: number): Promise<FileStatsData> {
    const response = await http.get(
        route('browser-api.games.version.file-stats', {
            game: gameSlug,
            version: versionId,
        }),
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

export async function uploadThumbnail({ gameSlug, file }: UploadThumbnailParams): Promise<{ thumbnail_url: string }> {
    const formData = new FormData();
    formData.append('thumbnail', file);

    const response = await http.post(route('browser-api.my-games.thumbnail.update', { game: gameSlug }), formData);

    if (!response.data.success) {
        throw new Error(response.data.message || 'Failed to upload thumbnail');
    }

    return { thumbnail_url: response.data.thumbnail_url };
}
