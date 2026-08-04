import type { MetaTags } from '@/components/seo/SeoHead.svelte';

interface Tag {
    id: number;
    name: string;
    slug: string;
    created_at: string;
    updated_at: string;
    pivot: any;
}

interface GameJam {
    id: number;
    name: string;
    slug?: string;
    url?: string;
    description?: string;
    start_date?: string;
    end_date?: string;
    theme?: string;
    submission_count?: number;
    participant_count?: number;
    host?: string;
    created_at: string;
    updated_at: string;
    pivot: any;
}

interface Language {
    id: number;
    iso_code: string;
    ref_name: string;
    flag_code: string;
}

export interface SupportedLanguage {
    iso_code: string;
    language: Language;
    is_available: boolean;
}

interface LanguageStats {
    words?: number;
    language: Language;
}

export interface GameVersion {
    id: number;
    version: string;
    published_at: string;
    devlog?: string;
    is_windows?: boolean;
    is_linux?: boolean;
    is_mac?: boolean;
    is_android?: boolean;
    is_web?: boolean;
    has_route_data?: boolean;
    supportedLanguages?: SupportedLanguage[];
    languageStats?: LanguageStats[];
}

export interface Screenshot {
    id: number;
    url: string;
    thumbnail_url: string;
    optimized?: Record<string, { path?: string }>;
}

interface AdditionalLink {
    id: number | string;
    name: string;
    url: string;
    platform?: string;
    last_edited_at?: string;
}

interface Rater {
    id: number;
    name: string;
    external_platform?: string;
}

interface ReviewUser {
    id: number;
    name: string;
    avatar?: string;
}

export interface Review {
    id: number;
    rating: number;
    review?: string;
    published_at: string;
    is_visible: boolean;
    is_reviewed: boolean;
    has_spoilers?: boolean;
    event_id?: string;
    previous_ratings_count?: number;
    rater: Rater;
    user?: ReviewUser | null;
}

interface UserReview {
    id: number;
    rating: number;
    review: string;
    has_spoilers: boolean;
    published_at: string;
    updated_at: string;
}

interface Game {
    id: number;
    name: string;
    slug: string;
    effective_name: string;
    description?: string;
    full_description?: string;
    custom_name?: string;
    custom_description?: string;
    effective_description?: string;
    has_custom_page?: boolean;
    view_mode?: 'custom' | 'original';
    thumb_url?: string;
    optimized_thumbnail_url?: string;
    rating?: number;
    rating_score?: number;
    rating_count?: number;
    initially_published_at?: string;
    authors?: string;
    game_engine?: string;
    status?: string;
    is_nsfw?: boolean;
    is_paid?: boolean;
    has_demo?: boolean;
    min_price?: number;
    currency?: string;
    current_price?: number;
    original_price?: number;
    formatted_current_price?: string;
    formatted_original_price?: string;
    is_on_sale?: boolean;
    sale_discount_percent?: number;
    discount_percentage?: number;
    url?: string;
    platform?: 'itch_io' | 'steam' | 'other';
    primary_url?: string | null;
    custom_css?: string;
    custom_tags?: string;
    is_visible?: boolean;
    is_delisted?: boolean;
    created_at: string;
    updated_at: string;
    tags?: Tag[];
    game_jams?: GameJam[];
    latest_version?: GameVersion;
    additional_links?: AdditionalLink[];
    screenshots?: Screenshot[];
    custom_screenshots?: Screenshot[] | null;
    effective_screenshots?: Screenshot[];
    [key: string]: any;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Paginated<T> {
    data: T[];
    meta?: Partial<PaginationMeta>;
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    from?: number;
    to?: number;
}

interface DailyStats {
    date: string;
    page_views_unique: number;
    page_views_total: number;
    external_project_unique: number;
    external_project_total: number;
    custom_links_unique: number;
    custom_links_total: number;
}

interface ClickStats {
    page_views_total: number;
    page_views_unique: number;
    last_page_view?: string;
    external_project_total: number;
    external_project_unique: number;
    last_external_project?: string;
    custom_links?: Array<{
        link_id: string;
        link_name: string;
        total_clicks: number;
        unique_clicks: number;
        last_click?: string;
    }>;
}

interface EditPermissions {
    canEdit: boolean;
    hasCustomPage: boolean;
    isOwner: boolean;
    isAdmin: boolean;
}

interface PublicList {
    id: number;
    name: string;
    description?: string;
    type: string;
    entries_count: number;
    created_at: string;
    user: { id: number; name: string; avatar?: string };
}

interface SimilarGame {
    id: number;
    name: string;
    slug: string;
    thumb_url?: string;
    authors?: string;
    platform?: string;
    rating_score?: number;
    rating_count?: number;
    status?: string;
}

interface EstimatedReadingTime {
    hours: number;
    minutes: number;
    total_minutes: number;
    word_count: number;
}

export interface GameShowProps {
    game: Game;
    reviews?: Paginated<Review>;
    gameVersions?: Paginated<GameVersion>;
    supportedLanguages?: SupportedLanguage[];
    englishStats?: LanguageStats;
    primaryStats?: LanguageStats;
    primaryLanguageLabel?: string;
    versionCharacterCounts?: Record<number, number>;
    versionHasFileStats?: Record<number, boolean>;
    versionHasDialogueLines?: Record<number, boolean>;
    versionHasRouteData?: Record<number, boolean>;
    versionOptimizedArchiveAvailability?: Record<number, boolean>;
    availableRatings?: number[];
    platforms?: { windows: boolean; linux: boolean; mac: boolean; android: boolean; web: boolean };
    canSeeAnalytics?: boolean;
    clickStats?: ClickStats;
    dailyStats?: DailyStats[];
    editPermissions?: EditPermissions;
    userReview?: UserReview | null;
    publicLists?: PublicList[];
    publicListsCount?: number;
    similarGames?: SimilarGame[];
    developerGames?: SimilarGame[];
    estimatedReadingTime?: EstimatedReadingTime | null;
    metaTags?: MetaTags;
}
