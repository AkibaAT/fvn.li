/**
 * Shared game payload shapes. Pages and components used to declare their own
 * `Game` interface; these types are the union of those declarations. `Game`
 * carries the common core with permissive optionality, and domain views extend
 * it, re-strengthening the fields their controller always sends.
 */

type GamePlatform = 'itch_io' | 'steam' | 'other';

interface OptimizedThumbnailVariant {
    path: string;
    width?: number;
    height?: number;
}

interface OptimizedThumbnails {
    default?: OptimizedThumbnailVariant;
}

export interface GameVersion {
    id: number;
    version: string;
    published_at: string;
}

interface GameRating {
    id: number;
    game_id: number;
    user_id: number;
    rating: number;
    is_reviewed: boolean;
}

export interface UserGameProgress {
    id: number;
    user_id: number;
    game_id: number;
    game_version_id?: number;
    personal_notes?: string;
    started_at?: string;
    completed_at?: string;
    game_version?: GameVersion;
    is_receiving_updates?: boolean;
}

interface GameTag {
    id: number;
    name: string;
    slug?: string;
}

interface GameJamRef {
    id: number;
    name: string;
}

interface LanguageRef {
    iso_code: string;
    ref_name: string;
    flag_code: string;
}

export interface Game {
    id: number;
    name: string;
    effective_name: string;
    slug: string;
    description?: string;
    thumb_url?: string;
    optimized_thumbnails?: OptimizedThumbnails | null;
    authors?: string;
    rating_score?: number | null;
    rating_count?: number | null;
    status?: string;
    game_engine?: string;
    platform?: GamePlatform;
    is_nsfw?: boolean;
    is_paid?: boolean;
    has_demo?: boolean;
    is_on_sale?: boolean;
    min_price?: number;
    english_word_count?: number | null;
    primary_word_count?: number | null;
    primary_language_name?: string | null;
    tags?: GameTag[];
    gameJams?: GameJamRef[];
    supported_languages?: LanguageRef[];
    is_windows?: boolean;
    is_linux?: boolean;
    is_mac?: boolean;
    is_android?: boolean;
    is_web?: boolean;
    trending_score?: number;
    initially_published_at?: string;
    latest_version?: GameVersion;
    latest_version_published_at?: string;
    game_versions?: GameVersion[];
    user_progress?: UserGameProgress[];
    ratings?: GameRating[];
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
}

/**
 * The editable-slice of a game the custom-page editors mutate. Both the name
 * and content editors receive this same shape.
 */
export interface EditableGame {
    id: number;
    effective_name?: string;
    custom_name?: string | null;
    custom_description?: string | null;
    effective_description?: string;
    full_description?: string;
    description?: string;
    has_custom_page?: boolean;
    [key: string]: any;
}
