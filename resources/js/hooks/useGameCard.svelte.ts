import { page, router } from '@inertiajs/svelte';
import { SvelteURLSearchParams } from 'svelte/reactivity';
import type { DelistedReason } from '@/types/game';
import { formatAuthorsInline, getGameThumbnail } from '@/utils/game-card-display';
import { usePlatformIcons, type GameCardPlatform } from './usePlatformIcons';
import { useStorePlatformIcons } from './useStorePlatformIcons';

/** Inertia shared auth prop; the cards only check for a signed-in user. */
type GameCardAuth = { user?: unknown };

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
    primary_language_name?: string | null;
    initially_published_at?: string | null;
    latest_version_published_at?: string | null;
    rating_score?: number | null;
    rating_count?: number | null;
    is_nsfw?: boolean;
    is_paid?: boolean;
    has_demo?: boolean;
    is_on_sale?: boolean;
    delisted_at?: string | null;
    delisted_reason?: DelistedReason | null;
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
        is_receiving_updates: boolean;
    }>;
    user_private_tags?: Array<{ id: number; name: string }>;
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
    showLost?: boolean;
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
    onLostToggle?: () => void;
}

export function useGameCard(props: GameCardProps) {
    const { getSupportedPlatforms } = usePlatformIcons();
    const { getStorePlatformFromString } = useStorePlatformIcons();

    // Session/state and derived display data stay $derived because Inertia
    // partial reloads update props without remounting cards.
    const thumbnailUrl = $derived(getGameThumbnail(props.game) || null);
    const authorsInlineHtml = $derived(formatAuthorsInline(props.game.authors));
    const auth = $derived(page.props.auth as GameCardAuth | undefined);
    const isIgnored = $derived(props.ignoredGameIds?.includes(props.game.id) || false);
    const supportedPlatforms = $derived(getSupportedPlatforms(props.game));
    const storePlatform = $derived(props.game.platform ? getStorePlatformFromString(props.game.platform) : 'itch_io');

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
        if (props.onTagClick) return props.onTagClick(String(id));
        navigateWith({ selectedTags: [String(id)] });
    };

    const handlePlatform = (platform: GameCardPlatform) => {
        if (props.onPlatformClick) return props.onPlatformClick(platform);
        navigateWith({ selectedPlatforms: [platform] });
    };

    const handleLanguage = (iso: string) => {
        if (props.onLanguageClick) return props.onLanguageClick(iso);
        navigateWith({ selectedLanguages: [iso] });
    };

    const handleStatus = (status: string) => {
        if (props.onStatusClick) return props.onStatusClick(status);
        navigateWith({ selectedStatuses: [status] });
    };

    const handleStorePlatform = (platform: string) => {
        if (props.onStorePlatformClick) return props.onStorePlatformClick(platform);
        navigateWith({ selectedStorePlatforms: [platform] });
    };

    const handleNsfwToggle = () => {
        if (props.onNsfwToggle) return props.onNsfwToggle();
        navigateWith({ nsfw: true });
    };

    const handlePaidToggle = () => {
        if (props.onPaidToggle) return props.onPaidToggle();
        navigateWith({ showPaid: true });
    };

    const handleDemoToggle = () => {
        if (props.onDemoToggle) return props.onDemoToggle();
        navigateWith({ showDemo: true });
    };

    const handleSaleToggle = () => {
        if (props.onSaleToggle) return props.onSaleToggle();
        navigateWith({ showSale: true });
    };

    const handleLostToggle = () => {
        if (props.onLostToggle) return props.onLostToggle();
        navigateWith({ showLost: true });
    };

    // Tag ordering and state
    const orderedTags = $derived(
        props.game.tags && props.game.tags.length > 0
            ? [...props.game.tags].sort((a, b) => {
                  const aSelected = props.selectedTags?.includes(String(a.id)) ?? false;
                  const bSelected = props.selectedTags?.includes(String(b.id)) ?? false;
                  if (aSelected === bSelected) return 0;
                  return aSelected ? -1 : 1;
              })
            : [],
    );

    return {
        // Image handling
        get thumbnailUrl() {
            return thumbnailUrl;
        },

        // Content
        get authorsInlineHtml() {
            return authorsInlineHtml;
        },

        // Session/state
        get auth() {
            return auth;
        },
        get isIgnored() {
            return isIgnored;
        },
        get supportedPlatforms() {
            return supportedPlatforms;
        },
        get storePlatform() {
            return storePlatform;
        },

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
        handleLostToggle,

        // Tags
        get orderedTags() {
            return orderedTags;
        },
    };
}
