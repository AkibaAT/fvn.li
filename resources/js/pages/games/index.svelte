<script lang="ts">
    import FunnelIcon from '@/components/icons/Funnel.svelte';
    import InformationCircleIcon from '@/components/icons/InformationCircle.svelte';
    import NoSymbolIcon from '@/components/icons/NoSymbol.svelte';
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
    import { Alert, Button, ViewToggle } from '@/components/ui';
    import { VIEW_MODE_COOKIES, parseViewMode, writeViewModeCookie, type ViewMode } from '@/utils/view-mode';

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
        /** Persisted catalogue layout, resolved from the `games_view` cookie. */
        gamesView?: string;
    }

    let { games, filters, currentFilters, metaTags, ignoredCount = 0, ignoredGameIds = [], gamesView = 'grid' }: GamesIndexProps = $props();

    let showFilters = $state(false);

    // The cookie drives the server-rendered layout; the override keeps the toggle instant.
    let viewOverride = $state<ViewMode | null>(null);
    const viewMode = $derived<ViewMode>(viewOverride ?? parseViewMode(gamesView));

    function setViewMode(next: ViewMode) {
        viewOverride = next;
        writeViewModeCookie(VIEW_MODE_COOKIES.games, next);
    }

    const { updateFilters, toggleFilter, clearFilters, buildActiveFilterChips, buildPageUrl } = useGameFilters({
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
        const perPageVal = Number(rawMeta.per_page ?? currentFilters.perPage ?? 10) || 10;
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

<div class="space-y-5 max-sm:space-y-4">
    <PageHeader title="Visual novels" count={`${gamesMeta.total.toLocaleString()} titles`} class="mb-0">
        {#snippet actions()}
            <ViewToggle value={viewMode} onchange={setViewMode} label="Game list layout" />
        {/snippet}
    </PageHeader>

    <div class="flex flex-wrap items-center gap-2">
        <div class="order-last flex flex-wrap items-center gap-2 md:order-first">
            <button
                type="button"
                onclick={() => {
                    showFilters = !showFilters;
                }}
                aria-expanded={showFilters}
                aria-controls="filter-modal"
                class="inline-flex h-[30px] items-center gap-1.5 rounded-md border border-border-strong bg-surface px-[11px] text-[13px] font-medium text-fg transition-colors hover:border-fg"
            >
                <FunnelIcon class="h-[13px] w-[13px]" />
                Filters
                {#if getActiveFilterCount() > 0}
                    <span class="text-fg-faint">{getActiveFilterCount()}</span>
                {/if}
            </button>

            <ActiveFilterChips chips={buildActiveFilterChips()} onClearAll={clearFilters} {getPlatformIcon} {getStorePlatformIcon} />
        </div>

        <div class="order-first flex w-full items-center gap-2 md:order-last md:ml-auto md:w-auto">
            <SortControls
                currentSort={currentFilters.sort || ''}
                currentDirection={currentFilters.direction === 'asc' || currentFilters.direction === 'desc' ? currentFilters.direction : 'desc'}
                sortOptions={filters.sortOptions || {}}
                onSortChange={(sort) => updateFilters({ sort })}
                onDirectionChange={(direction) => updateFilters({ direction })}
                hasSearch={Boolean(currentFilters.search?.trim())}
            />

            <span class="h-4 w-px bg-border" aria-hidden="true"></span>

            <button
                type="button"
                onclick={handleRandomGame}
                disabled={isRandomLoading}
                class="text-[13px] text-fg-muted transition-colors hover:text-fg disabled:cursor-not-allowed disabled:opacity-50"
            >
                {isRandomLoading ? 'Loading…' : 'Random title'}
            </button>
        </div>
    </div>

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
            {ignoredCount === 1 ? 'game is' : 'games are'} on your ignore list. Matching ignored games are excluded from results.
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
        {ignoredGameIds}
        {viewMode}
        onPlatformClick={(p) => toggleFilter('platform', p)}
        onLanguageClick={(iso) => toggleFilter('language', iso)}
        onTagClick={(tagId) => toggleFilter('tag', tagId)}
        onStatusClick={(status) => toggleFilter('status', status)}
        onStorePlatformClick={(platform) => toggleFilter('storePlatform', platform)}
        onNsfwToggle={() => updateFilters({ nsfw: !currentFilters.nsfw })}
        onPaidToggle={() => updateFilters({ showPaid: !currentFilters.showPaid })}
        onDemoToggle={() => updateFilters({ showDemo: !currentFilters.showDemo })}
        onSaleToggle={() => updateFilters({ showSale: !currentFilters.showSale })}
        {updateFilters}
    />

    <Pagination
        layout="pages"
        meta={gamesMeta}
        label="results"
        onChange={(page) => updateFilters({ page })}
        onPerPageChange={(perPage) => updateFilters({ perPage })}
        perPageOptions={[10, 20, 30, 50]}
        {buildPageUrl}
    />
</div>
