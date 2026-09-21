import type { Config } from 'ziggy-js';

/**
 * Canonical paginator shape shared by the Pagination component and every page
 * that builds one. `from`/`to` are optional summaries; the component falls
 * back to "Page X of Y" when they are absent.
 */
export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page?: number;
    total: number;
    from?: number | null;
    to?: number | null;
}

export * from './game';
export * from './lists';

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
    sort?: string;
    direction?: string;
    perPage?: number;
    page?: number;
    noDefaults?: boolean;
    usingDefaultLanguages?: boolean;
    usingDefaultExcludedTags?: boolean;
}
