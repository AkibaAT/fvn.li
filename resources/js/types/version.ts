import {Ref} from "vue";

export interface SupportedLanguage {
    iso_code: string;
    ref_name: string;
    flag_code: string;
}

export interface EnglishStats {
    blocks: number;
    words: number;
    menus: number;
    options: number;
}

export interface Version {
    id: number;
    version: string;
    published_at: string;
    rating: number | null;
    rating_count: number | null;
    is_windows: boolean;
    is_linux: boolean;
    is_mac: boolean;
    is_android: boolean;
    is_web: boolean;
    english_stats: EnglishStats | null;
    supported_languages: SupportedLanguage[];
    file_categories: boolean;
}

export interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface VersionPagination {
    current_page: number;
    data: Version[];
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    links: PageLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
}

export interface CharacterStats {
    characters: string[];
    languages: Array<{
        id: string;
        name: string;
        flag: string;
    }>;
    wordCounts: Record<string, Record<string, number>>;
    languageTotals: Record<string, number>;
}

export interface FileCategory {
    category: string;
    total_count: number;
    total_size: number;
    file_types: Array<{
        extension: string;
        count: number;
        size: number;
    }>;
}

export interface GameDetailStore {
    versions: { value: Ref<VersionPagination | null> };
    loadingVersions: { value: Ref<boolean> };
    characterStats: { value: Ref<CharacterStats | null> };
    fileStats: { value: Ref<FileCategory[] | null> };
    loadingStats: { value: Ref<boolean> };
    loadVersions: (gameId: number, perPage?: number, page?: number) => Promise<void>;
    loadCharacterStats: (gameId: number, versionId: number) => Promise<void>;
    loadFileStats: (gameId: number, versionId: number) => Promise<void>;
}
