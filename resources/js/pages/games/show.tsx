import CharacterStatsModal from '@/components/character-stats-modal';
import FileStatsModal from '@/components/file-stats-modal';
import GameStats from '@/components/game-stats';
import GameVersionComparisonModal from '@/components/game-version-comparison-modal';
import LoadingSpinner from '@/components/loading-spinner';
import AdvancedPagination from '@/components/advanced-pagination';
import EditableGameContent from '@/components/editor/EditableGameContent';
import GameCardUserSection from '@/components/game-card-user-section';
import ScreenshotsGallery from '@/components/games/ScreenshotsGallery';
import ScreenshotsLightbox from '@/components/games/ScreenshotsLightbox';

import DownloadsList from '@/components/games/DownloadsList';
import ReviewTextControls, {useReviewTextStyles} from '@/components/review-text-controls';
import {Link, usePage} from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ReactDOM from 'react-dom';
import { type OptimizedScreenshotVariants } from '@/constants/screenshot-variants';
import SeoHead, {type MetaTags, createGameMetaTags} from '@/components/seo/SeoHead';
import {formatLocalDate} from '@/utils/date-formatting';

// Use global axios from window (configured with CSRF)
import type {AxiosInstance} from 'axios';

declare global {
    interface Window {
        axios: AxiosInstance;
    }
}


interface Tag {
    id: number;
    name: string;
    slug: string;
    created_at: string;
    updated_at: string;
    pivot: {
        game_id: number;
        tag_id: number;
        created_at: string;
        updated_at: string;
    };
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
    pivot: {
        game_id: number;
        game_jam_id: number;
        ranking?: number;
        criteria_rankings?: Record<string, unknown>;
        created_at: string;
        updated_at: string;
    };
}

interface Language {
    id: number;
    iso_code: string;
    ref_name: string;
    flag_code: string;
}

interface SupportedLanguage {
    iso_code: string;
    language: Language;
    is_available: boolean;
}

interface LanguageStats {
    words?: number;
    language: Language;
}

interface GameVersion {
    id: number;
    version: string;
    published_at: string;
    devlog?: string;
    is_windows?: boolean;
    is_linux?: boolean;
    is_mac?: boolean;
    is_android?: boolean;
    is_web?: boolean;
    supportedLanguages?: SupportedLanguage[];
    languageStats?: LanguageStats[];
}

interface Screenshot {
    url: string;
    thumbnail_url: string;
    optimized?: OptimizedScreenshotVariants;
}

interface AdditionalLink {
    id: number | string; // keeps compatibility with DownloadsList prop type
    name: string;
    url: string;
    platform?: string;
    last_edited_at?: string;
}

interface Rater {
    id: number;
    name: string;
}

interface Review {
    id: number;
    rating: number;
    review?: string;
    published_at: string;
    is_visible: boolean;
    is_reviewed: boolean;
    event_id?: string;
    rater: Rater;
}

interface Game {
    id: number;
    name: string;
    slug: string;
    description?: string;
    full_description?: string;
    custom_description?: string;
    has_custom_page?: boolean;
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
    current_price?: number;
    original_price?: number;
    is_on_sale?: boolean;
    sale_discount_percent?: number;
    discount_percentage?: number;
    url?: string;
    custom_css?: string;
    custom_tags?: string;
    is_visible?: boolean;
    is_suspended?: boolean;
    blur_screenshots?: boolean;
    hasAdditionalLinks?: () => boolean;
    created_at: string;
    updated_at: string;
    tags?: Tag[];
    game_jams?: GameJam[];
    latest_version?: GameVersion;
    additional_links?: AdditionalLink[];
    screenshots?: Screenshot[];
}

interface PaginationMeta {
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
    // some Inertia/Laravel responses may put pagination directly on root
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

interface GameShowProps {
    game: Game;
    reviews?: Paginated<Review>;
    gameVersions?: Paginated<GameVersion>;
    supportedLanguages?: SupportedLanguage[];
    englishStats?: LanguageStats;
    versionCharacterCounts?: Record<number, number>;
    availableRatings?: number[];
    platforms?: {
        windows: boolean;
        linux: boolean;
        mac: boolean;
        android: boolean;
        web: boolean;
    };
    canSeeAnalytics?: boolean;
    clickStats?: ClickStats;
    dailyStats?: DailyStats[];
    editPermissions?: EditPermissions;
    metaTags?: MetaTags;
}

export default function GameShow({
                                     game,
                                     reviews,
                                     gameVersions,
                                     supportedLanguages,
                                     englishStats,
                                     versionCharacterCounts = {},
                                     availableRatings = [],
                                     platforms = {
                                         windows: false,
                                         linux: false,
                                         mac: false,
                                         android: false,
                                         web: false,
                                     },
                                     canSeeAnalytics = false,
                                     clickStats,
                                     dailyStats,
                                     editPermissions = {
                                         canEdit: false,
                                         hasCustomPage: false,
                                         isOwner: false,
                                         isAdmin: false,
                                     },
                                     metaTags,
                                 }: GameShowProps) {
    // SSR-safe auth detection via Inertia props
    const {auth} = (usePage().props as { auth?: { user?: unknown } }) ?? {};
    const isAuthenticated = Boolean(auth?.user);
    // Helper function to format bytes
    const formatBytes = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    };

    const [showAllRatings, setShowAllRatings] = useState(false);
    const [selectedRating, setSelectedRating] = useState<number | null>(null);
    const [compareFromVersionId, setCompareFromVersionId] = useState<
        number | null
    >(null);
    const [compareToVersionId, setCompareToVersionId] = useState<number | null>(
        null,
    );
    const [showCharacterStats, setShowCharacterStats] = useState<number | null>(
        null,
    );
    const [showFileStats, setShowFileStats] = useState<number | null>(null);
    const [showVersionComparison, setShowVersionComparison] = useState(false);
    const [versionComparisonData, setVersionComparisonData] = useState<{
        fromVersion: { version: string; published_at: string };
        toVersion: { version: string; published_at: string };
        languages: { id: string; flag: string; name: string }[];
        characters: string[];
        characterDiffs: Record<
            string,
            Record<string, { from: number; to: number; diff: number }>
        >;
        languageTotals: {
            from: Record<string, number>;
            to: Record<string, number>;
            diff: Record<string, number>;
        };
        fileCategories?: Array<{
            category: string;
            from: { count: number; size: number };
            to: { count: number; size: number };
            diff: { count: number; size: number };
            fileTypes?: Record<
                string,
                {
                    from: { count: number; size: number };
                    to: { count: number; size: number };
                    diff: { count: number; size: number };
                }
            >;
        }>;
    } | null>(null);
    const [isLoadingComparison, setIsLoadingComparison] = useState(false);
    const [activeComparisonTab, setActiveComparisonTab] = useState<
        'character' | 'file'
    >('character');
    const [characterStatsData, setCharacterStatsData] = useState<{
        languages: { id: string; flag: string; name: string; count: number }[];
        characters: string[];
        totalCharacters: number;
    } | null>(null);
    const [fileStatsData, setFileStatsData] = useState<{
        version?: { version?: string };
        file_categories?: {
            category: string;
            total_count: number;
            total_size: number;
            file_types: { extension: string; count: number; size: number }[];
        }[];
    } | null>(null);
    const [statsLoading, setStatsLoading] = useState(false);
    const [characterStatsLoading, setCharacterStatsLoading] = useState<
        number | null
    >(null);
    const [fileStatsLoading, setFileStatsLoading] = useState<number | null>(
        null,
    );
    const [isLightboxOpen, setIsLightboxOpen] = useState<boolean>(false);
    const [lightboxIndex, setLightboxIndex] = useState<number>(0);

    // Use the review text styles hook
    const reviewStyles = useReviewTextStyles();

    // Media editing state
    const [currentThumbnail, setCurrentThumbnail] = useState<string | null>(game.optimized_thumbnail_url || game.thumb_url || null);
    const [currentScreenshots, setCurrentScreenshots] = useState<Screenshot[]>(
        (game as any).custom_screenshots || game.screenshots || []
    );

    // Handle media updates
    const handleMediaUpdate = (newThumbnail: string | null, newScreenshots: Screenshot[]) => {
        setCurrentThumbnail(newThumbnail);
        setCurrentScreenshots(newScreenshots);
    };

    // Lightbox open/close (navigation is handled inside the Lightbox component)
    const openLightbox = (index: number) => {
        if (currentScreenshots && currentScreenshots[index]) {
            setLightboxIndex(index);
            setIsLightboxOpen(true);
        }
    };

    const closeLightbox = () => {
        setIsLightboxOpen(false);
    };



    // Lightbox keyboard and swipe navigation handled within Lightbox component

    const [currentReviews, setCurrentReviews] = useState(reviews?.data || []);
    const [currentAvailableRatings, setCurrentAvailableRatings] = useState(
        availableRatings || [],
    );
    const [reviewsLoading, setReviewsLoading] = useState(false);
    const [reviewsPagination, setReviewsPagination] = useState({
        current_page: reviews?.current_page ?? reviews?.meta?.current_page ?? 1,
        last_page: reviews?.last_page ?? reviews?.meta?.last_page ?? 1,
        per_page: reviews?.per_page ?? reviews?.meta?.per_page ?? 5,
        total:
            reviews?.total ??
            reviews?.meta?.total ??
            (reviews?.data ? reviews.data.length : 0),
        from:
            reviews?.from ??
            reviews?.meta?.from ??
            (reviews?.data && reviews.data.length > 0 ? 1 : 0),
        to:
            reviews?.to ??
            reviews?.meta?.to ??
            (reviews?.data ? reviews.data.length : 0),
    });

    // Versions state management
    const [currentVersions, setCurrentVersions] = useState(gameVersions?.data || []);
    const [versionsLoading, setVersionsLoading] = useState(false);
    const [versionsPagination, setVersionsPagination] = useState({
        current_page:
            gameVersions?.current_page ?? gameVersions?.meta?.current_page ?? 1,
        last_page:
            gameVersions?.last_page ?? gameVersions?.meta?.last_page ?? 1,
        per_page: gameVersions?.per_page ?? gameVersions?.meta?.per_page ?? 5,
        total:
            gameVersions?.total ??
            gameVersions?.meta?.total ??
            (gameVersions?.data ? gameVersions.data.length : 0),
        from:
            gameVersions?.from ??
            gameVersions?.meta?.from ??
            (gameVersions?.data && gameVersions.data.length > 0 ? 1 : 0),
        to:
            gameVersions?.to ??
            gameVersions?.meta?.to ??
            (gameVersions?.data ? gameVersions.data.length : 0),
    });

    // Get platform information from latest version or props
    const getPlatforms = () => {
        if (platforms && Object.values(platforms).some(Boolean)) {
            return platforms;
        }

        if (game.latest_version) {
            return {
                windows: game.latest_version.is_windows ?? false,
                linux: game.latest_version.is_linux ?? false,
                mac: game.latest_version.is_mac ?? false,
                android: game.latest_version.is_android ?? false,
                web: game.latest_version.is_web ?? false,
            };
        }

        return platforms;
    };

    const activePlatforms = getPlatforms();
    const platformNames = [];
    if (activePlatforms.windows) platformNames.push('Windows');
    if (activePlatforms.linux) platformNames.push('Linux');
    if (activePlatforms.mac) platformNames.push('macOS');
    if (activePlatforms.android) platformNames.push('Android');
    if (activePlatforms.web) platformNames.push('Web');

    // Fetch reviews from API
    const fetchReviews = async (
        showAll: boolean = showAllRatings,
        rating: number | null = selectedRating,
        page: number = 1,
        perPage: number = reviewsPagination.per_page,
    ) => {
        setReviewsLoading(true);
        try {
            const url = route('react-api.games.reviews', {game: game.id});
            const params = {
                showAllRatings: showAll,
                selectedRating: rating,
                perPage: perPage,
                page: page,
            };

            const response = await window.axios.get(url, {params});

            if (response.data.success) {
                setCurrentReviews(response.data.reviews.data);
                setCurrentAvailableRatings(response.data.availableRatings);

                // Laravel pagination structure - metadata is directly on the paginated object
                setReviewsPagination({
                    current_page: response.data.reviews.current_page,
                    last_page: response.data.reviews.last_page,
                    per_page: response.data.reviews.per_page,
                    total: response.data.reviews.total,
                    from: response.data.reviews.from,
                    to: response.data.reviews.to,
                });
            } else {
                console.error('API returned success: false', response.data);
            }
        } catch (error) {
            console.error('Error fetching reviews:', error);
        } finally {
            setReviewsLoading(false);
        }
    };

    // Fetch versions from API
    const fetchVersions = async (page: number = 1, perPage: number = 5) => {
        setVersionsLoading(true);
        try {
            const url = route('react-api.games.versions', {game: game.id});
            const params = {
                page: page,
                perPage: perPage,
            };

            const response = await window.axios.get(url, {params});

            if (response.data.success) {
                setCurrentVersions(response.data.versions.data);

                setVersionsPagination({
                    current_page: response.data.versions.current_page,
                    last_page: response.data.versions.last_page,
                    per_page: response.data.versions.per_page,
                    total: response.data.versions.total,
                    from: response.data.versions.from,
                    to: response.data.versions.to,
                });
            } else {
                console.error('API returned success: false', response.data);
            }
        } catch (error) {
            console.error('Error fetching versions:', error);
        } finally {
            setVersionsLoading(false);
        }
    };

    const handleReviewsPerPageChange = async (perPage: number) => {
        // Update the per_page value in reviewsPagination state
        setReviewsPagination(prev => ({
            ...prev,
            per_page: perPage
        }));
        // Fetch reviews with the new perPage value, reset to page 1
        await fetchReviews(showAllRatings, selectedRating, 1, perPage);
    };

    // Use current reviews directly (filtering is done on the server)
    const filteredReviews = currentReviews;

    const handleToggleRatingsView = async () => {
        const newShowAllRatings = !showAllRatings;
        setShowAllRatings(newShowAllRatings);
        setSelectedRating(null);
        await fetchReviews(newShowAllRatings, null, 1); // Reset to page 1
    };

    const handleRatingFilterChange = async (rating: number | null) => {
        setSelectedRating(rating);
        await fetchReviews(showAllRatings, rating, 1); // Reset to page 1
    };

    const handlePageChange = async (page: number) => {
        await fetchReviews(showAllRatings, selectedRating, page);
    };

    const handleVersionsPageChange = async (page: number) => {
        await fetchVersions(page, versionsPagination.per_page);
    };

    const handleVersionsPerPageChange = async (perPage: number) => {
        // Update the per_page value in versionsPagination state
        setVersionsPagination(prev => ({
            ...prev,
            per_page: perPage
        }));
        // Fetch versions with the new perPage value, reset to page 1
        await fetchVersions(1, perPage);
    };

    // Fetch character stats for a version
    const fetchCharacterStats = async (versionId: number) => {
        setCharacterStatsLoading(versionId);
        setStatsLoading(true);
        try {
            const response = await window.axios.get(
                route('react-api.games.version.character-stats', {
                    game: game.slug,
                    version: versionId,
                }),
            );
            if (response.data.success) {
                setCharacterStatsData(response.data.data);
                setShowCharacterStats(versionId);
                // Open the dialog using native showModal()
                const dialog = document.getElementById(
                    `character-stats-${versionId}`,
                ) as HTMLDialogElement;
                if (dialog) {
                    dialog.showModal();
                }
            }
        } catch (error) {
            console.error('Error fetching character stats:', error);
        } finally {
            setCharacterStatsLoading(null);
            setStatsLoading(false);
        }
    };

    // Fetch file stats for a version
    const fetchFileStats = async (versionId: number) => {
        setFileStatsLoading(versionId);
        setStatsLoading(true);
        try {
            const response = await window.axios.get(
                route('react-api.games.version.file-stats', {
                    game: game.slug,
                    version: versionId,
                }),
            );
            if (response.data.success) {
                setFileStatsData(response.data.data);
                setShowFileStats(versionId);
                // Open the dialog using native showModal()
                const dialog = document.getElementById(
                    `file-stats-${versionId}`,
                ) as HTMLDialogElement;
                if (dialog) {
                    dialog.showModal();
                }
            }
        } catch (error) {
            console.error('Error fetching file stats:', error);
        } finally {
            setFileStatsLoading(null);
            setStatsLoading(false);
        }
    };

    // Helper functions to close dialogs
    const closeCharacterStatsDialog = (versionId: number) => {
        const dialog = document.getElementById(
            `character-stats-${versionId}`,
        ) as HTMLDialogElement;
        if (dialog) {
            dialog.close();
        }
        setShowCharacterStats(null);
        setCharacterStatsData(null);
    };

    const closeFileStatsDialog = (versionId: number) => {
        const dialog = document.getElementById(
            `file-stats-${versionId}`,
        ) as HTMLDialogElement;
        if (dialog) {
            dialog.close();
        }
        setShowFileStats(null);
        setFileStatsData(null);
    };

    const compareVersions = async () => {
        if (!compareFromVersionId || !compareToVersionId) {
            return;
        }

        setIsLoadingComparison(true);

        try {
            const response = await window.axios.get(
                route('api.games.compare-versions', game.id),
                {
                    params: {
                        fromVersionId: compareFromVersionId,
                        toVersionId: compareToVersionId,
                    },
                },
            );

            // API returns data directly, not wrapped in success/data structure
            setVersionComparisonData(response.data);
            // Only show the dialog after data is loaded
            setShowVersionComparison(true);
        } catch (error) {
            console.error('Error fetching version comparison:', error);
            setVersionComparisonData(null);
        } finally {
            setIsLoadingComparison(false);
        }
    };

    const closeVersionComparisonDialog = () => {
        const dialog = document.getElementById(
            'version-comparison-dialog',
        ) as HTMLDialogElement;
        if (dialog) {
            dialog.close();
        }
        setShowVersionComparison(false);
        setVersionComparisonData(null);
        // Reset tab to default when closing
        setActiveComparisonTab('character');
    };

    // Effect to open dialog after React renders it
    useEffect(() => {
        if (showVersionComparison) {
            // Use setTimeout to ensure the dialog is rendered before trying to open it
            setTimeout(() => {
                const dialog = document.getElementById(
                    'version-comparison-dialog',
                ) as HTMLDialogElement;
                if (dialog) {
                    dialog.showModal();

                    // Add event listener for native dialog close (Escape key)
                    const handleDialogClose = () => {
                        setShowVersionComparison(false);
                        setVersionComparisonData(null);
                        setActiveComparisonTab('character');
                    };

                    dialog.addEventListener('close', handleDialogClose);

                    // Cleanup function to remove event listener
                    return () => {
                        dialog.removeEventListener('close', handleDialogClose);
                    };
                }
            }, 0);
        }
    }, [showVersionComparison]);

    // Add effect to handle clicking outside dialogs to close them
    useEffect(() => {
        const handleDialogClick = (event: Event) => {
            const dialog = event.target as HTMLDialogElement;
            if (dialog.tagName === 'DIALOG') {
                const rect = dialog.getBoundingClientRect();
                const clickedInDialog =
                    (event as MouseEvent).clientX >= rect.left &&
                    (event as MouseEvent).clientX <= rect.right &&
                    (event as MouseEvent).clientY >= rect.top &&
                    (event as MouseEvent).clientY <= rect.bottom;

                if (!clickedInDialog) {
                    dialog.close();
                    // Clear states when closing
                    if (showCharacterStats !== null) {
                        setShowCharacterStats(null);
                        setCharacterStatsData(null);
                    }
                    if (showFileStats !== null) {
                        setShowFileStats(null);
                        setFileStatsData(null);
                    }
                }
            }
        };

        document.addEventListener('click', handleDialogClick);
        return () => document.removeEventListener('click', handleDialogClick);
    }, [showCharacterStats, showFileStats]);

    const getPlatformIcon = (platform: string) => {
        const platformConfigs = {
            windows: {icon: 'icon-windows', color: 'text-platform-windows'},
            linux: {icon: 'icon-linux', color: 'text-platform-linux'},
            mac: {
                icon: 'icon-apple',
                color: 'text-platform-mac',
            },
            android: {icon: 'icon-android', color: 'text-platform-android'},
            web: {icon: 'icon-web', color: 'text-platform-web'},
        };

        const config =
            platformConfigs[platform as keyof typeof platformConfigs];
        if (!config) {
            return (
                <i
                    className="icon-external-link text-gray-600 dark:text-gray-400"
                    title={platform}
                />
            );
        }

        return (
            <i
                className={`${config.icon} ${config.color}`}
                title={platform.charAt(0).toUpperCase() + platform.slice(1)}
            />
        );
    };

    const getLanguageFlag = (flagCode: string) => {
        return `https://flagicons.lipis.dev/flags/1x1/${flagCode}.svg`;
    };

    function TagsSection({
                             tags,
                             customTags,
                         }: {
        tags: Array<{ id: number; name: string }>;
        customTags: string;
    }) {
        return (
            <div>
                <div className="flex flex-wrap items-center gap-2">
                    {tags.map((tag) => (
                        <Link
                            key={tag.id}
                            href={route('games.index', {
                                selectedTags: [tag.id],
                            })}
                            className="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            {tag.name}
                        </Link>
                    ))}
                </div>
                {customTags && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {customTags.split(',').map((raw, idx) => {
                            const t = raw.trim();
                            return t ? (
                                <span
                                    key={idx}
                                    className="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    {t}
                                </span>
                            ) : null;
                        })}
                    </div>
                )}
            </div>
        );
    }

    // Generate meta tags - merge backend meta tags with frontend-generated structured data
    const frontendMetaTags = createGameMetaTags(game);
    const gameMetaTags = metaTags
        ? { ...metaTags, structuredData: frontendMetaTags.structuredData }
        : frontendMetaTags;

    return (
        <>
            <SeoHead metaTags={gameMetaTags} />
            {/* Custom CSS */}
            {game.custom_css && (
                <style
                    dangerouslySetInnerHTML={{
                        __html: `
            .game_description img {
              display: initial;
            }
            ${game.custom_css}
          `,
                    }}
                />
            )}

            {/* Page content within global Container from AppLayout */}
            {/* Sticky Navigation */}
            <div
                className="sticky top-[4.5rem] z-40 mb-5 px-4 flex items-center justify-between bg-gray-100 py-4 border-b border-gray-200 dark:bg-gray-900 dark:border-gray-700 shadow-sm">
                <Link
                    href={route('games.index')}
                    className="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    <svg
                        className="mr-1 h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    Back to Game List
                </Link>

                {/* Section Navigation */}
                <nav className="flex space-x-4">
                    {canSeeAnalytics && (clickStats || dailyStats) && (
                        <a
                            href="#analytics"
                            className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Analytics
                        </a>
                    )}
                    {game.is_visible && (
                        <a
                            href="#details"
                            className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Details
                        </a>
                    )}
                    {game.screenshots && game.screenshots.length > 0 && (
                        <a
                            href="#screenshots"
                            className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Screenshots
                        </a>
                    )}
                    {game.additional_links &&
                        game.additional_links.length > 0 && (
                            <a
                                href="#downloads"
                                className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                            >
                                Downloads
                            </a>
                        )}
                    {gameVersions && gameVersions.data.length > 0 && (
                        <a
                            href="#versions"
                            className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Versions
                        </a>
                    )}
                    <a
                        href="#reviews"
                        className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        Reviews
                    </a>
                </nav>
            </div>

            {/* Game Header */}
            <div className="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                <div className="flex flex-col gap-6 md:flex-row">
                    {game.is_visible && currentThumbnail && (
                        <div className="shrink-0 relative group">
                            <img
                                src={currentThumbnail}
                                alt={game.name}
                                className="max-h-52 max-w-64 rounded-lg object-cover"
                            />
                            {editPermissions.canEdit && (
                                <label className="absolute top-2 right-2 bg-blue-600 text-white rounded-full p-2 hover:bg-blue-700 transition-colors shadow-lg cursor-pointer">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={async (e) => {
                                            if (e.target.files?.[0]) {
                                                const file = e.target.files[0];
                                                if (!file.type.startsWith('image/')) {
                                                    alert('Please upload an image file');
                                                    return;
                                                }
                                                const formData = new FormData();
                                                formData.append('thumbnail', file);
                                                try {
                                                    const res = await window.axios.post(
                                                        route('react-api.my-games.thumbnail.update', { game: game.slug }),
                                                        formData
                                                    );
                                                    if (res.data?.success) {
                                                        setCurrentThumbnail(res.data.thumbnail_url);
                                                    }
                                                } catch (e) {
                                                    console.error('Failed to upload thumbnail', e);

                                                    // Show error message to user
                                                    const error = e as any;
                                                    if (error.response?.data?.message) {
                                                        alert(error.response.data.message);
                                                    } else if (error.response?.data?.errors?.thumbnail) {
                                                        alert(error.response.data.errors.thumbnail[0]);
                                                    } else {
                                                        alert('Failed to upload thumbnail. Please try again.');
                                                    }
                                                }
                                            }
                                        }}
                                        className="hidden"
                                    />
                                </label>
                            )}
                        </div>
                    )}

                    <div className="flex-1">
                        <div className="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {game.name}
                            </h1>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                {game.url && (
                                    <a
                                        href={route('track.external-project', {
                                            game_id: game.id,
                                            url: game.url,
                                        })}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-blue-600 hover:underline dark:text-blue-400"
                                    >
                                        Visit Game Page
                                    </a>
                                )}
                            </div>
                        </div>

                        {/* Platform Icons and Badges */}
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-4">
                            <div className="flex flex-wrap items-center gap-3">
                                {/* Supported Platforms */}
                                {Object.values(activePlatforms).some(Boolean) && (
                                    <div className="flex items-center gap-2 text-lg">
                                        {activePlatforms.windows && getPlatformIcon('windows')}
                                        {activePlatforms.linux && getPlatformIcon('linux')}
                                        {activePlatforms.mac && getPlatformIcon('mac')}
                                        {activePlatforms.android && getPlatformIcon('android')}
                                        {activePlatforms.web && getPlatformIcon('web')}
                                    </div>
                                )}

                                {/* Pills (NSFW, Suspended, Sale, Paid, Demo) */}
                                {game.is_nsfw && (
                                    <span
                                        className="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">
                                        NSFW
                                    </span>
                                )}
                                {game.is_suspended && (
                                    <span
                                        className="flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        Suspended
                                    </span>
                                )}
                                {game.is_on_sale && (
                                    <span
                                        className="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Sale{typeof game.discount_percentage === 'number' ? ` -${game.discount_percentage}%` : ''}
                                    </span>
                                )}
                                {game.is_paid && (
                                    <span
                                        className="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {game.is_on_sale && game.current_price && game.original_price ? (
                                            <>
                                                <span
                                                    className="line-through text-blue-500 dark:text-blue-400 mr-1">${Number(game.original_price).toFixed(2)}</span>
                                                ${Number(game.current_price).toFixed(2)}
                                            </>
                                        ) : (
                                            game.current_price && Number(game.current_price) > 0
                                                ? `$${Number(game.current_price).toFixed(2)}`
                                                : 'Paid'
                                        )}
                                    </span>
                                )}
                                {game.has_demo && (
                                    <span
                                        className="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Demo
                                    </span>
                                )}
                            </div>

                            {/* Edit controls placeholder - will be filled by EditableGameContent */}
                            <div id="edit-controls-container"></div>
                        </div>

                        {/* Authors */}
                        {game.authors && (
                            <div className="mb-3 text-gray-600 dark:text-gray-300">
                                <div
                                    dangerouslySetInnerHTML={{
                                        __html: game.authors,
                                    }}
                                />
                            </div>
                        )}

                        {/* Description */}
                        <div className="group">
                            <EditableGameContent
                                gameId={game.id}
                                content={(game.has_custom_page && game.custom_description) ? game.custom_description : (game.full_description || game.description || '')}
                                canEdit={editPermissions.canEdit}
                                hasCustomPage={game.has_custom_page || false}
                                renderEditControls={(controls) => {
                                    // Render controls into the edit-controls-container (only on client-side)
                                    if (typeof document === 'undefined') return null;
                                    const container = document.getElementById('edit-controls-container');
                                    if (container) {
                                        return ReactDOM.createPortal(controls, container);
                                    }
                                    return null;
                                }}
                            />
                        </div>
                    </div>
                </div>

                {/* Add to List / Rate Game */}
                <div className="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    {/* Authenticated Add-to-List UI (SSR-safe) */}
                    {isAuthenticated ? (
                        <GameCardUserSection
                            gameId={game.id}
                            gameName={game.name}
                            isPaid={game.is_paid}
                            userProgress={null}
                            userListMemberships={[]}
                        />
                    ) : (
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                <Link
                                    href={route('login')}
                                    className="text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Log in
                                </Link>{' '}
                                to track your reading progress
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Analytics Section */}
            {canSeeAnalytics && (clickStats || dailyStats) && (
                <div
                    id="analytics"
                    className="mb-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800"
                >
                    <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Analytics
                    </h2>
                    <GameStats
                        clickStats={clickStats}
                        dailyStats={dailyStats}
                    />
                </div>
            )}

            {/* Game Details */}
            {game.is_visible && (
                <div
                    id="details"
                    className="mb-6 grid scroll-mt-28 grid-cols-1 gap-6 md:grid-cols-2"
                >
                    {/* Left Column: Basic Info */}
                    <div className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                        <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Game Details
                        </h2>

                        {/* Game Details (matched to legacy) */}
                        <dl className="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            {(
                                [
                                    {
                                        label: 'Status',
                                        value: game.status ? String(game.status) : '-',
                                    },
                                    {
                                        label: 'Engine',
                                        value: game.game_engine
                                            ? String(game.game_engine)
                                            : '-',
                                    },
                                    {
                                        label: 'Initial Release',
                                        value: formatLocalDate(game.initially_published_at) || '-',
                                    },
                                    {
                                        label: 'Latest Update',
                                        value: formatLocalDate(game.latest_version?.published_at) || '-',
                                    },
                                    {
                                        label: 'Current Version',
                                        value: game.latest_version?.version || '-',
                                    },
                                    {
                                        label: 'Word Count (English)',
                                        value:
                                            typeof englishStats?.words === 'number' &&
                                            englishStats.words > 0
                                                ? englishStats.words.toLocaleString()
                                                : '-',
                                    },
                                    {
                                        label: 'Price',
                                        value: (() => {
                                            if (!game.is_paid) return 'Free';
                                            const cp = typeof game.current_price === 'number' ? Number(game.current_price) : undefined;
                                            const op = typeof game.original_price === 'number' ? Number(game.original_price) : undefined;
                                            if (game.is_on_sale && cp && op) {
                                                return `$${cp.toFixed(2)}`;
                                            }
                                            if (cp && cp > 0) return `$${cp.toFixed(2)}`;
                                            return 'Paid';
                                        })(),
                                    },
                                    {
                                        label: 'Rating',
                                        value:
                                            typeof game.rating_score === 'number'
                                                ? game.rating_score.toFixed(1)
                                                : '-',
                                    },
                                    {
                                        label: 'Review Count',
                                        value:
                                            typeof game.rating_count === 'number'
                                                ? game.rating_count.toLocaleString()
                                                : '-',
                                    },
                                ] as Array<{ label: string; value: string }>
                            ).map(({label, value}) => (
                                <div key={label}>
                                    <dt className="text-sm text-gray-500 dark:text-gray-400">
                                        {label}
                                    </dt>
                                    <dd className="text-gray-900 dark:text-gray-100">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        {/* Supported Languages (flags only, compact) */}
                        {supportedLanguages &&
                            supportedLanguages.length > 0 && (
                                <div className="mt-4">
                                    <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Supported Languages
                                    </h3>
                                    <div
                                        className="flex flex-wrap gap-1"
                                        aria-label="Languages"
                                    >
                                        {supportedLanguages
                                            .filter((sl) => sl.is_available)
                                            .sort((a, b) =>
                                                a.language.ref_name.localeCompare(
                                                    b.language.ref_name,
                                                ),
                                            )
                                            .map((sl) => (
                                                <img
                                                    key={sl.iso_code}
                                                    src={getLanguageFlag(
                                                        sl.language.flag_code,
                                                    )}
                                                    alt={sl.language.ref_name}
                                                    title={sl.language.ref_name}
                                                    className="h-4 w-4 rounded-sm"
                                                />
                                            ))}
                                    </div>
                                </div>
                            )}
                    </div>

                    {/* Right Column: Tags */}
                    <div className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                        <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Tags
                        </h2>
                        <div className="overflow-hidden">
                            <TagsSection
                                tags={game.tags || []}
                                customTags={game.custom_tags || ''}
                            />
                        </div>
                    </div>

                    {/* Game Jams */}
                    {game.game_jams && game.game_jams.length > 0 && (
                        <div className="rounded-lg bg-white p-6 shadow-sm md:col-span-2 dark:bg-gray-800">
                            <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                Game Jams
                            </h2>
                            <div className="space-y-4">
                                {game.game_jams.map((jam) => (
                                    <div
                                        key={jam.id}
                                        className="border-b border-gray-200 pb-3 last:border-0 last:pb-0 dark:border-gray-700"
                                    >
                                        <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                            {jam.url ? (
                                                <a
                                                    href={jam.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="hover:text-blue-600 dark:hover:text-blue-400"
                                                >
                                                    <span
                                                        className="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-sm text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                                                        {jam.name}
                                                    </span>
                                                </a>
                                            ) : (
                                                <span
                                                    className="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-sm text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                                                    {jam.name}
                                                </span>
                                            )}
                                        </h3>
                                        {jam.start_date && jam.end_date && (
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                {formatLocalDate(jam.start_date)} - {formatLocalDate(jam.end_date)}
                                            </p>
                                        )}
                                        {jam.theme && (
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                <span className="font-medium">
                                                    Theme:
                                                </span>{' '}
                                                {jam.theme}
                                            </p>
                                        )}
                                        {jam.submission_count && (
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                <span className="font-medium">
                                                    Submissions:
                                                </span>{' '}
                                                {jam.submission_count.toLocaleString()}
                                                {jam.participant_count && (
                                                    <span className="ml-1 text-gray-500 dark:text-gray-500">
                                                        (
                                                        {jam.participant_count.toLocaleString()}{' '}
                                                        participants)
                                                    </span>
                                                )}
                                            </p>
                                        )}
                                        {jam.pivot?.ranking && (
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                <span className="font-medium">
                                                    Game Rank:
                                                </span>
                                                <span
                                                    className="ml-1 rounded-full bg-blue-200 px-1.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                                                    {jam.pivot.ranking}
                                                </span>
                                            </p>
                                        )}
                                        {jam.pivot?.criteria_rankings && (
                                            <div className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                <span className="font-medium">
                                                    Criteria Rankings:
                                                </span>
                                                {(() => {
                                                    type CriteriaDetails = {
                                                        rank?: string;
                                                        score?: string;
                                                    };
                                                    const parsed: Record<
                                                        string,
                                                        CriteriaDetails
                                                    > =
                                                        typeof jam.pivot!
                                                            .criteria_rankings ===
                                                        'string'
                                                            ? (JSON.parse(
                                                                jam.pivot!
                                                                    .criteria_rankings as unknown as string,
                                                            ) as Record<
                                                                string,
                                                                CriteriaDetails
                                                            >)
                                                            : (jam.pivot!
                                                            .criteria_rankings as unknown as Record<
                                                            string,
                                                            CriteriaDetails
                                                        >) || {};
                                                    return (
                                                        <ul className="mt-1 ml-4 list-disc space-y-1">
                                                            {Object.entries(
                                                                parsed,
                                                            ).map(
                                                                ([
                                                                     criteria,
                                                                     details,
                                                                 ]) => (
                                                                    <li
                                                                        key={
                                                                            criteria
                                                                        }
                                                                    >
                                                                        <span className="font-medium">
                                                                            {
                                                                                criteria
                                                                            }
                                                                            :
                                                                        </span>
                                                                        {details?.rank ? (
                                                                            <>
                                                                                {
                                                                                    details.rank
                                                                                }
                                                                                {details.score && (
                                                                                    <span
                                                                                        className="ml-1 rounded bg-blue-100 px-1 py-0.5 text-xs text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                                                                        (Score:{' '}
                                                                                        {
                                                                                            details.score
                                                                                        }
                                                                                        )
                                                                                    </span>
                                                                                )}
                                                                            </>
                                                                        ) : null}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    );
                                                })()}
                                            </div>
                                        )}
                                        {jam.host && (
                                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                                Hosted by {jam.host}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Screenshots Gallery */}
            {((currentScreenshots && currentScreenshots.length > 0) || editPermissions.canEdit) && (
                <ScreenshotsGallery
                    screenshots={currentScreenshots}
                    blur={!!game.blur_screenshots}
                    onOpenLightbox={openLightbox}
                    canEdit={editPermissions.canEdit}
                    gameSlug={game.slug}
                    onUpdate={handleMediaUpdate}
                />
            )}

            {/* Downloads */}
            {game.additional_links && game.additional_links.length > 0 && (
                <DownloadsList
                    gameId={game.id}
                    links={game.additional_links}
                    getPlatformIcon={getPlatformIcon}
                />
            )}


            {/* Version History */}
            {gameVersions && gameVersions.data.length > 0 && (
                <div
                    id="versions"
                    className="mb-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800"
                >
                    <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Version History
                    </h2>

                    {/* Browse Dialogue Link */}
                    {game.latest_version &&
                        versionCharacterCounts[game.latest_version.id] > 0 && (
                            <div className="mb-4">
                                <a
                                    href={route('dialogue.browser', {
                                        gameId: game.id,
                                        versionId: game.latest_version.id,
                                    })}
                                    className="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-blue-500 focus:border-blue-700 focus:ring focus:ring-blue-300 focus:outline-none active:bg-blue-700 disabled:opacity-25"
                                >
                                    <svg
                                        className="mr-1 h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                                        />
                                    </svg>
                                    Browse Dialogue
                                </a>
                            </div>
                        )}

                    {/* Version Comparison */}
                    <div className="my-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <h3 className="mb-3 text-base font-medium text-gray-900 dark:text-gray-100">
                            Compare Versions
                        </h3>
                        <div className="flex flex-col items-end gap-4 sm:flex-row">
                            <div>
                                <label
                                    htmlFor="compareFromVersionId"
                                    className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400"
                                >
                                    From Version
                                </label>
                                <select
                                    id="compareFromVersionId"
                                    value={compareFromVersionId || ''}
                                    onChange={(e) =>
                                        setCompareFromVersionId(
                                            e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        )
                                    }
                                    className="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 text-gray-900 sm:w-auto dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <option value="">Select version...</option>
                                    {currentVersions.map(
                                        (version) =>
                                            versionCharacterCounts[version.id] >
                                            0 && (
                                                <option
                                                    key={version.id}
                                                    value={version.id}
                                                >
                                                    {version.version} (
                                                    {new Date(
                                                        version.published_at,
                                                    ).toLocaleDateString(
                                                        'en-US',
                                                        {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            year: 'numeric',
                                                        },
                                                    )}
                                                    )
                                                </option>
                                            ),
                                    )}
                                </select>
                            </div>
                            <div>
                                <label
                                    htmlFor="compareToVersionId"
                                    className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400"
                                >
                                    To Version
                                </label>
                                <select
                                    id="compareToVersionId"
                                    value={compareToVersionId || ''}
                                    onChange={(e) =>
                                        setCompareToVersionId(
                                            e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        )
                                    }
                                    className="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 text-gray-900 sm:w-auto dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <option value="">Select version...</option>
                                    {currentVersions.map(
                                        (version) =>
                                            versionCharacterCounts[version.id] >
                                            0 && (
                                                <option
                                                    key={version.id}
                                                    value={version.id}
                                                >
                                                    {version.version} (
                                                    {new Date(
                                                        version.published_at,
                                                    ).toLocaleDateString(
                                                        'en-US',
                                                        {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            year: 'numeric',
                                                        },
                                                    )}
                                                    )
                                                </option>
                                            ),
                                    )}
                                </select>
                            </div>
                            <div>
                                <button
                                    type="button"
                                    onClick={compareVersions}
                                    disabled={
                                        !compareFromVersionId ||
                                        !compareToVersionId ||
                                        isLoadingComparison
                                    }
                                    className="inline-flex items-center gap-2 rounded-md border border-transparent bg-blue-600 px-4 py-3 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-blue-500 focus:border-blue-700 focus:ring focus:ring-blue-300 focus:outline-none active:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-25 cursor-pointer"
                                >
                                    {isLoadingComparison ? (
                                        <>
                                            <LoadingSpinner
                                                size="sm"
                                                className="border-white border-t-blue-200"
                                            />
                                            Comparing...
                                        </>
                                    ) : (
                                        'COMPARE'
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Version List */}
                    <div className="space-y-3">
                        {currentVersions.map((version) => (
                            <div
                                key={version.id}
                                className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div className="flex flex-col gap-4 sm:flex-row">
                                    <div
                                        className="flex flex-1 flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                                        <div className="flex w-full items-center">
                                            <div className="font-medium text-gray-900 dark:text-gray-100">
                                                {new Date(
                                                    version.published_at,
                                                ).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                })}
                                            </div>
                                        </div>

                                        <div className="flex w-full items-center">
                                            <div className="font-medium text-gray-900 dark:text-gray-100">
                                                Version {version.version}
                                            </div>
                                        </div>

                                        {/* Language Flags */}
                                        <div className="flex w-full items-center">
                                            <div className="flex flex-wrap gap-1">
                                                {version.supportedLanguages
                                                    ?.filter(
                                                        (sl) => sl.is_available,
                                                    )
                                                    .sort((a, b) =>
                                                        a.language.ref_name.localeCompare(
                                                            b.language.ref_name,
                                                        ),
                                                    )
                                                    .map((sl) => (
                                                        <img
                                                            key={sl.iso_code}
                                                            src={getLanguageFlag(
                                                                sl.language
                                                                    .flag_code,
                                                            )}
                                                            alt={
                                                                sl.language
                                                                    .ref_name
                                                            }
                                                            title={
                                                                sl.language
                                                                    .ref_name
                                                            }
                                                            className="h-4 w-4 rounded-sm"
                                                        />
                                                    ))}
                                            </div>
                                        </div>

                                        {/* Platforms */}
                                        <div className="flex w-full items-center">
                                            <div className="flex gap-2 text-lg">
                                                {version.is_windows &&
                                                    getPlatformIcon('windows')}
                                                {version.is_linux &&
                                                    getPlatformIcon('linux')}
                                                {version.is_mac &&
                                                    getPlatformIcon('mac')}
                                                {version.is_android &&
                                                    getPlatformIcon('android')}
                                                {version.is_web &&
                                                    getPlatformIcon('web')}
                                            </div>
                                        </div>

                                        {/* Word count */}
                                        <div className="flex w-full items-center text-sm whitespace-nowrap sm:w-auto">
                                            <span className="text-gray-500">
                                                Words:
                                            </span>
                                            <span className="ml-1 text-gray-900 dark:text-gray-100">
                                                {version.languageStats
                                                        ?.find(
                                                            (ls) =>
                                                                String(
                                                                    ls.language
                                                                        .iso_code,
                                                                ) === 'eng' ||
                                                                String(
                                                                    ls.language.id,
                                                                ) === 'eng',
                                                        )
                                                        ?.words?.toLocaleString() ||
                                                    '-'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div className="mt-2 flex gap-2">
                                    {versionCharacterCounts[version.id] > 0 && (
                                        <button
                                            onClick={() =>
                                                fetchCharacterStats(version.id)
                                            }
                                            disabled={
                                                characterStatsLoading ===
                                                version.id ||
                                                fileStatsLoading === version.id
                                            }
                                            className="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400"
                                        >
                                            {characterStatsLoading ===
                                            version.id ? (
                                                <>
                                                    <LoadingSpinner size="sm"/>
                                                    Loading...
                                                </>
                                            ) : (
                                                `View ${versionCharacterCounts[version.id]} Characters`
                                            )}
                                        </button>
                                    )}
                                    <button
                                        onClick={() =>
                                            fetchFileStats(version.id)
                                        }
                                        disabled={
                                            characterStatsLoading ===
                                            version.id ||
                                            fileStatsLoading === version.id
                                        }
                                        className="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400"
                                    >
                                        {fileStatsLoading === version.id ? (
                                            <>
                                                <LoadingSpinner size="sm"/>
                                                Loading...
                                            </>
                                        ) : (
                                            'View File Stats'
                                        )}
                                    </button>
                                </div>

                                {/* Character Stats Dialog */}
                                <CharacterStatsModal
                                    versionId={version.id}
                                    showCharacterStats={showCharacterStats}
                                    characterStatsData={characterStatsData}
                                    statsLoading={statsLoading}
                                    closeCharacterStatsDialog={
                                        closeCharacterStatsDialog
                                    }
                                    getLanguageFlag={getLanguageFlag}
                                />

                                {/* File Stats Dialog */}
                                <FileStatsModal
                                    versionId={version.id}
                                    showFileStats={showFileStats}
                                    fileStatsData={fileStatsData}
                                    statsLoading={statsLoading}
                                    closeFileStatsDialog={closeFileStatsDialog}
                                />
                            </div>
                        ))}
                    </div>

                    <AdvancedPagination
                        meta={versionsPagination}
                        isLoading={versionsLoading}
                        label="versions"
                        onPageChange={handleVersionsPageChange}
                        onPerPageChange={handleVersionsPerPageChange}
                        perPageOptions={[5, 10, 25, 50]}
                    />
                </div>
            )}

            {/* Review Text Controls */}
            <div className="mb-6">
                <ReviewTextControls />
            </div>

            {/* Reviews Section */}
            <div
                id="reviews"
                className="scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800"
            >
                <div className="mb-4 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Reviews
                        </h2>
                        {currentAvailableRatings.length > 0 && (
                            <select
                                value={selectedRating || ''}
                                onChange={(e) =>
                                    handleRatingFilterChange(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    )
                                }
                                className="rounded border border-gray-200 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                disabled={reviewsLoading}
                            >
                                <option value="">Any Stars</option>
                                {currentAvailableRatings.map((rating) => (
                                    <option key={rating} value={rating}>
                                        {rating} Stars
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                    <button
                        onClick={handleToggleRatingsView}
                        disabled={reviewsLoading}
                        className="text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 dark:text-blue-400"
                    >
                        {reviewsLoading
                            ? 'Loading...'
                            : `Show ${showAllRatings ? 'reviews only' : 'all ratings'}`}
                    </button>
                </div>

                {reviewsLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
                        <span className="ml-2 text-gray-600 dark:text-gray-400">
                            Loading reviews...
                        </span>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {filteredReviews.length === 0 ? (
                            <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                No {showAllRatings ? 'ratings' : 'reviews'}{' '}
                                found
                                {selectedRating
                                    ? ` with ${selectedRating} star${selectedRating !== 1 ? 's' : ''}`
                                    : ''}
                                .
                            </div>
                        ) : (
                            filteredReviews.map((review) => (
                                <div
                                    key={review.id}
                                    className="border-b border-gray-200 pb-6 last:border-0 dark:border-gray-700"
                                >
                                    <div className="mb-2 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-gray-900 dark:text-gray-100">
                                                <Link
                                                    href={`/raters/${review.rater.id}`}
                                                    className="hover:underline"
                                                >
                                                    {review.rater.name}
                                                </Link>
                                            </span>
                                            <span className="text-sm text-gray-500 dark:text-gray-400">
                                                {new Date(
                                                    review.published_at,
                                                ).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                })}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <div className="flex items-center gap-1 text-yellow-400">
                                                {Array.from(
                                                    {length: review.rating},
                                                    (_, i) => (
                                                        <svg
                                                            key={i}
                                                            className="h-5 w-5 fill-current"
                                                            viewBox="0 0 20 20"
                                                        >
                                                            <path
                                                                fillRule="evenodd"
                                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                                            />
                                                        </svg>
                                                    ),
                                                )}
                                            </div>
                                            {review.event_id && (
                                                <a
                                                    href={`https://itch.io/event/${review.event_id}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                                    title="View on itch.io"
                                                >
                                                    <i className="icon-external-link h-4 w-4"></i>
                                                </a>
                                            )}
                                        </div>
                                    </div>

                                    {review.review &&
                                        (!showAllRatings ||
                                            review.is_reviewed) && (
                                            <div
                                                className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300"
                                                style={reviewStyles}>
                                                <div
                                                    dangerouslySetInnerHTML={{
                                                        __html: review.review,
                                                    }}
                                                />
                                            </div>
                                        )}
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* Pagination */}
                <AdvancedPagination
                    meta={reviewsPagination}
                    isLoading={reviewsLoading}
                    label="reviews"
                    onPageChange={handlePageChange}
                    onPerPageChange={handleReviewsPerPageChange}
                    perPageOptions={[5, 10, 25, 50]}
                />
            </div>

            {/* Lightbox */}
            {isLightboxOpen && currentScreenshots && (
                <ScreenshotsLightbox
                    isOpen={isLightboxOpen}
                    screenshots={currentScreenshots}
                    startIndex={lightboxIndex}
                    onClose={closeLightbox}
                />
            )}

            {/* Version Comparison Dialog */}
            <GameVersionComparisonModal
                showVersionComparison={showVersionComparison}
                versionComparisonData={versionComparisonData}
                isLoadingComparison={isLoadingComparison}
                activeComparisonTab={activeComparisonTab}
                setActiveComparisonTab={setActiveComparisonTab}
                closeVersionComparisonDialog={closeVersionComparisonDialog}
                formatBytes={formatBytes}
            />
        </>
    );
}
