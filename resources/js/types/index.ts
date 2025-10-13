import type {Config} from 'ziggy-js';

export interface Auth {
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
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

// List related types
export interface VnList {
    id: number;
    name: string;
    description?: string;
    type: string;
    is_public: boolean;
    is_default: boolean;
    user_id: number;
    created_at: string;
    updated_at: string;
    entries_count?: number;
    featured_games?: Game[];
}

export interface ListEntry {
    id: number;
    vn_list_id: number;
    game_id: number;
    sort_order: number;
    private_notes?: string;
    created_at: string;
    updated_at: string;
    game: Game;
}

// Pagination
export interface PaginationData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    prev_page_url?: string;
    next_page_url?: string;
}

// API Response types
export interface ApiResponse<T = unknown> {
    success: boolean;
    message?: string;
    data?: T;
    errors?: Record<string, string[]>;
}

// Props for components
export interface PageProps {
    auth: {
        user?: User;
    };
    ziggy: {
        location: string;
        previous?: string;
    };
    flash: {
        success?: string;
        error?: string;
    };
    metaTags: {
        title: string;
        description: string;
        image?: string;
    };
    indicators?: {
        pending_invites: number;
        unread_notifications: number;
    };

    [key: string]: unknown;
}

// Filter types
export interface GameFilters {
    search: string;
    selectedStatuses: string[];
    selectedEngines: string[];
    selectedPlatforms: string[];
    selectedLanguages: string[];
    selectedGameJams: string[];
    selectedTags: string[];
    nsfw: boolean;
    sfw: boolean;
    showPaid: boolean;
    showFree: boolean;
    showDemo: boolean;
    sortField: string;
    sortDirection: string;
    perPage: number;
    page: number;
}

export interface FilterOptions {
    statuses: Record<string, string>;
    gameEngines: Record<string, string>;
    platforms: Record<string, string>;
    languages: Record<string, { ref_name: string; flag_code: string }>;
    gameJams: Record<string, string>;
    tags: Record<string, string>;
    sortOptions?: Record<string, string>;
}

export interface CurrentFilters {
    search?: string;
    selectedStatuses?: string[];
    selectedEngines?: string[];
    selectedPlatforms?: string[];
    selectedLanguages?: string[];
    selectedGameJams?: string[];
    selectedTags?: string[];
    nsfw?: boolean;
    sfw?: boolean;
    showPaid?: boolean;
    showFree?: boolean;
    showDemo?: boolean;
    sort?: string;
    direction?: string;
    perPage?: number;
    page?: number;
}