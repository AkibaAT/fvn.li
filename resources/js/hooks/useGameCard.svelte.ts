import { page, router } from '@inertiajs/svelte';
import { SvelteURLSearchParams } from 'svelte/reactivity';
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
}

export function useGameCard(props: GameCardProps) {
    const {
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
    } = props;
    const { getSupportedPlatforms } = usePlatformIcons();
    const { getStorePlatformFromString } = useStorePlatformIcons();

    const thumbnailUrl = getGameThumbnail(game) || null;
    const authorsInlineHtml = formatAuthorsInline(game.authors);

    // Session/state shared by every card and row variant. These stay $derived:
    // partial reloads update the props without remounting the cards, and the
    // ignore button's label flips purely off `ignoredGameIds` changing.
    const auth = $derived((page as any).props?.auth as GameCardAuth | undefined);
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

    return {
        // Image handling
        thumbnailUrl,

        // Content
        authorsInlineHtml,

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

        // Tags
        orderedTags,
    };
}
