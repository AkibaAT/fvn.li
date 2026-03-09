import React, {useState, useCallback} from 'react';
import {FilterModal} from '@/components/filter-modal';
import {useGameFilters} from '@/hooks/useGameFilters';
import {usePlatformIcons, type GameCardPlatform} from '@/hooks/usePlatformIcons';
import {useStorePlatformIcons, type StorePlatform} from '@/hooks/useStorePlatformIcons';
import ActiveFilterChips from '@/components/games/ActiveFilterChips';
import SortControls from '@/components/games/SortControls';
import GamesGrid from '@/components/games/GamesGrid';
import PaginationControls from '@/components/games/PaginationControls';
import type {CurrentFilters, FilterOptions} from '@/types';
import SeoHead, {type MetaTags as SeoMetaTags} from '@/components/seo/SeoHead';
import {router} from '@inertiajs/react';

// No-op placeholder: Collapsible sections were removed; keep UI flat for now


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

export default function GamesIndex({
    games,
    filters,
    currentFilters,
    metaTags,
    ignoredCount = 0,
    ignoredGameIds = [],
}: GamesIndexProps) {
    const [showFilters, setShowFilters] = useState(false);
    const [showSort] = useState(false);
    const [localIgnoredGameIds, setLocalIgnoredGameIds] = useState<number[]>(ignoredGameIds);

    // Use the custom hook for filter logic
    const {
        updateFilters,
        toggleFilter,
        clearFilters,
        hasActiveFilters,
        buildActiveFilterChips,
    } = useGameFilters({
        currentFilters,
        filters,
        onGamesPage: true,
    });

    // Use the platform icons hook
    const {getPlatformIcon: getTypedPlatformIcon} = usePlatformIcons();

    // Wrapper function to match the expected interface
    const getPlatformIcon = (platform: string) => {
        return getTypedPlatformIcon(platform as GameCardPlatform);
    };

    // Use the store platform icons hook
    const {getStorePlatformIcon: getTypedStorePlatformIcon} = useStorePlatformIcons();

    // Wrapper function for store platform icons
    const getStorePlatformIcon = (platform: string) => {
        return getTypedStorePlatformIcon(platform as StorePlatform);
    };

    // Handle ignore toggle callback
    const handleIgnoreToggle = (gameId: number, isIgnored: boolean, newIgnoredGameIds: number[]) => {
        setLocalIgnoredGameIds(newIgnoredGameIds);
    };

    const [isRandomLoading, setIsRandomLoading] = useState(false);

    // Navigate to a random game
    const handleRandomGame = useCallback(async () => {
        setIsRandomLoading(true);
        try {
            const response = await fetch(route('games.random'));
            const data = await response.json();
            if (data.slug) {
                router.visit(route('games.show', {game: data.slug}));
            }
        } catch {
            // Silently fail - the button will just stop loading
        } finally {
            setIsRandomLoading(false);
        }
    }, []);

    // Normalize pagination meta in case backend shape varies or meta is missing
    const resolveGamesMeta = () => {
        const rawMeta: PaginationMeta =
            (games as GamesIndexProps['games'])?.meta || {};
        const rawTop = games as unknown as {
            total?: number;
            current_page?: number;
            last_page?: number;
        };
        const perPage =
            Number(rawMeta.per_page ?? currentFilters.perPage ?? 8) || 8;
        const total =
            Number(rawMeta.total ?? rawTop.total ?? games?.data?.length ?? 0) ||
            0;
        const current =
            Number(rawMeta.current_page ?? rawTop.current_page ?? 1) || 1;
        const last =
            Number(
                rawMeta.last_page ??
                rawTop.last_page ??
                Math.max(1, Math.ceil(total / perPage)),
            ) || 1;
        const from = Number(
            rawMeta.from ?? (total > 0 ? (current - 1) * perPage + 1 : 0),
        );
        const to = Number(
            rawMeta.to ?? (total > 0 ? Math.min(current * perPage, total) : 0),
        );
        return {
            current_page: current,
            last_page: last,
            total,
            from,
            to,
            per_page: perPage,
        };
    };
    const gamesMeta = resolveGamesMeta();

    const getActiveFilterCount = () => {
        return buildActiveFilterChips().length;
    };

    const getChipColorClass = (type?: string) => {
        switch (type) {
            case 'search':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            case 'status':
                return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
            case 'platform':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            case 'language':
                return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300';
            case 'engine':
                return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300';
            case 'tag':
                return 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300';
            case 'gameJam':
                return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
            case 'sfw':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            case 'nsfw':
                return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
            case 'free':
                return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
            case 'paid':
                return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
            case 'demo':
                return 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300';
            case 'sale':
                return 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300';
            case 'excludeTag':
                return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
            case 'readingTime':
                return 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300';
            case 'delisted':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
            case 'hidden':
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
        }
    };

    return (
        <>
            <SeoHead metaTags={metaTags} />

            {/* Filter Modal - rendered outside main content flow */}
            <FilterModal
                isOpen={showFilters}
                onClose={() => setShowFilters(false)}
                filters={filters}
                currentFilters={currentFilters}
                onGamesPage={true}
            />

            <div className="space-y-8">
                {/* Page Heading - visually hidden but accessible to screen readers */}
                <h1 style={{
                    position: 'absolute',
                    width: '1px',
                    height: '1px',
                    padding: 0,
                    margin: '-1px',
                    overflow: 'hidden',
                    clip: 'rect(0, 0, 0, 0)',
                    whiteSpace: 'nowrap',
                    borderWidth: 0
                }}>Browse Visual Novels</h1>

                {/* Info Bar: Active Filters + Sorting + Filters */}
                <div
                    className="-mt-2 rounded-xl border border-gray-200/50 bg-white/70 px-4 py-3 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        {/* Section 1: Active Filters Display */}
                        <div className="flex flex-wrap items-center gap-2 lg:flex-1">
                            <ActiveFilterChips
                                chips={buildActiveFilterChips()}
                                onClearAll={clearFilters}
                                getChipColorClass={getChipColorClass}
                                getPlatformIcon={getPlatformIcon}
                                getStorePlatformIcon={getStorePlatformIcon}
                            />
                        </div>

                        {/* Visual Separator */}
                        <div className="hidden lg:block h-6 w-px bg-gray-300 dark:bg-gray-600"></div>

                        {/* Section 2: Sort Controls */}
                        <SortControls
                            currentSort={currentFilters.sort || ''}
                            currentDirection={currentFilters.direction === 'asc' || currentFilters.direction === 'desc' ? currentFilters.direction : 'desc'}
                            sortOptions={filters.sortOptions || {}}
                            onSortChange={(sort) => updateFilters({sort})}
                            onDirectionChange={(direction) => updateFilters({direction})}
                            hasSearch={Boolean(currentFilters.search?.trim())}
                        />

                        {/* Visual Separator */}
                        <div className="hidden lg:block h-6 w-px bg-gray-300 dark:bg-gray-600"></div>

                        {/* Section 3: Quick Filters + Filters Button */}
                        <div className="flex items-center gap-2 flex-wrap">
                            {/* Random Game Button */}
                            <button
                                onClick={handleRandomGame}
                                disabled={isRandomLoading}
                                className="cursor-pointer inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-3 py-1 text-sm text-amber-800 transition-colors hover:bg-amber-100 dark:border-amber-500 dark:bg-amber-900/30 dark:text-amber-300 dark:hover:bg-amber-900/50 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg className={`h-3.5 w-3.5 ${isRandomLoading ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {isRandomLoading ? 'Loading...' : "I'm Feeling Lucky"}
                            </button>

                            {/* Filters Button */}
                            <button
                                onClick={() => setShowFilters(!showFilters)}
                                className="cursor-pointer inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                aria-expanded={showFilters}
                                aria-controls="filter-modal"
                            >
                                <svg
                                    className="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                                    />
                                </svg>
                                Filters
                                {hasActiveFilters() && (
                                    <span
                                        className="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-blue-600 text-xs text-white">
                                        {getActiveFilterCount()}
                                    </span>
                                )}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Sort Panel */}
                {showSort && (
                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                            Sort Options
                        </h3>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {Object.entries(filters.sortOptions ?? {}).map(
                                ([value, label]) => {
                                    const defaultSort = currentFilters.search?.trim() ? 'relevance' : 'first_visible_at';
                                    const isChecked = currentFilters.sort === value || (!currentFilters.sort && value === defaultSort);

                                    return (
                                        <label
                                            key={value}
                                            className="flex items-center"
                                        >
                                            <input
                                                type="radio"
                                                name="sort"
                                                value={value}
                                                checked={isChecked}
                                                onChange={() =>
                                                    updateFilters({sort: value})
                                                }
                                                className="border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                {label}
                                            </span>
                                        </label>
                                    );
                                },
                            )}
                        </div>

                        <div className="mt-4">
                            <h4 className="mb-2 font-medium text-gray-900 dark:text-white">
                                Direction
                            </h4>
                            <div className="flex gap-4">
                                <label className="flex items-center">
                                    <input
                                        type="radio"
                                        name="direction"
                                        value="desc"
                                        checked={
                                            currentFilters.direction === 'desc'
                                        }
                                        onChange={() =>
                                            updateFilters({direction: 'desc'})
                                        }
                                        className="border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Descending
                                    </span>
                                </label>
                                <label className="flex items-center">
                                    <input
                                        type="radio"
                                        name="direction"
                                        value="asc"
                                        checked={
                                            currentFilters.direction === 'asc'
                                        }
                                        onChange={() =>
                                            updateFilters({direction: 'asc'})
                                        }
                                        className="border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Ascending
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                )}

                {/* Default Language Preferences Info Bar */}
                {currentFilters.usingDefaultLanguages && (
                    <div className="flex items-center justify-between rounded-lg border border-indigo-300 bg-indigo-50 p-3 dark:border-indigo-600 dark:bg-indigo-900/30">
                        <div className="flex items-center gap-2 text-sm text-indigo-700 dark:text-indigo-300">
                            <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                            <span>Showing games in your preferred languages.</span>
                        </div>
                        <button
                            onClick={() => updateFilters({selectedLanguages: [], noDefaults: true})}
                            className="rounded-md bg-indigo-600 px-3 py-1 text-sm font-medium text-white transition-colors hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                        >
                            Show all
                        </button>
                    </div>
                )}

                {/* Default Excluded Tags Info Bar */}
                {currentFilters.usingDefaultExcludedTags && (
                    <div className="flex items-center justify-between rounded-lg border border-red-300 bg-red-50 p-3 dark:border-red-600 dark:bg-red-900/30">
                        <div className="flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
                            <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                            <span>Hiding games with your excluded tags.</span>
                        </div>
                        <button
                            onClick={() => updateFilters({excludedTags: [], noDefaults: true})}
                            className="rounded-md bg-red-600 px-3 py-1 text-sm font-medium text-white transition-colors hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                        >
                            Show all
                        </button>
                    </div>
                )}

                {/* Ignored Items Info Bar */}
                {ignoredCount > 0 && !currentFilters.showIgnored && (
                    <div className="mb-4 flex items-center justify-between rounded-lg border border-gray-300 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700">
                        <div className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <svg className="h-5 w-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clipRule="evenodd" />
                            </svg>
                            <span>
                                <strong>{ignoredCount}</strong> {ignoredCount === 1 ? 'game' : 'games'} hidden from results
                            </span>
                        </div>
                        <button
                            onClick={() => updateFilters({showIgnored: true})}
                            className="rounded-md bg-blue-600 px-3 py-1 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                        >
                            Show Ignored
                        </button>
                    </div>
                )}

                {/* Show Ignored Toggle (when showing ignored) */}
                {currentFilters.showIgnored && (
                    <div className="mb-4 flex items-center justify-between rounded-lg border border-blue-300 bg-blue-50 p-3 dark:border-blue-600 dark:bg-blue-900/30">
                        <div className="flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                            <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                            <span>
                                Showing ignored games
                            </span>
                        </div>
                        <button
                            onClick={() => updateFilters({showIgnored: false})}
                            className="rounded-md bg-gray-600 px-3 py-1 text-sm font-medium text-white transition-colors hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600"
                        >
                            Hide Ignored
                        </button>
                    </div>
                )}

                {/* Games Grid */}
                <GamesGrid
                    games={games.data as GamesIndexGame[]}
                    currentFilters={currentFilters}
                    ignoredGameIds={localIgnoredGameIds}
                    onPlatformClick={(p) => toggleFilter('platform', p)}
                    onLanguageClick={(iso) => toggleFilter('language', iso)}
                    onTagClick={(tagId) => toggleFilter('tag', tagId)}
                    onStatusClick={(status) => toggleFilter('status', status)}
                    onStorePlatformClick={(platform) => toggleFilter('storePlatform', platform)}
                    onNsfwToggle={() => updateFilters({nsfw: !currentFilters.nsfw})}
                    onPaidToggle={() => updateFilters({showPaid: !currentFilters.showPaid})}
                    onDemoToggle={() => updateFilters({showDemo: !currentFilters.showDemo})}
                    onSaleToggle={() => updateFilters({showSale: !currentFilters.showSale})}
                    onDelistedToggle={() => updateFilters({delisted: !currentFilters.delisted})}
                    updateFilters={updateFilters}
                    onIgnoreToggle={handleIgnoreToggle}
                />

                {/* Pagination Controls */}
                <PaginationControls
                    meta={gamesMeta}
                    currentFilters={currentFilters}
                    updateFilters={updateFilters}
                />
            </div>
        </>
    );
}
