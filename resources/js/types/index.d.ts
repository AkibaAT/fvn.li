import {Config} from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    ziggy: Config & { location: string };
};

export interface Game {
    id: number;
    name: string;
    slug: string;
    url: string;
    thumb_url: string | null;
    authors: string | null;
    description: string | null;
    status: string;
    game_engine: string;
    is_nsfw: boolean;
    is_visible: boolean;
    tags: string | null;
    custom_tags: string | null;
    initially_published_at: string | null;
    latest_version_published_at: string | null;
    english_word_count: number | null;
    rating: number | null;
    rating_count: number | null;
    supported_languages: Array<{
        iso_code: string;
        ref_name: string;
        flag_code: string;
    }>;
    platforms: {
        windows: boolean;
        linux: boolean;
        mac: boolean;
        android: boolean;
        web: boolean;
    };
}

export type FilterType = 'status' | 'engine' | 'platform' | 'language' | 'nsfw' | 'sfw';

export interface FilterOptions {
    statuses: Record<string, string>;
    gameEngines: Record<string, string>;
    platforms: Record<string, string>;
    languages: Record<string, {
        ref_name: string;
        flag_code: string;
    }>;
}

export interface RouteParams<T extends string> {
    [key: string]: any;
}

declare module '@inertiajs/core' {
    interface PageProps {
        games: {
            data: Game[];
            meta: {
                current_page: number;
                from: number;
                last_page: number;
                per_page: number;
                to: number;
                total: number;
            };
        };
        filterOptions: FilterOptions;
    }
}
