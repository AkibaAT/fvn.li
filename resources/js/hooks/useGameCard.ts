import {useTagResizeObserver} from '@/hooks/useTagResizeObserver';
import {router} from '@inertiajs/react';
import {useState} from 'react';
import type {GameCardPlatform} from './usePlatformIcons';
import {SCREENSHOT_VARIANTS} from '@/constants/screenshot-variants';
import type {OptimizedScreenshotVariants, ScreenshotVariant} from '@/constants/screenshot-variants';

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
        optimized?: OptimizedScreenshotVariants;
    }>;
    english_word_count?: number | null;
    initially_published_at?: string | null;
    latest_version_published_at?: string | null;
    rating_score?: number | null;
    rating_count?: number | null;
    // visibility / extra
    is_nsfw?: boolean;
    is_paid?: boolean;
    has_demo?: boolean;
    is_on_sale?: boolean;
    // store platform (itch.io, steam, other)
    platform?: 'itch_io' | 'steam' | 'other';
    // game platforms (OS support)
    is_windows?: boolean;
    is_linux?: boolean;
    is_mac?: boolean;
    is_android?: boolean;
    is_web?: boolean;
    // tags & languages
    tags?: Array<{ id: number; name: string }>;
    supported_languages?: Array<{
        iso_code: string;
        ref_name: string;
        flag_code: string;
    }>;
    // user progress
    user_progress?: Array<{
        id: number;
        game_id: number;
        user_id: number;
        receive_updates: boolean;
    }>;
    // user list memberships
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
    // Optional selection styling (used on listing page)
    selectedTags?: string[];
    selectedPlatforms?: string[];
    selectedLanguages?: string[];
    selectedStatuses?: string[];
    selectedStorePlatforms?: string[];
    nsfw?: boolean;
    showPaid?: boolean;
    showDemo?: boolean;
    showSale?: boolean;
    // Optional filter handlers; if omitted, will navigate to games index with the respective filter
    onTagClick?: (tagId: string) => void;
    onPlatformClick?: (platform: GameCardPlatform) => void;
    onLanguageClick?: (iso: string) => void;
    onStatusClick?: (status: string) => void;
    onStorePlatformClick?: (platform: string) => void;
    onNsfwToggle?: () => void;
    onPaidToggle?: () => void;
    onDemoToggle?: () => void;
    onSaleToggle?: () => void;
}

export function useGameCard({game, selectedTags, onTagClick, onPlatformClick, onLanguageClick, onStatusClick, onStorePlatformClick, onNsfwToggle, onPaidToggle, onDemoToggle, onSaleToggle}: GameCardProps) {
    // Image handling
    const getOptimizedScreenshotUrl = (
        screenshot: NonNullable<GameCardGame['screenshots']>[number],
        variant: ScreenshotVariant = SCREENSHOT_VARIANTS.DEFAULT,
        fallbackToOriginal: boolean = true
    ): string | null => {
        if (!screenshot) return null;

        // Try optimized version first
        if (screenshot.optimized?.[variant]?.path) {
            // SSR-safe: use relative path
            return `/storage/${screenshot.optimized[variant].path}`;
        }

        // Fallback to original URLs if requested
        if (fallbackToOriginal) {
            if (variant === SCREENSHOT_VARIANTS.SMALL || variant === SCREENSHOT_VARIANTS.DEFAULT) {
                return screenshot.thumbnail_url || screenshot.url || null;
            }
            return screenshot.url || null;
        }

        return null;
    };

    const getThumbnailUrl = (): string | null => {
        // Try optimized thumbnail first
        if (game.optimized_thumbnails?.default?.path) {
            // SSR-safe: use relative path
            return `/storage/${game.optimized_thumbnails.default.path}`;
        }

        // Fallback to regular thumbnail
        if (game.thumb_url) return game.thumb_url;

        // Try first screenshot with optimized version
        const first = game.screenshots?.[0];
        if (first) {
            return getOptimizedScreenshotUrl(first, SCREENSHOT_VARIANTS.DEFAULT, true);
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
        router.get(route('games.index'), params, {preserveState: false});
    };

    const handleTag = (id: number) => {
        if (onTagClick) return onTagClick(String(id));
        navigateWith({selectedTags: [String(id)]});
    };

    const handlePlatform = (platform: GameCardPlatform) => {
        if (onPlatformClick) return onPlatformClick(platform);
        navigateWith({selectedPlatforms: [platform]});
    };

    const handleLanguage = (iso: string) => {
        if (onLanguageClick) return onLanguageClick(iso);
        navigateWith({selectedLanguages: [iso]});
    };

    const handleStatus = (status: string) => {
        if (onStatusClick) return onStatusClick(status);
        navigateWith({selectedStatuses: [status]});
    };

    const handleStorePlatform = (platform: string) => {
        if (onStorePlatformClick) return onStorePlatformClick(platform);
        navigateWith({selectedStorePlatforms: [platform]});
    };

    const handleNsfwToggle = () => {
        if (onNsfwToggle) return onNsfwToggle();
        navigateWith({nsfw: true});
    };

    const handlePaidToggle = () => {
        if (onPaidToggle) return onPaidToggle();
        navigateWith({showPaid: true});
    };

    const handleDemoToggle = () => {
        if (onDemoToggle) return onDemoToggle();
        navigateWith({showDemo: true});
    };

    const handleSaleToggle = () => {
        if (onSaleToggle) return onSaleToggle();
        navigateWith({showSale: true});
    };

    // Tag ordering and state
    const orderedTags = game.tags && game.tags.length > 0
        ? [...game.tags].sort((a, b) => {
            const aSelected = selectedTags?.includes(String(a.id)) ?? false;
            const bSelected = selectedTags?.includes(String(b.id)) ?? false;
            if (aSelected === bSelected) return 0;
            return aSelected ? -1 : 1;
        })
        : [];

    // Tag resize observer
    const {containerRef, hiddenTagCount, setTagRef} = useTagResizeObserver({
        enabled: orderedTags.length > 0,
    });

    const [tagsExpanded, setTagsExpanded] = useState(false);

    return {
        // Image handling
        thumbnailUrl: getThumbnailUrl(),
        getOptimizedScreenshotUrl,

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

        // Tags
        orderedTags,
        tagContainerRef: containerRef,
        hiddenTagCount,
        setTagRef,
        tagsExpanded,
        setTagsExpanded,
    };
}