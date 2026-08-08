import type { Config } from 'ziggy-js';

interface Auth {
    user: User;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    gameFilters: FilterOptions;

    [key: string]: unknown;
}

// User related types
export interface User {
    id: number;
    name: string;
    email?: string;
    avatar?: string;
    created_at: string;
    updated_at: string;
}

export interface SocialAccount {
    id: number;
    provider: string;
    provider_id: string;
    display_name?: string;
    avatar?: string;
    email?: string;
    created_at: string;
    updated_at: string;
}

// Game related types
export interface Game {
    id: number;
    name: string;
    slug: string;
    description?: string;
    thumbnail?: string;
    is_nsfw: boolean;
    is_paid: boolean;
    has_demo: boolean;
    rating_score?: number;
    rating_count?: number;
    status: string;
    authors?: string;
    game_engine?: string;
    platform?: string; // 'itch_io' | 'steam' | 'other'
    primary_url?: string | null;
    effective_name?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface FilterOptions {
    statuses: Record<string, string>;
    gameEngines: Record<string, string>;
    platforms: Record<string, string>;
    storePlatforms: Record<string, string>;
    languages: Record<string, { ref_name: string; flag_code: string }>;
    gameJams: Record<string, string>;
    tags: Record<string, string>;
    sortOptions?: Record<string, string>;
    readingTimeOptions?: Record<string, string>;
}

export interface CurrentFilters {
    search?: string;
    selectedStatuses?: string[];
    selectedEngines?: string[];
    selectedPlatforms?: string[];
    selectedStorePlatforms?: string[];
    selectedLanguages?: string[];
    selectedGameJams?: string[];
    selectedTags?: string[];
    excludedTags?: string[];
    readingTime?: string;
    nsfw?: boolean;
    sfw?: boolean;
    showPaid?: boolean;
    showFree?: boolean;
    showDemo?: boolean;
    showSale?: boolean;
    showIgnored?: boolean;
    delisted?: boolean;
    sort?: string;
    direction?: string;
    perPage?: number;
    page?: number;
    noDefaults?: boolean;
    usingDefaultLanguages?: boolean;
    usingDefaultExcludedTags?: boolean;
}
