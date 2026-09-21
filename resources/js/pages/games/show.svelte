<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import { toast } from '@/utils/toast';
    import { getErrorMessage } from '@/utils/async-action.svelte';
    import GameStats from '@/components/GameStats.svelte';
    import VersionComparisonModal from '@/components/VersionComparisonModal.svelte';
    import GameHeader from '@/components/games/GameHeader.svelte';
    import ScreenshotsGallery from '@/components/games/ScreenshotsGallery.svelte';
    import ScreenshotsLightbox from '@/components/games/ScreenshotsLightbox.svelte';
    import GameJamsSection from '@/components/games/GameJamsSection.svelte';
    import { Badge, Card, formatListType, listTypeBorderClass, listTypeTone } from '@/components/ui';
    import DownloadsList from '@/components/games/DownloadsList.svelte';
    import GameRecommendationsSection from '@/components/games/GameRecommendationsSection.svelte';
    import GameReviewsSection from '@/components/games/GameReviewsSection.svelte';
    import GameVersionHistory from '@/components/games/GameVersionHistory.svelte';
    import ReportReviewModal from '@/components/games/ReportReviewModal.svelte';
    import { useReviewStyleString } from '@/components/ReviewTextControls.svelte';
    import { Link, page } from '@inertiajs/svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import StickyBackBar, { type StickyBackBarSection } from '@/components/layout/StickyBackBar.svelte';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { escapeStyleElementText } from '@/utils/style-html';
    import { getGamePlatforms } from '@/utils/game-show';
    import { fetchReviews, fetchVersions, fetchCharacterStats, fetchFileStats, uploadThumbnail } from '@/api';
    import type { GameFact, GameShowProps, Screenshot } from '@/types/game-show';

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
        versionOptimizedArchiveAvailability = {},
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

    const auth = $derived((page.props as any)?.auth);
    const isAuthenticated = $derived(Boolean(auth?.user));

    const reviewStyles = useReviewStyleString();
    const latestVersionHasDialogue = $derived(
        game.latest_version
            ? (versionCharacterCounts[game.latest_version.id] ?? 0) > 0 && versionHasDialogueLines[game.latest_version.id] === true
            : false,
    );
    const latestVersionHasRouteMap = $derived(game.latest_version ? versionHasRouteData[game.latest_version.id] === true : false);
    const canBrowseLatestDialogue = $derived(Boolean(game.latest_version && !game.is_paid && latestVersionHasDialogue));

    let showAllRatings = $state(false);
    let selectedRating = $state<number | null>(null);
    let compareFromVersionId = $state<number | null>(null);
    let compareToVersionId = $state<number | null>(null);
    let showCharacterStats = $state<number | null>(null);
    let showFileStats = $state<number | null>(null);
    let showVersionComparison = $state(false);
    let characterStatsLoading = $state<number | null>(null);
    let fileStatsLoading = $state<number | null>(null);
    let isLightboxOpen = $state(false);
    let lightboxIndex = $state(0);
    let reportingReviewId = $state<number | null>(null);
    let reportingReviewerName = $state('');
    let copiedReviewId = $state<number | null>(null);
    let previewingVisitorView = $state(false);
    let isUploadingThumbnail = $state(false);
    const currentThumbnail = $derived<string | null>(game.optimized_thumbnail_url || null);
    const customScreenshots = $derived<Screenshot[]>(game.custom_screenshots || game.effective_screenshots || game.screenshots || []);
    let visitorScreenshots = $derived<Screenshot[]>(game.effective_screenshots || game.screenshots || []);
    let visitorName = $derived(game.effective_name);
    let visitorDescription = $derived(game.effective_description || game.full_description || game.description || '');
    let visitorViewMode = $derived<'custom' | 'original'>(game.view_mode === 'custom' ? 'custom' : 'original');
    const currentScreenshots = $derived(editPermissions.canEdit && !previewingVisitorView ? customScreenshots : visitorScreenshots);

    let characterStatsData = $state<any>(null);
    let fileStatsData = $state<any>(null);

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

    let reviewsInitial = true;
    let reviewsRevision = $state(0);
    let reviewsError = $state<string | null>(null);
    $effect(() => {
        const revision = reviewsRevision;
        const gameId = game.id;
        const params = { showAllRatings, selectedRating, page: reviewsPage, perPage: reviewsPerPage };
        if (reviewsInitial) {
            reviewsInitial = false;
            if (revision === 0 && params.page === 1 && !params.showAllRatings && params.selectedRating === null) return;
        }
        let active = true;
        reviewsLoading = true;
        reviewsError = null;
        fetchReviews(gameId, params)
            .then((data) => {
                if (active) reviewsData = data;
            })
            .catch(() => {
                if (active) reviewsError = 'Unable to load reviews. Please try again.';
            })
            .finally(() => {
                if (active) reviewsLoading = false;
            });
        return () => {
            active = false;
        };
    });

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

    let versionsInitial = true;
    $effect(() => {
        const gameId = game.id;
        const page = versionsPage;
        const perPage = versionsPerPage;
        if (versionsInitial) {
            versionsInitial = false;
            if (page === 1) return;
        }
        let active = true;
        versionsLoading = true;
        fetchVersions(gameId, page, perPage)
            .then((data) => {
                if (!active) return;
                versionHasFileStats = {
                    ...versionHasFileStats,
                    ...data.versionHasFileStats,
                };
                versionOptimizedArchiveAvailability = {
                    ...versionOptimizedArchiveAvailability,
                    ...data.versionOptimizedArchiveAvailability,
                };
                versionsData = data;
            })
            .catch(() => {
                if (active) toast.error('Unable to load versions. Please try again.');
            })
            .finally(() => {
                if (active) versionsLoading = false;
            });
        return () => {
            active = false;
        };
    });

    $effect(() => {
        characterStatsData = null;
        characterStatsLoading = showCharacterStats;
        if (showCharacterStats === null) return;
        let active = true;
        fetchCharacterStats(game.slug, showCharacterStats)
            .then((data) => {
                if (active) characterStatsData = data;
            })
            .catch(() => {
                if (!active) return;
                showCharacterStats = null;
                toast.error('Unable to load character statistics. Please try again.');
            })
            .finally(() => {
                if (active) characterStatsLoading = null;
            });
        return () => {
            active = false;
        };
    });

    $effect(() => {
        fileStatsData = null;
        fileStatsLoading = showFileStats;
        if (showFileStats === null) return;
        let active = true;
        fetchFileStats(game.slug, showFileStats)
            .then((data) => {
                if (active) fileStatsData = data;
            })
            .catch(() => {
                if (!active) return;
                showFileStats = null;
                toast.error('Unable to load file statistics. Please try again.');
            })
            .finally(() => {
                if (active) fileStatsLoading = null;
            });
        return () => {
            active = false;
        };
    });

    const activePlatforms = $derived(getGamePlatforms(platforms, game.latest_version));

    const facts = $derived.by((): GameFact[] => {
        const items: GameFact[] = [];
        const primaryWords = primaryStats?.words;
        if (typeof primaryWords === 'number' && primaryWords > 0) {
            const isEnglish = !primaryLanguageLabel || primaryLanguageLabel === 'EN';
            const englishWords = englishStats?.words;
            items.push({
                label: isEnglish ? 'Words' : `Words (${primaryLanguageLabel})`,
                value: primaryWords.toLocaleString(),
                hint: !isEnglish && typeof englishWords === 'number' && englishWords > 0 ? ` · EN ${englishWords.toLocaleString()}` : undefined,
            });
        }
        if (estimatedReadingTime) {
            items.push({
                label: 'Reading Time',
                value:
                    estimatedReadingTime.hours > 0
                        ? `~${estimatedReadingTime.hours} hr ${estimatedReadingTime.minutes} min`
                        : `~${estimatedReadingTime.minutes} min`,
            });
        }
        const initialRelease = formatLocalDate(game.initially_published_at);
        if (initialRelease) items.push({ label: 'Initial Release', value: initialRelease });
        const latestUpdate = formatLocalDate(game.latest_version?.published_at);
        if (latestUpdate) items.push({ label: 'Latest Update', value: latestUpdate });
        if (game.latest_version?.version) items.push({ label: 'Version', value: game.latest_version.version });
        if (game.game_engine) items.push({ label: 'Engine', value: String(game.game_engine) });
        return items;
    });

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

    const openLightbox = (index: number) => {
        if (currentScreenshots?.[index]) {
            lightboxIndex = index;
            isLightboxOpen = true;
        }
    };
    const closeLightbox = () => {
        isLightboxOpen = false;
    };

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
        showCharacterStats = versionId;
    };
    const loadFileStats = (versionId: number) => {
        showFileStats = versionId;
    };

    const closeCharacterStatsDialog = () => {
        showCharacterStats = null;
    };
    const closeFileStatsDialog = () => {
        showFileStats = null;
    };

    const compareVersions = () => {
        if (!compareFromVersionId || !compareToVersionId) return;
        showVersionComparison = true;
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
            toast.error('Please upload an image file');
            return;
        }

        isUploadingThumbnail = true;
        try {
            await uploadThumbnail({ gameSlug: game.slug, file });
            if (!(await refreshPage(['game', 'metaTags']))) return;
        } catch (error: any) {
            console.error('Failed to upload thumbnail', error);
            if (error?.response?.data?.message) {
                toast.error(error.response.data.message);
            } else if (error?.response?.data?.errors?.thumbnail) {
                toast.error(error.response.data.errors.thumbnail[0]);
            } else {
                toast.error(getErrorMessage(error, 'Failed to upload thumbnail. Please try again.'));
            }
        } finally {
            isUploadingThumbnail = false;
        }
    };

    $effect(() => {
        if (typeof window === 'undefined') return;
        const hash = window.location.hash;
        if (hash?.startsWith('#review-')) {
            setTimeout(() => {
                const el = document.getElementById(hash.slice(1));
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('bg-surface-alt', 'rounded-md', 'transition-colors');
                    setTimeout(() => el.classList.remove('bg-surface-alt'), 3000);
                }
            }, 500);
        }
    });

    const sections = $derived.by((): StickyBackBarSection[] => {
        const items: StickyBackBarSection[] = [];
        if (canSeeAnalytics && (clickStats || dailyStats)) items.push({ id: 'analytics', label: 'Analytics' });
        if (game.is_visible && game.game_jams && game.game_jams.length > 0) items.push({ id: 'game-jams', label: 'Game Jams' });
        if (currentScreenshots && currentScreenshots.length > 0) items.push({ id: 'screenshots', label: 'Screenshots' });
        if (game.additional_links && game.additional_links.length > 0) items.push({ id: 'downloads', label: 'Downloads' });
        if (publicLists && publicLists.length > 0) items.push({ id: 'featured-lists', label: 'Lists' });
        if (currentVersions.length > 0) items.push({ id: 'versions', label: 'Versions' });
        items.push({ id: 'reviews', label: 'Reviews' });
        if (similarGames && similarGames.length > 0) items.push({ id: 'similar-games', label: 'Similar' });
        return items;
    });

    const customCssStyleHtml = $derived(
        game.custom_css ? `<style>.game_description img { display: initial; } ${escapeStyleElementText(game.custom_css)}</style>` : '',
    );
</script>

<SeoHead {metaTags} />

{#if customCssStyleHtml}
    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
    {@html customCssStyleHtml}
{/if}

<StickyBackBar href={route('games.index')} label="Back to Game List" {sections} />

<GameHeader
    {game}
    {isAuthenticated}
    {currentThumbnail}
    {activePlatforms}
    supportedLanguages={visibleSupportedLanguages}
    {facts}
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
    <Card id="analytics" padding="lg" class="mb-6 scroll-mt-32">
        <h2 class="mb-4 text-title font-semibold text-fg">Analytics</h2>
        <GameStats {clickStats} {dailyStats} />
    </Card>
{/if}

{#if game.is_visible}
    <GameJamsSection gameJams={game.game_jams ?? []} />
{/if}

{#if (currentScreenshots && currentScreenshots.length > 0) || (editPermissions.canEdit && !previewingVisitorView)}
    <ScreenshotsGallery
        screenshots={currentScreenshots}
        blur={!!game.is_nsfw}
        onOpenLightbox={openLightbox}
        canEdit={editPermissions.canEdit && !previewingVisitorView}
        gameSlug={game.slug}
        gameName={game.name}
    />
{/if}

{#if game.additional_links && game.additional_links.length > 0}
    <DownloadsList gameId={game.id} links={game.additional_links} />
{/if}

{#if publicLists && publicLists.length > 0}
    <Card id="featured-lists" padding="lg" class="mb-6 scroll-mt-32">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-title font-semibold text-fg">
                Featured in {publicListsCount} Public {publicListsCount === 1 ? 'List' : 'Lists'}
            </h2>
            {#if publicListsCount > publicLists.length}
                <Link href={route('lists.public', { game: game.id })} class="text-ui text-fg-muted transition-colors hover:text-fg hover:underline">
                    View all {publicListsCount} lists
                </Link>
            {/if}
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {#each publicLists as list (list.id)}
                <Link
                    href={route('lists.show', list.id)}
                    class="group block rounded-md border border-l-4 border-border {listTypeBorderClass(
                        list.type,
                    )} p-4 transition-colors hover:bg-surface-alt"
                >
                    <div class="mb-1.5 flex items-center gap-2">
                        <Badge tone={listTypeTone(list.type)} size="sm">{formatListType(list.type)}</Badge>
                        <span class="text-2xs text-fg-faint">
                            {list.entries_count}
                            {list.entries_count === 1 ? 'game' : 'games'}
                        </span>
                    </div>
                    <h3 class="text-md font-medium text-fg group-hover:underline">{list.name}</h3>
                    {#if list.description}
                        <p class="mt-1 line-clamp-2 text-ui text-fg-muted">{list.description}</p>
                    {/if}
                    <div class="mt-2 flex items-center gap-2 text-xs text-fg-faint">
                        {#if list.user.avatar}
                            <img src={list.user.avatar} alt="" aria-hidden="true" class="h-5 w-5 rounded-full" />
                        {:else}
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-surface-alt text-2xs font-medium text-fg-muted"
                                aria-hidden="true"
                            >
                                {list.user.name.charAt(0).toUpperCase()}
                            </span>
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
    {versionOptimizedArchiveAvailability}
    canDownloadOptimizedArchives={editPermissions.canEdit && !previewingVisitorView}
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

<GameReviewsSection
    reviews={filteredReviews}
    gameId={game.id}
    hasRatings={(game.rating_count ?? 0) > 0}
    initialUserReview={userReview}
    {isAuthenticated}
    availableRatings={currentAvailableRatings}
    {selectedRating}
    {showAllRatings}
    {reviewsLoading}
    {reviewsError}
    onRefreshReviews={() => (reviewsRevision += 1)}
    {copiedReviewId}
    {expandedReviews}
    {revealedSpoilers}
    reviewStyles={reviewStyles.css}
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
<GameRecommendationsSection title="More by This Developer" games={developerGames} />

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

<VersionComparisonModal
    isOpen={showVersionComparison}
    onClose={() => (showVersionComparison = false)}
    gameId={game.id}
    fromVersionId={compareFromVersionId ?? undefined}
    toVersionId={compareToVersionId ?? undefined}
/>
