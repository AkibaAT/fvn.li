<script lang="ts">
    import InformationCircleIcon from '@/components/icons/InformationCircle.svelte';
    import NoSymbolIcon from '@/components/icons/NoSymbol.svelte';
    import FilterBar from '@/components/games/filter/FilterBar.svelte';
    import { useGameFilters } from '@/hooks/useGameFilters.svelte';
    import { fetchRandomGameSlug } from '@/api';
    import SortControls from '@/components/games/SortControls.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import GamesGrid from '@/components/games/GamesGrid.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import type { PaginationMeta } from '@/types/game-show';
    import type { CurrentFilters, FilterOptions } from '@/types';
    import type { Game } from '@/types/game';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags as SeoMetaTags } from '@/types/meta-tags';
    import { router } from '@inertiajs/svelte';
    import { Alert, Button, ViewToggle } from '@/components/ui';
    import { VIEW_MODE_COOKIES, parseViewMode, writeViewModeCookie, type ViewMode } from '@/utils/view-mode';

    /**
     * Catalogue rows re-strengthen the fields the games index controller always
     * sends; `user_progress` keeps the GameCardGame row shape so rows stay
     * assignable to the GamesGrid/GameCard props.
     */
    interface GamesIndexGame extends Game {
        status: string;
        is_nsfw: boolean;
        is_paid: boolean;
        has_demo: boolean;
        created_at: string;
        updated_at: string;
        rating?: number;
        user_progress?: Array<{ id: number; game_id: number; user_id: number; receive_updates: boolean }>;
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

    // The cookie drives the server-rendered layout; the override keeps the toggle instant.
    let viewOverride = $state<ViewMode | null>(null);
    const viewMode = $derived<ViewMode>(viewOverride ?? parseViewMode(gamesView));

    function setViewMode(next: ViewMode) {
        viewOverride = next;
        writeViewModeCookie(VIEW_MODE_COOKIES.games, next);
    }

    const { updateFilters, toggleFilter, buildPageUrl } = useGameFilters({
        getCurrentFilters: () => currentFilters,
        getFilters: () => filters,
        onGamesPage: true,
    });

    let isRandomLoading = $state(false);

    const handleRandomGame = async () => {
        isRandomLoading = true;
        const slug = await fetchRandomGameSlug().catch(() => null);
        isRandomLoading = false;
        if (slug) router.visit(route('games.show', { game: slug }));
    };

    const resolveGamesMeta = () => {
        const rawMeta: PaginationMeta = (games as GamesIndexProps['games'])?.meta || ({} as PaginationMeta);
        const rawTop = games as unknown as {
            total?: number;
            current_page?: number;
            last_page?: number;
        };
        const perPageVal = Number(rawMeta.per_page ?? currentFilters.perPage ?? 12) || 12;
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
</script>

<SeoHead {metaTags} />

<div class="space-y-5 max-sm:space-y-4">
    <PageHeader title="Visual novels" count={`${gamesMeta.total.toLocaleString()} titles`} class="mb-6 max-sm:mb-4">
        {#snippet actions()}
            <Button type="button" variant="ghost" tone="neutral" size="sm" onclick={handleRandomGame} loading={isRandomLoading}>Random title</Button>
        {/snippet}
    </PageHeader>

    <div class="flex flex-wrap items-start gap-x-3 gap-y-2">
        <FilterBar {filters} {currentFilters} />

        <div class="ml-auto flex h-9 items-center gap-2">
            <SortControls
                currentSort={currentFilters.sort || ''}
                currentDirection={currentFilters.direction === 'asc' || currentFilters.direction === 'desc' ? currentFilters.direction : 'desc'}
                sortOptions={filters.sortOptions || {}}
                onSortChange={(sort) => updateFilters({ sort })}
                onDirectionChange={(direction) => updateFilters({ direction })}
                hasSearch={Boolean(currentFilters.search?.trim())}
            />

            <ViewToggle value={viewMode} onchange={setViewMode} label="Game list layout" />
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
    />

    <Pagination
        layout="pages"
        meta={gamesMeta}
        label="results"
        onChange={(page) => updateFilters({ page })}
        onPerPageChange={(perPage) => updateFilters({ perPage })}
        perPageOptions={[12, 24, 48]}
        {buildPageUrl}
    />
</div>
