<script lang="ts">
    import CharacterStatsModal from '@/components/CharacterStatsModal.svelte';
    import FileStatsModal from '@/components/FileStatsModal.svelte';
    import GameStats from '@/components/GameStats.svelte';
    import GameVersionComparisonModal from '@/components/GameVersionComparisonModal.svelte';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import EditableGameContent from '@/components/editor/EditableGameContent.svelte';
    import EditableGameName from '@/components/editor/EditableGameName.svelte';
    import GameCardUserSection from '@/components/GameCardUserSection.svelte';
    import ScreenshotsGallery from '@/components/games/ScreenshotsGallery.svelte';
    import ScreenshotsLightbox from '@/components/games/ScreenshotsLightbox.svelte';
    import PlatformLink from '@/components/game-card/PlatformLink.svelte';
    import PlatformIcon from '@/components/ui/PlatformIcon.svelte';
    import DownloadsList from '@/components/games/DownloadsList.svelte';
    import UserReviewForm from '@/components/games/UserReviewForm.svelte';
    import ReportReviewModal from '@/components/games/ReportReviewModal.svelte';
    import ReviewTextControls, { useReviewTextStyles } from '@/components/ReviewTextControls.svelte';
    import { Link, page } from '@inertiajs/svelte';
    import SeoHead, { createGameMetaTags } from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/components/seo/SeoHead.svelte';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { fetchReviews, fetchVersions, fetchCharacterStats, fetchFileStats, uploadThumbnail, fetchVersionComparison } from '@/hooks/api';

    // Types (abbreviated - same as TSX version)
    interface Tag {
        id: number;
        name: string;
        slug: string;
        created_at: string;
        updated_at: string;
        pivot: any;
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
        pivot: any;
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
        has_route_data?: boolean;
        supportedLanguages?: SupportedLanguage[];
        languageStats?: LanguageStats[];
    }
    interface Screenshot {
        id: number;
        url: string;
        thumbnail_url: string;
        optimized?: Record<string, { path?: string }>;
    }
    interface AdditionalLink {
        id: number | string;
        name: string;
        url: string;
        platform?: string;
        last_edited_at?: string;
    }
    interface Rater {
        id: number;
        name: string;
        external_platform?: string;
    }
    interface ReviewUser {
        id: number;
        name: string;
        avatar?: string;
    }
    interface Review {
        id: number;
        rating: number;
        review?: string;
        published_at: string;
        is_visible: boolean;
        is_reviewed: boolean;
        has_spoilers?: boolean;
        event_id?: string;
        rater: Rater;
        user?: ReviewUser | null;
    }
    interface UserReview {
        id: number;
        rating: number;
        review: string;
        has_spoilers: boolean;
        published_at: string;
        updated_at: string;
    }
    interface Game {
        id: number;
        name: string;
        slug: string;
        effective_name: string;
        description?: string;
        full_description?: string;
        custom_name?: string;
        custom_description?: string;
        effective_description?: string;
        has_custom_page?: boolean;
        view_mode?: 'custom' | 'original';
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
        currency?: string;
        current_price?: number;
        original_price?: number;
        formatted_current_price?: string;
        formatted_original_price?: string;
        is_on_sale?: boolean;
        sale_discount_percent?: number;
        discount_percentage?: number;
        url?: string;
        platform?: 'itch_io' | 'steam' | 'other';
        primary_url?: string | null;
        custom_css?: string;
        custom_tags?: string;
        is_visible?: boolean;
        is_delisted?: boolean;
        created_at: string;
        updated_at: string;
        tags?: Tag[];
        game_jams?: GameJam[];
        latest_version?: GameVersion;
        additional_links?: AdditionalLink[];
        screenshots?: Screenshot[];
        custom_screenshots?: Screenshot[] | null;
        effective_screenshots?: Screenshot[];
        [key: string]: any;
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
        custom_links?: Array<{ link_id: string; link_name: string; total_clicks: number; unique_clicks: number; last_click?: string }>;
    }
    interface EditPermissions {
        canEdit: boolean;
        hasCustomPage: boolean;
        isOwner: boolean;
        isAdmin: boolean;
    }
    interface PublicList {
        id: number;
        name: string;
        description?: string;
        type: string;
        entries_count: number;
        created_at: string;
        user: { id: number; name: string; avatar?: string };
    }
    interface SimilarGame {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string;
        authors?: string;
        platform?: string;
        rating_score?: number;
        rating_count?: number;
        status?: string;
    }
    interface EstimatedReadingTime {
        hours: number;
        minutes: number;
        total_minutes: number;
        word_count: number;
    }

    interface GameShowProps {
        game: Game;
        reviews?: Paginated<Review>;
        gameVersions?: Paginated<GameVersion>;
        supportedLanguages?: SupportedLanguage[];
        englishStats?: LanguageStats;
        primaryStats?: LanguageStats;
        primaryLanguageLabel?: string;
        versionCharacterCounts?: Record<number, number>;
        versionHasFileStats?: Record<number, boolean>;
        versionHasDialogueLines?: Record<number, boolean>;
        versionHasRouteData?: Record<number, boolean>;
        availableRatings?: number[];
        platforms?: { windows: boolean; linux: boolean; mac: boolean; android: boolean; web: boolean };
        canSeeAnalytics?: boolean;
        clickStats?: ClickStats;
        dailyStats?: DailyStats[];
        editPermissions?: EditPermissions;
        userReview?: UserReview | null;
        publicLists?: PublicList[];
        publicListsCount?: number;
        similarGames?: SimilarGame[];
        developerGames?: SimilarGame[];
        estimatedReadingTime?: EstimatedReadingTime | null;
        metaTags?: MetaTags;
    }

    let {
        game,
        reviews,
        gameVersions,
        supportedLanguages,
        englishStats,
        primaryStats,
        primaryLanguageLabel,
        versionCharacterCounts = {},
        versionHasFileStats = {},
        versionHasDialogueLines = {},
        versionHasRouteData = {},
        availableRatings = [],
        platforms = { windows: false, linux: false, mac: false, android: false, web: false },
        canSeeAnalytics = false,
        clickStats,
        dailyStats,
        editPermissions = { canEdit: false, hasCustomPage: false, isOwner: false, isAdmin: false },
        userReview = null,
        publicLists = [],
        publicListsCount = 0,
        similarGames = [],
        developerGames = [],
        estimatedReadingTime = null,
        metaTags,
    }: GameShowProps = $props();

    // Auth from Inertia
    const auth = $derived(($page.props as any).auth);
    const isAuthenticated = $derived(Boolean(auth?.user));

    // Review text styles
    const reviewStylesObj = useReviewTextStyles();
    const reviewStyles = $derived(
        `max-width: ${reviewStylesObj.maxWidth}; font-size: ${reviewStylesObj.fontSize}; line-height: ${reviewStylesObj.lineHeight}; margin: ${reviewStylesObj.margin};`,
    );
    const latestVersionHasDialogue = $derived(
        game.latest_version
            ? (versionCharacterCounts[game.latest_version.id] ?? 0) > 0 && versionHasDialogueLines[game.latest_version.id] === true
            : false,
    );
    const latestVersionHasRouteMap = $derived(game.latest_version ? versionHasRouteData[game.latest_version.id] === true : false);
    const canBrowseLatestDialogue = $derived(Boolean(game.latest_version && !game.is_paid && latestVersionHasDialogue));

    // State
    let showAllRatings = $state(false);
    let selectedRating = $state<number | null>(null);
    let compareFromVersionId = $state<number | null>(null);
    let compareToVersionId = $state<number | null>(null);
    let showCharacterStats = $state<number | null>(null);
    let showFileStats = $state<number | null>(null);
    let showVersionComparison = $state(false);
    let activeComparisonTab = $state<'character' | 'file'>('character');
    let characterStatsLoading = $state<number | null>(null);
    let fileStatsLoading = $state<number | null>(null);
    let isLightboxOpen = $state(false);
    let lightboxIndex = $state(0);
    let reportingReviewId = $state<number | null>(null);
    let reportingReviewerName = $state('');
    let copiedReviewId = $state<number | null>(null);
    let currentThumbnail = $state<string | null>(null);
    let customScreenshots = $state<Screenshot[]>([]);
    let visitorScreenshots = $state<Screenshot[]>([]);
    let visitorName = $state('');
    let visitorDescription = $state('');
    let visitorViewMode = $state<'custom' | 'original'>('original');
    let previewingVisitorView = $state(false);
    let isUploadingThumbnail = $state(false);
    let editControlsContainer = $state<HTMLElement | undefined>(undefined);
    let lastSyncedMediaKey: string | null = null;
    const currentScreenshots = $derived(editPermissions.canEdit && !previewingVisitorView ? customScreenshots : visitorScreenshots);

    function customScreenshotsForEditor(): Screenshot[] {
        return game.custom_screenshots || game.effective_screenshots || game.screenshots || [];
    }

    $effect(() => {
        const nextMediaKey = `${game.id}:${game.custom_page_updated_at ?? game.updated_at ?? ''}`;
        if (lastSyncedMediaKey === nextMediaKey) {
            return;
        }

        lastSyncedMediaKey = nextMediaKey;
        currentThumbnail = game.optimized_thumbnail_url || null;
        customScreenshots = customScreenshotsForEditor();
        visitorScreenshots = game.effective_screenshots || game.screenshots || [];
        visitorName = game.effective_name;
        visitorDescription = game.effective_description || game.full_description || game.description || '';
        visitorViewMode = game.view_mode === 'custom' ? 'custom' : 'original';
    });

    // Async data state
    let characterStatsData = $state<any>(null);
    let fileStatsData = $state<any>(null);
    let versionComparisonData = $state<any>(null);

    // Reviews state
    let reviewsPage = $state(1);
    let reviewsPerPage = $derived(reviews?.per_page ?? reviews?.meta?.per_page ?? 5);
    let reviewsData = $state<any>(null);

    const currentReviews = $derived(reviewsData?.reviews ?? reviews?.data ?? []);
    const currentAvailableRatings = $derived(reviewsData?.availableRatings ?? availableRatings ?? []);
    const reviewsPagination = $derived(
        reviewsData?.pagination ?? {
            current_page: reviews?.current_page ?? reviews?.meta?.current_page ?? 1,
            last_page: reviews?.last_page ?? reviews?.meta?.last_page ?? 1,
            per_page: reviews?.per_page ?? reviews?.meta?.per_page ?? 5,
            total: reviews?.total ?? reviews?.meta?.total ?? (reviews?.data ? reviews.data.length : 0),
            from: reviews?.from ?? reviews?.meta?.from ?? (reviews?.data && reviews.data.length > 0 ? 1 : 0),
            to: reviews?.to ?? reviews?.meta?.to ?? (reviews?.data ? reviews.data.length : 0),
        },
    );

    // Fetch reviews when params change (skip initial if SSR data matches)
    let reviewsInitial = true;
    $effect(() => {
        // Track all reactive deps
        const params = { showAllRatings, selectedRating, page: reviewsPage, perPage: reviewsPerPage };
        if (reviewsInitial) {
            reviewsInitial = false;
            if (params.page === 1 && !params.showAllRatings && params.selectedRating === null) return;
        }
        fetchReviews(game.id, params)
            .then((data) => {
                reviewsData = data;
            })
            .catch(() => {});
    });

    // Versions state
    let versionsPage = $state(1);
    // eslint-disable-next-line svelte/prefer-writable-derived
    let versionsPerPage = $state(5);
    let versionsData = $state<any>(null);
    $effect(() => {
        versionsPerPage = gameVersions?.per_page ?? gameVersions?.meta?.per_page ?? 5;
    });

    const currentVersions = $derived(versionsData?.versions ?? gameVersions?.data ?? []);
    const versionsPagination = $derived(
        versionsData?.pagination ?? {
            current_page: gameVersions?.current_page ?? gameVersions?.meta?.current_page ?? 1,
            last_page: gameVersions?.last_page ?? gameVersions?.meta?.last_page ?? 1,
            per_page: gameVersions?.per_page ?? gameVersions?.meta?.per_page ?? 5,
            total: gameVersions?.total ?? gameVersions?.meta?.total ?? (gameVersions?.data ? gameVersions.data.length : 0),
            from: gameVersions?.from ?? gameVersions?.meta?.from ?? 0,
            to: gameVersions?.to ?? gameVersions?.meta?.to ?? 0,
        },
    );

    // Fetch versions when page changes (skip initial if SSR data matches)
    let versionsInitial = true;
    $effect(() => {
        // Track reactive deps
        const page = versionsPage;
        const perPage = versionsPerPage;
        if (versionsInitial) {
            versionsInitial = false;
            if (page === 1) return;
        }
        fetchVersions(game.id, page, perPage)
            .then((data) => {
                versionsData = data;
            })
            .catch(() => {});
    });

    // Fetch character stats on demand
    $effect(() => {
        if (showCharacterStats === null) return;
        fetchCharacterStats(game.slug, showCharacterStats)
            .then((data) => {
                characterStatsData = data;
            })
            .catch(() => {
                characterStatsData = null;
            });
    });

    // Fetch file stats on demand
    $effect(() => {
        if (showFileStats === null) return;
        fetchFileStats(game.slug, showFileStats)
            .then((data) => {
                fileStatsData = data;
            })
            .catch(() => {
                fileStatsData = null;
            });
    });

    // Fetch version comparison on demand
    $effect(() => {
        if (!showVersionComparison || !compareFromVersionId || !compareToVersionId) return;
        fetchVersionComparison({ gameId: game.id, fromVersionId: compareFromVersionId, toVersionId: compareToVersionId })
            .then((data) => {
                versionComparisonData = data;
            })
            .catch(() => {
                versionComparisonData = null;
            });
    });

    // Platform info
    const getPlatforms = () => {
        if (platforms && Object.values(platforms).some(Boolean)) return platforms;
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
    const activePlatforms = $derived(getPlatforms());

    const detailItems = $derived([
        { label: 'Status', value: game.status ? String(game.status) : '-' },
        { label: 'Engine', value: game.game_engine ? String(game.game_engine) : '-' },
        { label: 'Initial Release', value: formatLocalDate(game.initially_published_at) || '-' },
        { label: 'Latest Update', value: formatLocalDate(game.latest_version?.published_at) || '-' },
        { label: 'Current Version', value: game.latest_version?.version || '-' },
        {
            label: `Word Count (${primaryLanguageLabel || 'EN'})`,
            value:
                typeof primaryStats?.words === 'number' && primaryStats.words > 0
                    ? primaryStats.words.toLocaleString() +
                      (primaryLanguageLabel && primaryLanguageLabel !== 'EN' && typeof englishStats?.words === 'number' && englishStats.words > 0
                          ? ` (EN: ${englishStats.words.toLocaleString()})`
                          : '')
                    : '-',
        },
        {
            label: 'Est. Reading Time',
            value: estimatedReadingTime
                ? estimatedReadingTime.hours > 0
                    ? `~${estimatedReadingTime.hours} hr ${estimatedReadingTime.minutes} min`
                    : `~${estimatedReadingTime.minutes} min`
                : '-',
        },
        {
            label: 'Price',
            value: !game.is_paid ? 'Free' : game.formatted_current_price || 'Paid',
        },
        {
            label: 'Rating',
            value: typeof game.rating_score === 'number' ? game.rating_score.toFixed(1) : '-',
        },
        {
            label: 'Review Count',
            value: typeof game.rating_count === 'number' ? game.rating_count.toLocaleString() : '-',
        },
    ]);

    const visibleSupportedLanguages = $derived(
        (supportedLanguages || []).filter((sl) => sl.is_available).sort((a, b) => a.language.ref_name.localeCompare(b.language.ref_name)),
    );

    let expandedReviews = $state<Record<number, boolean>>({});
    let revealedSpoilers = $state<Record<number, boolean>>({});

    const getLanguageFlag = (flagCode: string) => `https://flagicons.lipis.dev/flags/1x1/${flagCode}.svg`;

    const getVersionWordCount = (version: GameVersion) =>
        version.languageStats?.find((ls) => String(ls.language.iso_code) === 'eng' || String(ls.language.id) === 'eng')?.words?.toLocaleString() ||
        '-';

    const getReviewAuthorHref = (review: Review) => (review.user ? route('users.reviews', review.user.id) : route('raters.show', review.rater.id));

    const shouldCollapseReview = (reviewHtml?: string) => (reviewHtml?.length || 0) > 900;

    const toggleReviewExpanded = (reviewId: number) => {
        expandedReviews = { ...expandedReviews, [reviewId]: !expandedReviews[reviewId] };
    };

    const revealSpoilers = (reviewId: number) => {
        revealedSpoilers = { ...revealedSpoilers, [reviewId]: true };
    };

    const copyReviewLink = async (reviewId: number) => {
        if (typeof window === 'undefined' || !navigator.clipboard) return;
        const url = route('reviews.show', reviewId);
        const fullUrl = url.startsWith('http') ? url : window.location.origin + url;
        await navigator.clipboard.writeText(fullUrl);
        copiedReviewId = reviewId;
        setTimeout(() => {
            copiedReviewId = null;
        }, 2000);
    };

    const getPublicListColors = (type: string) =>
        ({
            reading: {
                border: 'border-blue-500',
                bg: 'bg-blue-100',
                text: 'text-blue-800',
                darkBg: 'dark:bg-blue-900/20',
                darkText: 'dark:text-blue-400',
            },
            completed: {
                border: 'border-green-500',
                bg: 'bg-green-100',
                text: 'text-green-800',
                darkBg: 'dark:bg-green-900/20',
                darkText: 'dark:text-green-400',
            },
            plan_to_read: {
                border: 'border-yellow-500',
                bg: 'bg-yellow-100',
                text: 'text-yellow-800',
                darkBg: 'dark:bg-yellow-900/20',
                darkText: 'dark:text-yellow-400',
            },
            on_hold: {
                border: 'border-orange-500',
                bg: 'bg-orange-100',
                text: 'text-orange-800',
                darkBg: 'dark:bg-orange-900/20',
                darkText: 'dark:text-orange-400',
            },
            dropped: {
                border: 'border-red-500',
                bg: 'bg-red-100',
                text: 'text-red-800',
                darkBg: 'dark:bg-red-900/20',
                darkText: 'dark:text-red-400',
            },
        })[type] || {
            border: 'border-gray-500',
            bg: 'bg-gray-100',
            text: 'text-gray-800',
            darkBg: 'dark:bg-gray-900/20',
            darkText: 'dark:text-gray-400',
        };

    const parseCriteriaRankings = (criteriaRankings: unknown): Record<string, { rank?: string; score?: string }> => {
        if (!criteriaRankings) return {};
        if (typeof criteriaRankings === 'string') {
            try {
                return JSON.parse(criteriaRankings) as Record<string, { rank?: string; score?: string }>;
            } catch {
                return {};
            }
        }
        return criteriaRankings as Record<string, { rank?: string; score?: string }>;
    };

    const filteredReviews = $derived(currentReviews);
    let reviewsLoading = $state(false);
    let versionsLoading = $state(false);

    // Meta tags
    const frontendMetaTags = $derived(createGameMetaTags(game));
    const gameMetaTags = $derived(metaTags || frontendMetaTags);

    // Lightbox
    const openLightbox = (index: number) => {
        if (currentScreenshots?.[index]) {
            lightboxIndex = index;
            isLightboxOpen = true;
        }
    };
    const closeLightbox = () => {
        isLightboxOpen = false;
    };

    // Handlers
    const handleToggleRatingsView = () => {
        showAllRatings = !showAllRatings;
        selectedRating = null;
        reviewsPage = 1;
    };
    const handleRatingFilterChange = (rating: number | null) => {
        selectedRating = rating;
        reviewsPage = 1;
    };
    const handlePageChange = (p: number) => {
        reviewsPage = p;
    };
    const handleReviewsPerPageChange = (pp: number) => {
        reviewsPerPage = pp;
        reviewsPage = 1;
    };
    const handleVersionsPageChange = (p: number) => {
        versionsPage = p;
    };
    const handleVersionsPerPageChange = (pp: number) => {
        versionsPerPage = pp;
        versionsPage = 1;
    };

    const loadCharacterStats = (versionId: number) => {
        characterStatsLoading = versionId;
        showCharacterStats = versionId;
    };
    const loadFileStats = (versionId: number) => {
        fileStatsLoading = versionId;
        showFileStats = versionId;
    };

    const closeCharacterStatsDialog = (versionId: number) => {
        const dialog = document.getElementById(`character-stats-${versionId}`) as HTMLDialogElement;
        if (dialog) dialog.close();
        showCharacterStats = null;
    };
    const closeFileStatsDialog = (versionId: number) => {
        const dialog = document.getElementById(`file-stats-${versionId}`) as HTMLDialogElement;
        if (dialog) dialog.close();
        showFileStats = null;
    };

    const compareVersions = () => {
        if (!compareFromVersionId || !compareToVersionId) return;
        showVersionComparison = true;
    };
    const closeVersionComparisonDialog = () => {
        const dialog = document.getElementById('version-comparison-dialog') as HTMLDialogElement;
        if (dialog) dialog.close();
        showVersionComparison = false;
        activeComparisonTab = 'character';
    };

    const handleMediaUpdate = (newThumbnail: string | null, newScreenshots: any[]) => {
        if (newThumbnail !== null) {
            currentThumbnail = newThumbnail;
        }
        customScreenshots = newScreenshots;
        if (visitorViewMode === 'custom') {
            visitorScreenshots = newScreenshots;
        }
    };

    const handleVisitorViewModeUpdate = (data: {
        view_mode?: 'custom' | 'original';
        effective_name?: string | null;
        effective_description?: string | null;
        effective_screenshots?: unknown[];
    }) => {
        if (data.view_mode) {
            visitorViewMode = data.view_mode;
        }
        if (data.effective_name !== undefined && data.effective_name !== null) {
            visitorName = data.effective_name;
        }
        if (data.effective_description !== undefined && data.effective_description !== null) {
            visitorDescription = data.effective_description;
        }
        if (data.effective_screenshots) {
            visitorScreenshots = data.effective_screenshots as Screenshot[];
        }
    };

    const handleCustomNameUpdate = (newName: string) => {
        if (visitorViewMode === 'custom') {
            visitorName = newName;
        }
    };

    const handleCustomContentUpdate = (newContent: string) => {
        if (visitorViewMode === 'custom') {
            visitorDescription = newContent;
        }
    };

    const handleThumbnailUpload = async (file: File) => {
        if (!file.type.startsWith('image/')) {
            alert('Please upload an image file');
            return;
        }

        isUploadingThumbnail = true;
        try {
            const data = await uploadThumbnail({ gameSlug: game.slug, file });
            currentThumbnail = data.thumbnail_url;
        } catch (error: any) {
            console.error('Failed to upload thumbnail', error);
            if (error?.response?.data?.message) {
                alert(error.response.data.message);
            } else if (error?.response?.data?.errors?.thumbnail) {
                alert(error.response.data.errors.thumbnail[0]);
            } else {
                alert('Failed to upload thumbnail. Please try again.');
            }
        } finally {
            isUploadingThumbnail = false;
        }
    };

    // Format bytes helper
    const formatBytes = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    };

    // Effects for dialog management
    $effect(() => {
        if (showCharacterStats !== null && characterStatsData) {
            characterStatsLoading = null;
            const dialog = document.getElementById(`character-stats-${showCharacterStats}`) as HTMLDialogElement;
            if (dialog && !dialog.open) dialog.showModal();
        }
    });

    $effect(() => {
        if (showFileStats !== null && fileStatsData) {
            fileStatsLoading = null;
            const dialog = document.getElementById(`file-stats-${showFileStats}`) as HTMLDialogElement;
            if (dialog && !dialog.open) dialog.showModal();
        }
    });

    $effect(() => {
        if (showVersionComparison) {
            setTimeout(() => {
                const dialog = document.getElementById('version-comparison-dialog') as HTMLDialogElement;
                if (dialog) dialog.showModal();
            }, 0);
        }
    });

    // Scroll to review anchor on mount
    $effect(() => {
        if (typeof window === 'undefined') return;
        const hash = window.location.hash;
        if (hash?.startsWith('#review-')) {
            setTimeout(() => {
                const el = document.getElementById(hash.slice(1));
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'rounded-lg', 'transition-colors');
                    setTimeout(() => el.classList.remove('bg-blue-50', 'dark:bg-blue-900/20'), 3000);
                }
            }, 500);
        }
    });
</script>

<SeoHead metaTags={gameMetaTags} />

{#if game.custom_css}
    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
    {@html `<style>.game_description img { display: initial; } ${game.custom_css}</style>`}
{/if}

<!-- Sticky Navigation -->
<div
    class="sticky top-[4.5rem] z-40 mb-5 flex items-center justify-between border-b border-gray-200 bg-gray-100 px-4 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
>
    <Link href={route('games.index')} class="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
        <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg
        >
        Back to Game List
    </Link>
    <nav class="flex space-x-4">
        {#if canSeeAnalytics && (clickStats || dailyStats)}
            <a href="#analytics" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">Analytics</a>
        {/if}
        {#if game.is_visible}
            <a href="#details" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">Details</a>
        {/if}
        {#if currentScreenshots && currentScreenshots.length > 0}
            <a href="#screenshots" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">Screenshots</a>
        {/if}
        {#if game.additional_links && game.additional_links.length > 0}
            <a href="#downloads" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">Downloads</a>
        {/if}
        {#if publicLists && publicLists.length > 0}
            <a href="#featured-lists" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">Lists</a>
        {/if}
        {#if gameVersions && gameVersions.data.length > 0}
            <a href="#versions" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">Versions</a>
        {/if}
        <a href="#reviews" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">Reviews</a>
        {#if similarGames && similarGames.length > 0}
            <a href="#similar-games" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">Similar</a>
        {/if}
    </nav>
</div>

<!-- Game Header -->
<div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
    <div class="flex flex-col gap-6 md:flex-row">
        {#if game.is_visible && currentThumbnail}
            <div class="group relative shrink-0">
                <img
                    src={currentThumbnail}
                    alt={game.name}
                    class="max-h-52 max-w-64 rounded-lg {game.platform === 'steam' ? 'object-contain' : 'object-cover'}"
                />
                {#if editPermissions.canEdit}
                    <label
                        class="absolute top-2 right-2 cursor-pointer rounded-full bg-blue-600 p-2 text-white shadow-lg transition-colors hover:bg-blue-700"
                    >
                        {#if isUploadingThumbnail}
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582M20 20v-5h-.581M5.8 9A7 7 0 0118 7.38M18.2 15A7 7 0 016 16.62"
                                />
                            </svg>
                        {:else}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                />
                            </svg>
                        {/if}
                        <input
                            type="file"
                            accept="image/*"
                            class="hidden"
                            onchange={(e) => {
                                const file = (e.target as HTMLInputElement).files?.[0];
                                if (file) {
                                    handleThumbnailUpload(file);
                                }
                            }}
                        />
                    </label>
                {/if}
            </div>
        {/if}

        <div class="flex-1">
            <div class="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div class="group min-w-0 flex-1">
                    {#if editPermissions.canEdit}
                        <EditableGameName {game} {previewingVisitorView} previewName={visitorName} onNameUpdate={handleCustomNameUpdate} />
                    {:else}
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{game.effective_name}</h1>
                    {/if}
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    {#if game.primary_url}
                        <PlatformLink
                            url={game.primary_url}
                            platform={game.platform}
                            gameId={game.id}
                            class="inline-flex items-center gap-2 font-medium text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                        />
                    {/if}
                </div>
            </div>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    {#if Object.values(activePlatforms).some(Boolean)}
                        <div class="flex items-center gap-2 text-lg">
                            {#if activePlatforms.windows}<i class="icon-windows text-platform-windows" title="Windows"></i>{/if}
                            {#if activePlatforms.linux}<i class="icon-linux text-platform-linux" title="Linux"></i>{/if}
                            {#if activePlatforms.mac}<i class="icon-apple text-platform-mac" title="Mac"></i>{/if}
                            {#if activePlatforms.android}<i class="icon-android text-platform-android" title="Android"></i>{/if}
                            {#if activePlatforms.web}<i class="icon-web text-platform-web" title="Web"></i>{/if}
                        </div>
                    {/if}

                    {#if game.is_nsfw}
                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">NSFW</span>
                    {/if}
                    {#if game.is_delisted}
                        <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                            >Delisted</span
                        >
                    {/if}
                    {#if game.is_on_sale}
                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            Sale{typeof game.discount_percentage === 'number' ? ` -${game.discount_percentage}%` : ''}
                        </span>
                    {/if}
                    {#if game.is_paid}
                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {#if game.is_on_sale && game.formatted_current_price && game.formatted_original_price}
                                <span class="mr-1 text-blue-500 line-through dark:text-blue-400">{game.formatted_original_price}</span>
                                {game.formatted_current_price}
                            {:else}
                                {game.formatted_current_price || 'Paid'}
                            {/if}
                        </span>
                    {/if}
                    {#if game.has_demo}
                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200"
                            >Demo</span
                        >
                    {/if}
                </div>
                <div id="edit-controls-container" bind:this={editControlsContainer}></div>
            </div>

            {#if game.authors}
                <div class="mb-3 text-gray-600 dark:text-gray-300">
                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                    <div>{@html game.authors}</div>
                </div>
            {/if}

            <div class="group">
                {#if editPermissions.canEdit}
                    <EditableGameContent
                        {game}
                        controlsTarget={editControlsContainer}
                        {previewingVisitorView}
                        previewContent={visitorDescription}
                        onPreviewingVisitorViewChange={(previewing) => {
                            previewingVisitorView = previewing;
                        }}
                        onViewModeUpdate={handleVisitorViewModeUpdate}
                        onContentUpdate={handleCustomContentUpdate}
                    />
                {:else if game.is_visible && (game.effective_description || game.full_description || game.description)}
                    <div class="game_description prose max-w-none dark:prose-invert">
                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                        {@html game.effective_description || game.full_description || game.description || ''}
                    </div>
                {/if}
            </div>
        </div>
    </div>
    <!-- User Section (outside flex row, inside card) -->
    {#if isAuthenticated}
        <div class="mt-4">
            <GameCardUserSection
                gameId={game.id}
                gameName={game.effective_name}
                isPaid={game.is_paid}
                userProgress={(game as any).user_progress ?? null}
            />
        </div>
    {:else}
        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <Link href={route('login')} class="text-blue-600 hover:underline dark:text-blue-400">Log in</Link>
                    to track your reading progress
                </div>
            </div>
        </div>
    {/if}
</div>

{#if canSeeAnalytics && (clickStats || dailyStats)}
    <div id="analytics" class="mb-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Analytics</h2>
        <GameStats {clickStats} {dailyStats} />
    </div>
{/if}

{#if game.is_visible}
    <div id="details" class="mb-6 grid scroll-mt-28 grid-cols-1 gap-6 md:grid-cols-2">
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
            <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Game Details</h2>
            <dl class="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                {#each detailItems as item (item.label)}
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{item.label}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{item.value}</dd>
                    </div>
                {/each}
            </dl>

            {#if visibleSupportedLanguages.length > 0}
                <div class="mt-4">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Supported Languages</h3>
                    <div class="flex flex-wrap gap-1" aria-label="Languages">
                        {#each visibleSupportedLanguages as sl (sl.iso_code)}
                            <img
                                src={getLanguageFlag(sl.language.flag_code)}
                                alt={sl.language.ref_name}
                                title={sl.language.ref_name}
                                class="h-4 w-4 rounded-sm"
                            />
                        {/each}
                    </div>
                </div>
            {/if}
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
            <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Tags</h2>
            <div class="flex flex-wrap items-center gap-2">
                {#each game.tags || [] as tag (tag.id)}
                    <Link
                        href={route('games.index', { selectedTags: [tag.id], noDefaults: true })}
                        class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        {tag.name}
                    </Link>
                {/each}
            </div>
        </div>

        {#if game.game_jams && game.game_jams.length > 0}
            <div class="rounded-lg bg-white p-6 shadow-sm md:col-span-2 dark:bg-gray-800">
                <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Game Jams</h2>
                <div class="space-y-4">
                    {#each game.game_jams as jam (jam.id)}
                        <div class="border-b border-gray-200 pb-3 last:border-0 last:pb-0 dark:border-gray-700">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">
                                {#if jam.url}
                                    <a href={jam.url} target="_blank" rel="noopener" class="hover:text-blue-600 dark:hover:text-blue-400">
                                        <span
                                            class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-sm text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"
                                        >
                                            {jam.name}
                                        </span>
                                    </a>
                                {:else}
                                    <span
                                        class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-sm text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"
                                    >
                                        {jam.name}
                                    </span>
                                {/if}
                            </h3>
                            {#if jam.start_date && jam.end_date}
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {formatLocalDate(jam.start_date)} - {formatLocalDate(jam.end_date)}
                                </p>
                            {/if}
                            {#if jam.theme}
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400"><span class="font-medium">Theme:</span> {jam.theme}</p>
                            {/if}
                            {#if jam.submission_count}
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Submissions:</span>
                                    {jam.submission_count.toLocaleString()}
                                    {#if jam.participant_count}
                                        <span class="ml-1 text-gray-500 dark:text-gray-500"
                                            >({jam.participant_count.toLocaleString()} participants)</span
                                        >
                                    {/if}
                                </p>
                            {/if}
                            {#if jam.pivot?.ranking}
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Game Rank:</span>
                                    <span
                                        class="ml-1 rounded-full bg-blue-200 px-1.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-800 dark:text-blue-200"
                                    >
                                        {jam.pivot.ranking}
                                    </span>
                                </p>
                            {/if}
                            {#if jam.pivot?.criteria_rankings}
                                {@const parsed = parseCriteriaRankings(jam.pivot.criteria_rankings)}
                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Criteria Rankings:</span>
                                    <ul class="mt-1 ml-4 list-disc space-y-1">
                                        {#each Object.entries(parsed) as [criteria, details] (criteria)}
                                            <li>
                                                <span class="font-medium">{criteria}:</span>
                                                {#if details?.rank}
                                                    {details.rank}
                                                    {#if details.score}
                                                        <span
                                                            class="ml-1 rounded bg-blue-100 px-1 py-0.5 text-xs text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                                                        >
                                                            (Score: {details.score})
                                                        </span>
                                                    {/if}
                                                {/if}
                                            </li>
                                        {/each}
                                    </ul>
                                </div>
                            {/if}
                            {#if jam.host}
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">Hosted by {jam.host}</p>
                            {/if}
                        </div>
                    {/each}
                </div>
            </div>
        {/if}
    </div>
{/if}

{#if (currentScreenshots && currentScreenshots.length > 0) || (editPermissions.canEdit && !previewingVisitorView)}
    <ScreenshotsGallery
        screenshots={currentScreenshots}
        blur={!!game.is_nsfw}
        onOpenLightbox={openLightbox}
        canEdit={editPermissions.canEdit && !previewingVisitorView}
        gameSlug={game.slug}
        onUpdate={handleMediaUpdate}
    />
{/if}

{#if game.additional_links && game.additional_links.length > 0}
    <DownloadsList
        gameId={game.id}
        links={game.additional_links}
        getPlatformIcon={(platform) =>
            `<i class="${
                platform === 'windows'
                    ? 'icon-windows text-platform-windows'
                    : platform === 'linux'
                      ? 'icon-linux text-platform-linux'
                      : platform === 'mac'
                        ? 'icon-apple text-platform-mac'
                        : platform === 'android'
                          ? 'icon-android text-platform-android'
                          : platform === 'web'
                            ? 'icon-web text-platform-web'
                            : 'icon-external-link text-gray-600 dark:text-gray-400'
            }"></i>`}
    />
{/if}

{#if publicLists && publicLists.length > 0}
    <div id="featured-lists" class="mb-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                Featured in {publicListsCount} Public {publicListsCount === 1 ? 'List' : 'Lists'}
            </h2>
            {#if publicListsCount > publicLists.length}
                <Link href={route('lists.public', { game: game.id })} class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                    View all {publicListsCount} lists
                </Link>
            {/if}
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each publicLists as list (list.id)}
                {@const colors = getPublicListColors(list.type)}
                <Link
                    href={route('lists.show', list.id)}
                    class="group block rounded-lg border-l-4 {colors.border} bg-white p-4 shadow-sm transition-all hover:shadow-md dark:bg-gray-700/50"
                >
                    <div class="mb-2 flex items-start justify-between">
                        <h3 class="font-medium text-gray-900 group-hover:text-blue-600 dark:text-gray-100 dark:group-hover:text-blue-400">
                            {list.name}
                        </h3>
                        <span
                            class="ml-2 shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-600 dark:text-gray-300"
                        >
                            {list.entries_count}
                            {list.entries_count === 1 ? 'game' : 'games'}
                        </span>
                    </div>
                    <div class="mb-2">
                        <span
                            class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold {colors.bg} {colors.text} {colors.darkBg} {colors.darkText}"
                        >
                            {list.type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
                        </span>
                    </div>
                    {#if list.description}
                        <p class="mb-2 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">{list.description}</p>
                    {/if}
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        {#if list.user.avatar}
                            <img src={list.user.avatar} alt={list.user.name} class="h-5 w-5 rounded-full" />
                        {:else}
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-600">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{list.user.name.charAt(0).toUpperCase()}</span>
                            </div>
                        {/if}
                        <span>by {list.user.name}</span>
                    </div>
                </Link>
            {/each}
        </div>
    </div>
{/if}

{#if gameVersions && gameVersions.data.length > 0}
    <div id="versions" class="mb-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Version History</h2>

        {#if game.latest_version && (canBrowseLatestDialogue || latestVersionHasRouteMap)}
            <div class="mb-4 flex gap-3">
                {#if canBrowseLatestDialogue}
                    <a
                        href={route('dialogue.browser', { gameId: game.id })}
                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-blue-500 focus:border-blue-700 focus:ring focus:ring-blue-300 focus:outline-none active:bg-blue-700 disabled:opacity-25"
                    >
                        <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                            />
                        </svg>
                        Browse Dialogue
                    </a>
                {/if}
                {#if latestVersionHasRouteMap}
                    <a
                        href={route('games.route-map', { game: game.slug })}
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase transition hover:bg-gray-50 focus:border-gray-500 focus:ring focus:ring-gray-300 focus:outline-none active:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    >
                        <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Route Map
                    </a>
                {/if}
            </div>
        {/if}

        <div class="my-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="mb-3 text-base font-medium text-gray-900 dark:text-gray-100">Compare Versions</h3>
            <div class="flex flex-col items-end gap-4 sm:flex-row">
                <div>
                    <label for="compareFromVersionId" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">From Version</label>
                    <select
                        id="compareFromVersionId"
                        bind:value={compareFromVersionId}
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 text-gray-900 sm:w-auto dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value={null}>Select version...</option>
                        {#each currentVersions as version (version.id)}
                            {#if versionCharacterCounts[version.id] > 0}
                                <option value={version.id}>
                                    {version.version} ({new Date(version.published_at).toLocaleDateString('en-US', {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                    })})
                                </option>
                            {/if}
                        {/each}
                    </select>
                </div>
                <div>
                    <label for="compareToVersionId" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">To Version</label>
                    <select
                        id="compareToVersionId"
                        bind:value={compareToVersionId}
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 text-gray-900 sm:w-auto dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value={null}>Select version...</option>
                        {#each currentVersions as version (version.id)}
                            {#if versionCharacterCounts[version.id] > 0}
                                <option value={version.id}>
                                    {version.version} ({new Date(version.published_at).toLocaleDateString('en-US', {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                    })})
                                </option>
                            {/if}
                        {/each}
                    </select>
                </div>
                <div>
                    <button
                        type="button"
                        onclick={compareVersions}
                        disabled={!compareFromVersionId || !compareToVersionId || compareFromVersionId === compareToVersionId}
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        COMPARE
                    </button>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            {#each currentVersions as version (version.id)}
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-col gap-4 sm:flex-row">
                        <div class="flex flex-1 flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                            <div class="flex w-full items-center">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{formatLocalDate(version.published_at)}</div>
                            </div>
                            <div class="flex w-full items-center">
                                <div class="font-medium text-gray-900 dark:text-gray-100">Version {version.version}</div>
                            </div>
                            <div class="flex w-full items-center">
                                <div class="flex flex-wrap gap-1">
                                    {#each (version.supportedLanguages || [])
                                        .filter((sl: SupportedLanguage) => sl.is_available)
                                        .sort( (a: SupportedLanguage, b: SupportedLanguage) => a.language.ref_name.localeCompare(b.language.ref_name), ) as sl (sl.iso_code)}
                                        <img
                                            src={getLanguageFlag(sl.language.flag_code)}
                                            alt={sl.language.ref_name}
                                            title={sl.language.ref_name}
                                            class="h-4 w-4 rounded-sm"
                                        />
                                    {/each}
                                </div>
                            </div>
                            <div class="flex w-full items-center">
                                <div class="flex gap-2 text-lg">
                                    {#if version.is_windows}<i class="icon-windows text-platform-windows" title="Windows"></i>{/if}
                                    {#if version.is_linux}<i class="icon-linux text-platform-linux" title="Linux"></i>{/if}
                                    {#if version.is_mac}<i class="icon-apple text-platform-mac" title="Mac"></i>{/if}
                                    {#if version.is_android}<i class="icon-android text-platform-android" title="Android"></i>{/if}
                                    {#if version.is_web}<i class="icon-web text-platform-web" title="Web"></i>{/if}
                                </div>
                            </div>
                            <div class="flex w-full items-center text-sm whitespace-nowrap sm:w-auto">
                                <span class="text-gray-500">Words:</span>
                                <span class="ml-1 text-gray-900 dark:text-gray-100">{getVersionWordCount(version)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2">
                        {#if versionCharacterCounts[version.id] > 0}
                            <button
                                onclick={() => loadCharacterStats(version.id)}
                                disabled={characterStatsLoading === version.id || fileStatsLoading === version.id}
                                class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400"
                            >
                                {#if characterStatsLoading === version.id}
                                    <LoadingSpinner size="sm" />
                                    Loading...
                                {:else}
                                    View {versionCharacterCounts[version.id]} Characters
                                {/if}
                            </button>
                        {/if}
                        {#if versionHasRouteData[version.id] === true || version.has_route_data === true}
                            <a
                                href={route('games.route-map', { game: game.slug }) + '?version_id=' + version.id}
                                class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline dark:text-blue-400"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Route Map
                            </a>
                        {/if}
                        {#if versionHasFileStats[version.id]}
                            <button
                                onclick={() => loadFileStats(version.id)}
                                disabled={characterStatsLoading === version.id || fileStatsLoading === version.id}
                                class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400"
                            >
                                {#if fileStatsLoading === version.id}
                                    <LoadingSpinner size="sm" />
                                    Loading...
                                {:else}
                                    View File Stats
                                {/if}
                            </button>
                        {/if}
                    </div>

                    <CharacterStatsModal
                        versionId={version.id}
                        {showCharacterStats}
                        {characterStatsData}
                        statsLoading={characterStatsLoading === version.id}
                        {closeCharacterStatsDialog}
                        {getLanguageFlag}
                    />

                    <FileStatsModal
                        versionId={version.id}
                        {showFileStats}
                        {fileStatsData}
                        statsLoading={fileStatsLoading === version.id}
                        {closeFileStatsDialog}
                    />
                </div>
            {/each}
        </div>

        <div class="mt-4">
            <AdvancedPagination
                meta={versionsPagination}
                onPageChange={handleVersionsPageChange}
                onPerPageChange={handleVersionsPerPageChange}
                isLoading={versionsLoading}
                label="versions"
            />
        </div>
    </div>
{/if}

<div class="mb-6">
    <ReviewTextControls />
</div>

<div id="user-review-form" class="mb-6">
    <UserReviewForm gameId={game.id} gameName={game.effective_name} initialReview={userReview} />
</div>

<div id="reviews" class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Reviews</h2>
            {#if currentAvailableRatings.length > 0}
                <select
                    value={selectedRating || ''}
                    onchange={(e) =>
                        handleRatingFilterChange((e.target as HTMLSelectElement).value ? Number((e.target as HTMLSelectElement).value) : null)}
                    class="rounded border border-gray-200 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    disabled={reviewsLoading}
                >
                    <option value="">Any Stars</option>
                    {#each currentAvailableRatings as rating (rating)}
                        <option value={rating}>{rating} Stars</option>
                    {/each}
                </select>
            {/if}
        </div>
        <button
            onclick={handleToggleRatingsView}
            disabled={reviewsLoading}
            class="text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 dark:text-blue-400"
        >
            {reviewsLoading ? 'Loading...' : `Show ${showAllRatings ? 'reviews only' : 'all ratings'}`}
        </button>
    </div>

    {#if reviewsLoading}
        <div class="flex items-center justify-center py-8">
            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600 dark:text-gray-400">Loading reviews...</span>
        </div>
    {:else if filteredReviews.length === 0}
        <div class="py-8 text-center text-gray-500 dark:text-gray-400">
            No {showAllRatings ? 'ratings' : 'reviews'} found{selectedRating ? ` with ${selectedRating} star${selectedRating !== 1 ? 's' : ''}` : ''}.
        </div>
    {:else}
        <div class="space-y-6">
            {#each filteredReviews as review (review.id)}
                <div id="review-{review.id}" class="border-b border-gray-200 pb-6 last:border-0 dark:border-gray-700">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                <Link href={getReviewAuthorHref(review)} class="flex items-center gap-1 hover:underline">
                                    {#if review.user?.avatar}
                                        <img src={review.user.avatar} alt="" class="h-5 w-5 rounded-full" />
                                    {/if}
                                    {review.user?.name || review.rater.name}
                                    {#if review.user}
                                        <span
                                            class="ml-1 rounded bg-blue-100 px-1 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                                            >FVN.li</span
                                        >
                                    {/if}
                                </Link>
                            </span>
                            {#if !review.user && review.rater.external_platform}
                                <PlatformIcon platform={review.rater.external_platform} />
                            {/if}
                            <span class="text-sm text-gray-500 dark:text-gray-400">{formatLocalDate(review.published_at)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1 text-yellow-400">
                                {#each Array.from({ length: review.rating }) as _, i (i)}
                                    <svg class="h-5 w-5 fill-current" viewBox="0 0 20 20">
                                        <path
                                            fill-rule="evenodd"
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                        />
                                    </svg>
                                {/each}
                            </div>
                            {#if review.event_id}
                                <a
                                    href={`https://itch.io/event/${review.event_id}`}
                                    target="_blank"
                                    rel="noopener"
                                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                    title="View on itch.io"
                                >
                                    <i class="icon-external-link h-4 w-4"></i>
                                </a>
                            {/if}
                            {#if review.user}
                                <button
                                    onclick={() => copyReviewLink(review.id)}
                                    class="text-gray-400 hover:text-blue-500 dark:text-gray-500 dark:hover:text-blue-400"
                                    title={copiedReviewId === review.id ? 'Link copied!' : 'Copy link to review'}
                                >
                                    {#if copiedReviewId === review.id}
                                        <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    {:else}
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                            />
                                        </svg>
                                    {/if}
                                </button>
                            {/if}
                            <button
                                onclick={() => {
                                    reportingReviewId = review.id;
                                    reportingReviewerName = review.user?.name || review.rater.name;
                                }}
                                class="text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                                title="Report review"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {#if review.review && (!showAllRatings || review.is_reviewed)}
                        {#if review.has_spoilers && !revealedSpoilers[review.id]}
                            <button
                                onclick={() => revealSpoilers(review.id)}
                                class="flex items-center gap-2 rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-800 transition-colors hover:bg-yellow-100 dark:border-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-200 dark:hover:bg-yellow-900/50"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                                    />
                                </svg>
                                This review contains spoilers — click to reveal
                            </button>
                        {:else}
                            <div>
                                {#if review.has_spoilers}
                                    <span
                                        class="mr-1 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                                        >Spoilers</span
                                    >
                                {/if}
                                <div
                                    class="relative overflow-hidden transition-[max-height] duration-300 ease-in-out"
                                    style={!expandedReviews[review.id] && shouldCollapseReview(review.review) ? 'max-height: 200px;' : undefined}
                                >
                                    <div class="prose max-w-none text-gray-600 dark:text-gray-300 dark:prose-invert" style={reviewStyles}>
                                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                        {@html review.review}
                                    </div>
                                    {#if !expandedReviews[review.id] && shouldCollapseReview(review.review)}
                                        <div
                                            class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-white dark:from-gray-800"
                                        ></div>
                                    {/if}
                                </div>
                                {#if shouldCollapseReview(review.review)}
                                    <button
                                        onclick={() => toggleReviewExpanded(review.id)}
                                        class="mt-1 text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        {expandedReviews[review.id] ? 'Show less' : 'Read more'}
                                    </button>
                                {/if}
                            </div>
                        {/if}
                    {/if}
                </div>
            {/each}
        </div>
    {/if}

    <div class="mt-4">
        <AdvancedPagination
            meta={reviewsPagination}
            onPageChange={handlePageChange}
            onPerPageChange={handleReviewsPerPageChange}
            isLoading={reviewsLoading}
            label="reviews"
        />
    </div>
</div>

{#if similarGames && similarGames.length > 0}
    <div id="similar-games" class="mt-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Similar Games</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            {#each similarGames as sg (sg.id)}
                <Link
                    href={route('games.show', sg.slug)}
                    class="group flex flex-col overflow-hidden rounded-xl border border-gray-200/50 bg-white/70 shadow transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg dark:border-gray-700/50 dark:bg-gray-800/70"
                >
                    <div class="relative aspect-[315/250] w-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                        {#if sg.thumb_url}
                            <img
                                src={sg.thumb_url}
                                alt={sg.name}
                                class="h-full w-full {sg.platform === 'steam' ? 'object-contain' : 'object-cover'}"
                                loading="lazy"
                            />
                        {:else}
                            <div class="flex h-full w-full items-center justify-center">
                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                        {/if}
                    </div>
                    <div class="flex flex-1 flex-col p-3">
                        <h3
                            class="line-clamp-2 text-sm font-medium text-gray-900 group-hover:text-blue-600 dark:text-gray-100 dark:group-hover:text-blue-400"
                        >
                            {sg.name}
                        </h3>
                        {#if sg.authors}
                            <p class="mt-1 line-clamp-1 text-xs text-gray-500 dark:text-gray-400">{sg.authors}</p>
                        {/if}
                        <div class="mt-auto flex items-center gap-2 pt-2">
                            {#if typeof sg.rating_score === 'number' && sg.rating_score > 0}
                                <span class="flex items-center gap-0.5 text-xs text-yellow-600 dark:text-yellow-400">
                                    <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                        />
                                    </svg>
                                    {sg.rating_score.toFixed(1)}
                                </span>
                            {/if}
                            {#if sg.status}
                                <span
                                    class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    >{sg.status}</span
                                >
                            {/if}
                        </div>
                    </div>
                </Link>
            {/each}
        </div>
    </div>
{/if}

{#if developerGames && developerGames.length > 0}
    <div class="mt-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">More by This Developer</h2>
        <div class="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
            {#each developerGames as dg (dg.id)}
                <Link
                    href={route('games.show', dg.slug)}
                    class="group flex flex-col overflow-hidden rounded-xl border border-gray-200/50 bg-white/70 shadow transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg dark:border-gray-700/50 dark:bg-gray-800/70"
                >
                    <div class="relative aspect-[315/250] w-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                        {#if dg.thumb_url}
                            <img
                                src={dg.thumb_url}
                                alt={dg.name}
                                class="h-full w-full {dg.platform === 'steam' ? 'object-contain' : 'object-cover'}"
                                loading="lazy"
                            />
                        {:else}
                            <div class="flex h-full w-full items-center justify-center">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                        {/if}
                    </div>
                    <div class="flex flex-1 flex-col p-2">
                        <h3
                            class="line-clamp-2 text-xs font-medium text-gray-900 group-hover:text-blue-600 dark:text-gray-100 dark:group-hover:text-blue-400"
                        >
                            {dg.name}
                        </h3>
                        <div class="mt-auto flex items-center gap-1 pt-1">
                            {#if typeof dg.rating_score === 'number' && dg.rating_score > 0}
                                <span class="flex items-center gap-0.5 text-[10px] text-yellow-600 dark:text-yellow-400">
                                    <svg class="h-3 w-3 fill-current" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                        />
                                    </svg>
                                    {dg.rating_score.toFixed(1)}
                                </span>
                            {/if}
                            {#if dg.status}
                                <span
                                    class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    >{dg.status}</span
                                >
                            {/if}
                        </div>
                    </div>
                </Link>
            {/each}
        </div>
    </div>
{/if}

{#if isLightboxOpen}
    <ScreenshotsLightbox isOpen={isLightboxOpen} screenshots={currentScreenshots} startIndex={lightboxIndex} onClose={closeLightbox} />
{/if}

{#if reportingReviewId}
    <ReportReviewModal
        ratingId={reportingReviewId}
        reviewerName={reportingReviewerName}
        isOpen={true}
        onClose={() => {
            reportingReviewId = null;
            reportingReviewerName = '';
        }}
    />
{/if}

<GameVersionComparisonModal
    {showVersionComparison}
    {versionComparisonData}
    isLoadingComparison={showVersionComparison && !versionComparisonData}
    {activeComparisonTab}
    setActiveComparisonTab={(tab) => {
        activeComparisonTab = tab;
    }}
    {closeVersionComparisonDialog}
    {formatBytes}
/>
