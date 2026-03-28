import { useTagResizeObserver } from '@/hooks/useTagResizeObserver.svelte';
import { router } from '@inertiajs/svelte';
import { SvelteURLSearchParams } from 'svelte/reactivity';
import type { GameCardPlatform } from './usePlatformIcons';

export interface GameCardGame {
    id: number;
    name: string;
    effective_name: string;
    slug: string;
    status?: string | null;
    authors?: string;
    thumb_url?: string | null;
    optimized_thumbnails?: { default?: { path: string } } | null;
    screenshots?: Array<{
        url?: string;
        thumbnail_url?: string;
    }>;
    english_word_count?: number | null;
    primary_word_count?: number | null;
    primary_language_label?: string | null;
    initially_published_at?: string | null;
    latest_version_published_at?: string | null;
    rating_score?: number | null;
    rating_count?: number | null;
    is_nsfw?: boolean;
    is_paid?: boolean;
    has_demo?: boolean;
    is_on_sale?: boolean;
    is_delisted?: boolean;
    platform?: 'itch_io' | 'steam' | 'other';
    is_windows?: boolean;
    is_linux?: boolean;
    is_mac?: boolean;
    is_android?: boolean;
    is_web?: boolean;
    tags?: Array<{ id: number; name: string }>;
    supported_languages?: Array<{
        iso_code: string;
        ref_name: string;
        flag_code: string;
    }>;
    user_progress?: Array<{
        id: number;
        game_id: number;
        user_id: number;
        receive_updates: boolean;
    }>;
    user_list_memberships?: Array<{
        list_id: number;
        name: string;
        type: string;
        is_default: boolean;
    }>;
    [key: string]: unknown;
}

export interface GameCardProps {
    game: GameCardGame;
    fixedHeight?: boolean;
    selectedTags?: string[];
    selectedPlatforms?: string[];
    selectedLanguages?: string[];
    selectedStatuses?: string[];
    selectedStorePlatforms?: string[];
    nsfw?: boolean;
    showPaid?: boolean;
    showDemo?: boolean;
    showSale?: boolean;
    delisted?: boolean;
    ignoredGameIds?: number[];
    onTagClick?: (tagId: string) => void;
    onPlatformClick?: (platform: GameCardPlatform) => void;
    onLanguageClick?: (iso: string) => void;
    onStatusClick?: (status: string) => void;
    onStorePlatformClick?: (platform: string) => void;
    onNsfwToggle?: () => void;
    onPaidToggle?: () => void;
    onDemoToggle?: () => void;
    onSaleToggle?: () => void;
    onDelistedToggle?: () => void;
    onIgnoreToggle?: (gameId: number, isIgnored: boolean, ignoredGameIds: number[]) => void;
}

export function useGameCard({
    game,
    selectedTags,
    onTagClick,
    onPlatformClick,
    onLanguageClick,
    onStatusClick,
    onStorePlatformClick,
    onNsfwToggle,
    onPaidToggle,
    onDemoToggle,
    onSaleToggle,
    onDelistedToggle,
}: GameCardProps) {
    const getThumbnailUrl = (): string | null => {
        if (game.optimized_thumbnails?.default?.path) {
            return `/storage/${game.optimized_thumbnails.default.path}`;
        }

        if (game.thumb_url) return game.thumb_url;

        const first = game.screenshots?.[0];
        if (first) {
            return first.thumbnail_url || first.url || null;
        }

        return null;
    };

    // Authors formatting
    const authorsInlineHtml = game.authors
        ? game.authors
              .replace(/<br\s*\/?>(\s*)/gi, ' ')
              .replace(/\n+/g, ' ')
              .replace(/\s{2,}/g, ' ')
              .trim()
        : '';

    // Navigation helpers
    const navigateWith = (params: Record<string, string | string[] | boolean>) => {
        const searchParams = new SvelteURLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (Array.isArray(value)) {
                value.forEach((v) => searchParams.append(`${key}[]`, v));
            } else {
                searchParams.set(key, String(value));
            }
        }
        router.visit(`/games?${searchParams.toString()}`);
    };

    const handleTag = (id: number) => {
        if (onTagClick) return onTagClick(String(id));
        navigateWith({ selectedTags: [String(id)] });
    };

    const handlePlatform = (platform: GameCardPlatform) => {
        if (onPlatformClick) return onPlatformClick(platform);
        navigateWith({ selectedPlatforms: [platform] });
    };

    const handleLanguage = (iso: string) => {
        if (onLanguageClick) return onLanguageClick(iso);
        navigateWith({ selectedLanguages: [iso] });
    };

    const handleStatus = (status: string) => {
        if (onStatusClick) return onStatusClick(status);
        navigateWith({ selectedStatuses: [status] });
    };

    const handleStorePlatform = (platform: string) => {
        if (onStorePlatformClick) return onStorePlatformClick(platform);
        navigateWith({ selectedStorePlatforms: [platform] });
    };

    const handleNsfwToggle = () => {
        if (onNsfwToggle) return onNsfwToggle();
        navigateWith({ nsfw: true });
    };

    const handlePaidToggle = () => {
        if (onPaidToggle) return onPaidToggle();
        navigateWith({ showPaid: true });
    };

    const handleDemoToggle = () => {
        if (onDemoToggle) return onDemoToggle();
        navigateWith({ showDemo: true });
    };

    const handleSaleToggle = () => {
        if (onSaleToggle) return onSaleToggle();
        navigateWith({ showSale: true });
    };

    const handleDelistedToggle = () => {
        if (onDelistedToggle) return onDelistedToggle();
        navigateWith({ delisted: true });
    };

    // Tag ordering and state
    const orderedTags =
        game.tags && game.tags.length > 0
            ? [...game.tags].sort((a, b) => {
                  const aSelected = selectedTags?.includes(String(a.id)) ?? false;
                  const bSelected = selectedTags?.includes(String(b.id)) ?? false;
                  if (aSelected === bSelected) return 0;
                  return aSelected ? -1 : 1;
              })
            : [];

    // Tag resize observer
    const tagObserver = useTagResizeObserver({
        enabled: orderedTags.length > 0,
    });

    let tagsExpanded = $state(false);

    // Language resize observer
    const languageObserver = useTagResizeObserver({
        enabled: (game.supported_languages?.length ?? 0) > 0,
    });

    let languagesExpanded = $state(false);

    return {
        // Image handling
        thumbnailUrl: getThumbnailUrl(),

        // Content
        authorsInlineHtml,

        // Navigation handlers
        handleTag,
        handlePlatform,
        handleLanguage,
        handleStatus,
        handleStorePlatform,
        handleNsfwToggle,
        handlePaidToggle,
        handleDemoToggle,
        handleSaleToggle,
        handleDelistedToggle,

        // Tags
        orderedTags,
        get tagContainerRef() {
            return tagObserver.containerRef;
        },
        get hiddenTagCount() {
            return tagObserver.hiddenTagCount;
        },
        setTagRef: tagObserver.setTagRef,
        get tagsExpanded() {
            return tagsExpanded;
        },
        setTagsExpanded(value: boolean) {
            tagsExpanded = value;
        },

        // Languages
        get languageContainerRef() {
            return languageObserver.containerRef;
        },
        get hiddenLanguageCount() {
            return languageObserver.hiddenTagCount;
        },
        setLanguageRef: languageObserver.setTagRef,
        get languagesExpanded() {
            return languagesExpanded;
        },
        setLanguagesExpanded(value: boolean) {
            languagesExpanded = value;
        },
    };
}
