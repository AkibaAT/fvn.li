<script lang="ts">
    import { untrack } from 'svelte';
    import FilterModal from '@/components/FilterModal.svelte';
    import { useGameFilters } from '@/hooks/useGameFilters.svelte';
    import { usePlatformIcons, type GameCardPlatform } from '@/hooks/usePlatformIcons';
    import { useStorePlatformIcons, type StorePlatform } from '@/hooks/useStorePlatformIcons';
    import ActiveFilterChips from '@/components/games/ActiveFilterChips.svelte';
    import SortControls from '@/components/games/SortControls.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import GamesGrid from '@/components/games/GamesGrid.svelte';
    import PaginationControls from '@/components/games/PaginationControls.svelte';
    import type { CurrentFilters, FilterOptions } from '@/types';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags as SeoMetaTags } from '@/components/seo/SeoHead.svelte';
    import { router } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';

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

    interface PaginationMeta {
        current_page: number;
        from?: number;
        last_page: number;
        path?: string;
        per_page: number;
        to?: number;
        total: number;
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

    // Use the custom hook for filter logic
    const { updateFilters, toggleFilter, clearFilters, hasActiveFilters, buildActiveFilterChips } = useGameFilters({
        getCurrentFilters: () => currentFilters,
        getFilters: () => filters,
        onGamesPage: true,
    });

    // Use the platform icons hook
    const { getPlatformIcon: getTypedPlatformIcon } = usePlatformIcons();
    const getPlatformIcon = (platform: string) => {
        return getTypedPlatformIcon(platform as GameCardPlatform);
    };

    // Use the store platform icons hook
    const { getStorePlatformIcon: getTypedStorePlatformIcon } = useStorePlatformIcons();
    const getStorePlatformIcon = (platform: string) => {
        return getTypedStorePlatformIcon(platform as StorePlatform);
    };

    // Handle ignore toggle callback
    const handleIgnoreToggle = (gameId: number, isIgnored: boolean, newIgnoredGameIds: number[]) => {
        localIgnoredGameIds = newIgnoredGameIds;
    };

    let isRandomLoading = $state(false);

    // Navigate to a random game
    const handleRandomGame = async () => {
        isRandomLoading = true;
        try {
            const response = await fetch(route('games.random'));
            const data = await response.json();
            if (data.slug) {
                router.visit(route('games.show', { game: data.slug }));
            }
        } catch {
            // Silently fail
        } finally {
            isRandomLoading = false;
        }
    };

    // Normalize pagination meta
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

<!-- Filter Modal -->
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

    <!-- Info Bar -->
    <Card variant="glass" padding="none" class="-mt-2 px-4 py-3 shadow-none">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2 lg:flex-1">
                <ActiveFilterChips chips={buildActiveFilterChips()} onClearAll={clearFilters} {getPlatformIcon} {getStorePlatformIcon} />
            </div>

            <div class="hidden h-6 w-px bg-gray-300 lg:block dark:bg-gray-600"></div>

            <SortControls
                currentSort={currentFilters.sort || ''}
                currentDirection={currentFilters.direction === 'asc' || currentFilters.direction === 'desc' ? currentFilters.direction : 'desc'}
                sortOptions={filters.sortOptions || {}}
                onSortChange={(sort) => updateFilters({ sort })}
                onDirectionChange={(direction) => updateFilters({ direction })}
                hasSearch={Boolean(currentFilters.search?.trim())}
            />

            <div class="hidden h-6 w-px bg-gray-300 lg:block dark:bg-gray-600"></div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    tone="warning"
                    size="sm"
                    onclick={handleRandomGame}
                    disabled={isRandomLoading}
                    loading={isRandomLoading}
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-3 py-1 text-sm text-amber-800 transition-colors hover:bg-amber-100 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-500 dark:bg-amber-900/30 dark:text-amber-300 dark:hover:bg-amber-900/50"
                >
                    <svg
                        class="h-3.5 w-3.5 {isRandomLoading ? 'animate-spin' : ''}"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width={2}
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>
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
                    class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    aria-expanded={showFilters}
                    aria-controls="filter-modal"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width={2}
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                        />
                    </svg>
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

    <!-- Default Language Preferences Info Bar -->
    {#if currentFilters.usingDefaultLanguages}
        <div
            class="flex items-center justify-between rounded-lg border border-indigo-300 bg-indigo-50 p-3 dark:border-indigo-600 dark:bg-indigo-900/30"
        >
            <div class="flex items-center gap-2 text-sm text-indigo-700 dark:text-indigo-300">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span>Showing games in your preferred languages.</span>
            </div>
            <Button
                type="button"
                variant="solid"
                tone="info"
                size="sm"
                onclick={() => updateFilters({ selectedLanguages: [], noDefaults: true })}
                class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-medium text-white transition-colors hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
            >
                Show all
            </Button>
        </div>
    {/if}

    <!-- Ignored Items Info Bar -->
    {#if ignoredCount > 0 && !currentFilters.showIgnored}
        <div class="mb-4 flex items-center justify-between rounded-lg border border-gray-300 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700">
            <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        fill-rule="evenodd"
                        d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span>
                    <strong>{ignoredCount}</strong>
                    {ignoredCount === 1 ? 'game' : 'games'} hidden from results
                </span>
            </div>
            <Button
                type="button"
                variant="solid"
                tone="primary"
                size="sm"
                onclick={() => updateFilters({ showIgnored: true })}
                class="rounded-md bg-blue-600 px-3 py-1 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
            >
                Show Ignored
            </Button>
        </div>
    {/if}

    <!-- Show Ignored Toggle -->
    {#if currentFilters.showIgnored}
        <div class="mb-4 flex items-center justify-between rounded-lg border border-blue-300 bg-blue-50 p-3 dark:border-blue-600 dark:bg-blue-900/30">
            <div class="flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span>Showing ignored games</span>
            </div>
            <Button
                type="button"
                variant="solid"
                tone="neutral"
                size="sm"
                onclick={() => updateFilters({ showIgnored: false })}
                class="rounded-md bg-gray-600 px-3 py-1 text-sm font-medium text-white transition-colors hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600"
            >
                Hide Ignored
            </Button>
        </div>
    {/if}

    <!-- Games Grid -->
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

    <!-- Pagination Controls -->
    <PaginationControls meta={gamesMeta} {currentFilters} {updateFilters} />
</div>
