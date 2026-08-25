<script lang="ts">
    import ArrowPathIcon from '@/components/icons/ArrowPath.svelte';
    import FunnelIcon from '@/components/icons/Funnel.svelte';
    import InformationCircleIcon from '@/components/icons/InformationCircle.svelte';
    import NoSymbolIcon from '@/components/icons/NoSymbol.svelte';
    import { untrack } from 'svelte';
    import FilterModal from '@/components/FilterModal.svelte';
    import { useGameFilters } from '@/hooks/useGameFilters.svelte';
    import { usePlatformIcons, type GameCardPlatform } from '@/hooks/usePlatformIcons';
    import { fetchRandomGameSlug } from '@/api';
    import { useStorePlatformIcons, type StorePlatform } from '@/hooks/useStorePlatformIcons';
    import ActiveFilterChips from '@/components/games/ActiveFilterChips.svelte';
    import SortControls from '@/components/games/SortControls.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import GamesGrid from '@/components/games/GamesGrid.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import type { PaginationMeta } from '@/components/Pagination.svelte';
    import type { CurrentFilters, FilterOptions } from '@/types';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags as SeoMetaTags } from '@/types/meta-tags';
    import { router } from '@inertiajs/svelte';
    import { Alert, Button, Card } from '@/components/ui';

    interface GamesIndexGame {
        id: number;
        name: string;
        effective_name: string;
        slug: string;
        description?: string;
        thumb_url?: string;
        optimized_thumbnails?: {
            default?: { path: string; width: number; height: number };
        };
        rating_score?: number;
        rating_count?: number;
        status: string;
        game_engine?: string;
        is_nsfw: boolean;
        is_paid: boolean;
        has_demo: boolean;
        is_delisted: boolean;
        authors?: string;
        tags?: Array<{ id: number; name: string; slug: string }>;
        gameJams?: Array<{ id: number; name: string }>;
        supported_languages?: Array<{
            iso_code: string;
            ref_name: string;
            flag_code: string;
        }>;
        is_windows?: boolean;
        is_linux?: boolean;
        is_mac?: boolean;
        is_android?: boolean;
        is_web?: boolean;
        platform?: 'itch_io' | 'steam' | 'other';
        english_word_count?: number;
        primary_word_count?: number | null;
        primary_language_label?: string | null;
        trending_score?: number;
        initially_published_at?: string;
        latest_version_published_at?: string;
        rating?: number;
        is_on_sale?: boolean;
        [key: string]: unknown;
        created_at: string;
        updated_at: string;
    }

    interface PaginationLinks {
        first?: string;
        last?: string;
        prev?: string | null;
        next?: string | null;
    }

    interface GamesIndexProps {
        games: {
            data: GamesIndexGame[];
            links: PaginationLinks;
            meta: PaginationMeta;
        };
        filters: FilterOptions;
        currentFilters: CurrentFilters;
        metaTags: SeoMetaTags;
        ignoredCount?: number;
        ignoredGameIds?: number[];
    }

    let { games, filters, currentFilters, metaTags, ignoredCount = 0, ignoredGameIds = [] }: GamesIndexProps = $props();

    let showFilters = $state(false);
    let localIgnoredGameIds = $state<number[]>(untrack(() => ignoredGameIds));

    const { updateFilters, toggleFilter, clearFilters, hasActiveFilters, buildActiveFilterChips, buildPageUrl } = useGameFilters({
        getCurrentFilters: () => currentFilters,
        getFilters: () => filters,
        onGamesPage: true,
    });

    const { getPlatformIcon: getTypedPlatformIcon } = usePlatformIcons();
    const getPlatformIcon = (platform: string) => {
        return getTypedPlatformIcon(platform as GameCardPlatform);
    };

    const { getStorePlatformIcon: getTypedStorePlatformIcon } = useStorePlatformIcons();
    const getStorePlatformIcon = (platform: string) => {
        return getTypedStorePlatformIcon(platform as StorePlatform);
    };

    const handleIgnoreToggle = (gameId: number, isIgnored: boolean, newIgnoredGameIds: number[]) => {
        localIgnoredGameIds = newIgnoredGameIds;
    };

    let isRandomLoading = $state(false);

    // Navigate to a random game
    const handleRandomGame = async () => {
        isRandomLoading = true;
        try {
            const slug = await fetchRandomGameSlug();
            if (slug) {
                router.visit(route('games.show', { game: slug }));
            }
        } catch {
            // Silently fail
        } finally {
            isRandomLoading = false;
        }
    };

    const resolveGamesMeta = () => {
        const rawMeta: PaginationMeta = (games as GamesIndexProps['games'])?.meta || ({} as PaginationMeta);
        const rawTop = games as unknown as {
            total?: number;
            current_page?: number;
            last_page?: number;
        };
        const perPageVal = Number(rawMeta.per_page ?? currentFilters.perPage ?? 8) || 8;
        const total = Number(rawMeta.total ?? rawTop.total ?? games?.data?.length ?? 0) || 0;
        const current = Number(rawMeta.current_page ?? rawTop.current_page ?? 1) || 1;
        const last = Number(rawMeta.last_page ?? rawTop.last_page ?? Math.max(1, Math.ceil(total / perPageVal))) || 1;
        const from = Number(rawMeta.from ?? (total > 0 ? (current - 1) * perPageVal + 1 : 0));
        const to = Number(rawMeta.to ?? (total > 0 ? Math.min(current * perPageVal, total) : 0));
        return {
            current_page: current,
            last_page: last,
            total,
            from,
            to,
            per_page: perPageVal,
        };
    };

    const gamesMeta = $derived(resolveGamesMeta());

    const getActiveFilterCount = () => {
        return buildActiveFilterChips().length;
    };
</script>

<SeoHead {metaTags} />

<FilterModal
    isOpen={showFilters}
    onClose={() => {
        showFilters = false;
    }}
    {filters}
    {currentFilters}
    onGamesPage={true}
/>

<div class="space-y-8">
    <PageHeader title="Browse Visual Novels" class="mb-0" />

    <Card variant="glass" padding="none" class="-mt-2 px-4 py-3 shadow-none">
        <div class="flex flex-col gap-3 min-[56rem]:flex-row min-[56rem]:items-center min-[56rem]:justify-between">
            <div class="flex flex-wrap items-center gap-2 min-[56rem]:flex-1">
                <ActiveFilterChips chips={buildActiveFilterChips()} onClearAll={clearFilters} {getPlatformIcon} {getStorePlatformIcon} />
            </div>

            <div class="hidden h-6 w-px bg-gray-300 min-[56rem]:block dark:bg-gray-600"></div>

            <SortControls
                currentSort={currentFilters.sort || ''}
                currentDirection={currentFilters.direction === 'asc' || currentFilters.direction === 'desc' ? currentFilters.direction : 'desc'}
                sortOptions={filters.sortOptions || {}}
                onSortChange={(sort) => updateFilters({ sort })}
                onDirectionChange={(direction) => updateFilters({ direction })}
                hasSearch={Boolean(currentFilters.search?.trim())}
            />

            <div class="hidden h-6 w-px bg-gray-300 min-[56rem]:block dark:bg-gray-600"></div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    tone="warning"
                    size="sm"
                    onclick={handleRandomGame}
                    disabled={isRandomLoading}
                    loading={isRandomLoading}
                >
                    {#if !isRandomLoading}
                        <ArrowPathIcon class="h-3.5 w-3.5" />
                    {/if}
                    {isRandomLoading ? 'Loading...' : "I'm Feeling Lucky"}
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    tone="neutral"
                    size="sm"
                    onclick={() => {
                        showFilters = !showFilters;
                    }}
                    aria-expanded={showFilters}
                    aria-controls="filter-modal"
                >
                    <FunnelIcon class="h-4 w-4" />
                    Filters
                    {#if hasActiveFilters()}
                        <span class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-blue-600 text-xs text-white">
                            {getActiveFilterCount()}
                        </span>
                    {/if}
                </Button>
            </div>
        </div>
    </Card>

    {#if currentFilters.usingDefaultLanguages}
        <Alert tone="note" layout="inline" role="status">
            {#snippet icon()}<InformationCircleIcon class="h-5 w-5" />{/snippet}
            Showing games in your preferred languages.
            {#snippet actions()}
                <Button
                    type="button"
                    variant="solid"
                    tone="info"
                    size="sm"
                    onclick={() => updateFilters({ selectedLanguages: [], noDefaults: true })}
                >
                    Show all
                </Button>
            {/snippet}
        </Alert>
    {/if}

    {#if ignoredCount > 0 && !currentFilters.showIgnored}
        <Alert tone="neutral" layout="inline" role="status" class="mb-4">
            {#snippet icon()}<NoSymbolIcon class="h-5 w-5" />{/snippet}
            <strong>{ignoredCount}</strong>
            {ignoredCount === 1 ? 'game' : 'games'} hidden from results
            {#snippet actions()}
                <Button type="button" variant="solid" tone="primary" size="sm" onclick={() => updateFilters({ showIgnored: true })}
                    >Show Ignored</Button
                >
            {/snippet}
        </Alert>
    {/if}

    {#if currentFilters.showIgnored}
        <Alert tone="info" layout="inline" role="status" class="mb-4">
            {#snippet icon()}<InformationCircleIcon class="h-5 w-5" />{/snippet}
            Showing ignored games
            {#snippet actions()}
                <Button type="button" variant="solid" tone="neutral" size="sm" onclick={() => updateFilters({ showIgnored: false })}
                    >Hide Ignored</Button
                >
            {/snippet}
        </Alert>
    {/if}

    <GamesGrid
        games={games.data}
        {currentFilters}
        ignoredGameIds={localIgnoredGameIds}
        onPlatformClick={(p) => toggleFilter('platform', p)}
        onLanguageClick={(iso) => toggleFilter('language', iso)}
        onTagClick={(tagId) => toggleFilter('tag', tagId)}
        onStatusClick={(status) => toggleFilter('status', status)}
        onStorePlatformClick={(platform) => toggleFilter('storePlatform', platform)}
        onNsfwToggle={() => updateFilters({ nsfw: !currentFilters.nsfw })}
        onPaidToggle={() => updateFilters({ showPaid: !currentFilters.showPaid })}
        onDemoToggle={() => updateFilters({ showDemo: !currentFilters.showDemo })}
        onSaleToggle={() => updateFilters({ showSale: !currentFilters.showSale })}
        onDelistedToggle={() => updateFilters({ delisted: !currentFilters.delisted })}
        {updateFilters}
        onIgnoreToggle={handleIgnoreToggle}
    />

    <Pagination
        layout="full"
        meta={gamesMeta}
        label="results"
        onChange={(page) => updateFilters({ page })}
        onPerPageChange={(perPage) => updateFilters({ perPage })}
        perPageOptions={[8, 16, 24, 32]}
        {buildPageUrl}
    />
</div>
