<script lang="ts">
    import GameStats from '@/components/GameStats.svelte';
    import GameVersionComparisonModal from '@/components/GameVersionComparisonModal.svelte';
    import GameHeader from '@/components/games/GameHeader.svelte';
    import ScreenshotsGallery from '@/components/games/ScreenshotsGallery.svelte';
    import ScreenshotsLightbox from '@/components/games/ScreenshotsLightbox.svelte';
    import GameDetailsSection from '@/components/games/GameDetailsSection.svelte';
    import { Card } from '@/components/ui';
    import DownloadsList from '@/components/games/DownloadsList.svelte';
    import GameRecommendationsSection from '@/components/games/GameRecommendationsSection.svelte';
    import GameReviewsSection from '@/components/games/GameReviewsSection.svelte';
    import GameVersionHistory from '@/components/games/GameVersionHistory.svelte';
    import UserReviewForm from '@/components/games/UserReviewForm.svelte';
    import ReportReviewModal from '@/components/games/ReportReviewModal.svelte';
    import ReviewTextControls, { useReviewTextStyles } from '@/components/ReviewTextControls.svelte';
    import { Link, page } from '@inertiajs/svelte';
    import SeoHead, { createGameMetaTags } from '@/components/seo/SeoHead.svelte';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { escapeStyleElementText } from '@/utils/style-html';
    import { formatBytes, getGamePlatforms, getPublicListColors } from '@/utils/game-show';
    import { fetchReviews, fetchVersions, fetchCharacterStats, fetchFileStats, uploadThumbnail, fetchVersionComparison } from '@/hooks/api';
    import type { GameShowProps, Screenshot } from '@/types/game-show';

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
    const auth = $derived((page as any)?.props?.auth);
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
                versionHasFileStats = {
                    ...versionHasFileStats,
                    ...data.versionHasFileStats,
                };
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

    const activePlatforms = $derived(getGamePlatforms(platforms, game.latest_version));

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

    const customCssStyleHtml = $derived(
        game.custom_css ? `<style>.game_description img { display: initial; } ${escapeStyleElementText(game.custom_css)}</style>` : '',
    );
</script>

<SeoHead metaTags={gameMetaTags} />

{#if customCssStyleHtml}
    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
    {@html customCssStyleHtml}
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

<GameHeader
    {game}
    {isAuthenticated}
    {currentThumbnail}
    {activePlatforms}
    {editPermissions}
    {previewingVisitorView}
    {visitorName}
    {visitorDescription}
    {isUploadingThumbnail}
    onThumbnailUpload={handleThumbnailUpload}
    onPreviewingVisitorViewChange={(previewing) => {
        previewingVisitorView = previewing;
    }}
    onViewModeUpdate={handleVisitorViewModeUpdate}
    onNameUpdate={handleCustomNameUpdate}
    onContentUpdate={handleCustomContentUpdate}
/>

{#if canSeeAnalytics && (clickStats || dailyStats)}
    <Card id="analytics" padding="lg" class="mb-6 scroll-mt-28">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Analytics</h2>
        <GameStats {clickStats} {dailyStats} />
    </Card>
{/if}

{#if game.is_visible}
    <GameDetailsSection {game} {detailItems} {visibleSupportedLanguages} />
{/if}

{#if (currentScreenshots && currentScreenshots.length > 0) || (editPermissions.canEdit && !previewingVisitorView)}
    <ScreenshotsGallery
        screenshots={currentScreenshots}
        blur={!!game.is_nsfw}
        onOpenLightbox={openLightbox}
        canEdit={editPermissions.canEdit && !previewingVisitorView}
        gameSlug={game.slug}
        gameName={game.name}
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
    <Card id="featured-lists" padding="lg" class="mb-6 scroll-mt-28">
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
    </Card>
{/if}

<GameVersionHistory
    gameSlug={game.slug}
    latestVersion={game.latest_version}
    {currentVersions}
    pagination={versionsPagination}
    {canBrowseLatestDialogue}
    {latestVersionHasRouteMap}
    {versionCharacterCounts}
    {versionHasFileStats}
    {versionHasRouteData}
    {compareFromVersionId}
    {compareToVersionId}
    {characterStatsLoading}
    {fileStatsLoading}
    {showCharacterStats}
    {showFileStats}
    {characterStatsData}
    {fileStatsData}
    {versionsLoading}
    onCompareFromChange={(versionId) => (compareFromVersionId = versionId)}
    onCompareToChange={(versionId) => (compareToVersionId = versionId)}
    onCompare={compareVersions}
    onLoadCharacterStats={loadCharacterStats}
    onLoadFileStats={loadFileStats}
    onCloseCharacterStats={closeCharacterStatsDialog}
    onCloseFileStats={closeFileStatsDialog}
    onPageChange={handleVersionsPageChange}
    onPerPageChange={handleVersionsPerPageChange}
/>

<div class="mb-6">
    <ReviewTextControls />
</div>

<div id="user-review-form" class="mb-6">
    <UserReviewForm gameId={game.id} gameName={game.effective_name} initialReview={userReview} />
</div>

<GameReviewsSection
    reviews={filteredReviews}
    availableRatings={currentAvailableRatings}
    {selectedRating}
    {showAllRatings}
    {reviewsLoading}
    {copiedReviewId}
    {expandedReviews}
    {revealedSpoilers}
    {reviewStyles}
    pagination={reviewsPagination}
    onToggleRatingsView={handleToggleRatingsView}
    onRatingFilterChange={handleRatingFilterChange}
    onCopyReviewLink={copyReviewLink}
    onReportReview={(reviewId, reviewerName) => {
        reportingReviewId = reviewId;
        reportingReviewerName = reviewerName;
    }}
    onRevealSpoilers={revealSpoilers}
    onToggleReviewExpanded={toggleReviewExpanded}
    onPageChange={handlePageChange}
    onPerPageChange={handleReviewsPerPageChange}
/>

<GameRecommendationsSection id="similar-games" title="Similar Games" games={similarGames} />
<GameRecommendationsSection title="More by This Developer" games={developerGames} compact />

{#if isLightboxOpen}
    <ScreenshotsLightbox
        isOpen={isLightboxOpen}
        screenshots={currentScreenshots}
        startIndex={lightboxIndex}
        gameName={game.name}
        onClose={closeLightbox}
    />
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
