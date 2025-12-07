import AdvancedPagination from '@/components/advanced-pagination';
import type {VnList} from '@/components/vn-list-card';
import VnListCard from '@/components/vn-list-card';
import {Head, Link, router} from '@inertiajs/react';
import React, {useEffect, useState} from 'react';

interface FilterGame {
    id: number;
    name: string;
    slug: string;
}

interface PublicListsProps {
    lists: {
        data: VnList[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    metaTags?: {
        title?: string;
        description?: string;
    };
    type?: string;
    search?: string;
    sort?: string;
    filterGame?: FilterGame | null;
    counts?: {
        all: number;
        plan_to_read: number;
        reading: number;
        completed: number;
        on_hold: number;
        dropped: number;
        custom: number;
    };
}


export default function PublicLists({
                                        lists,
                                        metaTags,
                                        type = 'all',
                                        search: initialSearch = '',
                                        sort: initialSort = 'default',
                                        filterGame = null,
                                        counts = {
                                            all: 0,
                                            plan_to_read: 0,
                                            reading: 0,
                                            completed: 0,
                                            on_hold: 0,
                                            dropped: 0,
                                            custom: 0,
                                        },
                                    }: PublicListsProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [localLists, setLocalLists] = useState(lists.data);
    const [localCounts, setLocalCounts] = useState(counts);
    const [searchInput, setSearchInput] = useState(initialSearch);
    const [currentSearch, setCurrentSearch] = useState(initialSearch);
    const [currentSort, setCurrentSort] = useState(initialSort);

    // Sync local state when props change (from navigation)
    useEffect(() => {
        setLocalLists(lists.data);
        setLocalCounts(counts);
        setSearchInput(initialSearch);
        setCurrentSearch(initialSearch);
        setCurrentSort(initialSort);
    }, [lists.data, counts, initialSearch, initialSort]);

    const typeLabel = (t: string) =>
        t.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());

    const handleTabChange = (newType: string) => {
        setIsLoading(true);
        router.get(
            route('lists.public'),
            {
                type: newType,
                per_page: lists.per_page,
                page: 1,
                search: currentSearch || undefined,
                sort: currentSort !== 'default' ? currentSort : undefined,
                game: filterGame?.id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handlePageChange = (page: number) => {
        setIsLoading(true);
        router.get(
            route('lists.public'),
            {
                type: type,
                per_page: lists.per_page,
                page: page,
                search: currentSearch || undefined,
                sort: currentSort !== 'default' ? currentSort : undefined,
                game: filterGame?.id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handlePerPageChange = (perPage: number) => {
        setIsLoading(true);
        router.get(
            route('lists.public'),
            {
                type: type,
                per_page: perPage,
                page: 1,
                search: currentSearch || undefined,
                sort: currentSort !== 'default' ? currentSort : undefined,
                game: filterGame?.id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        setCurrentSearch(searchInput);
        router.get(
            route('lists.public'),
            {
                type: type,
                per_page: lists.per_page,
                page: 1,
                search: searchInput || undefined,
                sort: currentSort !== 'default' ? currentSort : undefined,
                game: filterGame?.id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleSortChange = (newSort: string) => {
        setIsLoading(true);
        setCurrentSort(newSort);
        router.get(
            route('lists.public'),
            {
                type: type,
                per_page: lists.per_page,
                page: 1,
                search: currentSearch || undefined,
                sort: newSort !== 'default' ? newSort : undefined,
                game: filterGame?.id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const clearSearch = () => {
        setSearchInput('');
        setCurrentSearch('');
        setIsLoading(true);
        router.get(
            route('lists.public'),
            {
                type: type,
                per_page: lists.per_page,
                page: 1,
                sort: currentSort !== 'default' ? currentSort : undefined,
                game: filterGame?.id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    // Build SSR-friendly URLs for pagination
    const buildPageUrl = (page: number): string => {
        const params = new URLSearchParams();
        if (type && type !== 'all') params.set('type', type);
        if (currentSearch) params.set('search', currentSearch);
        if (currentSort && currentSort !== 'default') params.set('sort', currentSort);
        if (filterGame?.id) params.set('game', filterGame.id.toString());
        params.set('per_page', lists.per_page.toString());
        params.set('page', page.toString());
        return `/lists/public?${params.toString()}`;
    };

    const clearGameFilter = () => {
        setIsLoading(true);
        router.get(
            route('lists.public'),
            {
                type: type,
                per_page: lists.per_page,
                page: 1,
                search: currentSearch || undefined,
                sort: currentSort !== 'default' ? currentSort : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    return (
        <>
            <Head title={metaTags?.title || 'Public Visual Novel Lists'}/>

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-blue-600">
                            Public Visual Novel Lists
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Discover and explore visual novel lists shared by
                            the community
                        </p>
                    </div>
                    <div className="mt-4 flex space-x-3 sm:mt-0">
                        <Link
                            href={route('lists.index')}
                            className="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
                        >
                            <svg
                                className="mr-2 h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                            My Lists
                        </Link>
                    </div>
                </div>

                {/* Search and Sort Controls */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl bg-white/70 p-4 shadow-lg backdrop-blur-xl dark:bg-gray-800/70">
                    {/* Search Form */}
                    <form onSubmit={handleSearch} className="flex flex-1 gap-2 max-w-md">
                        <div className="relative flex-1">
                            <input
                                type="text"
                                value={searchInput}
                                onChange={(e) => setSearchInput(e.target.value)}
                                placeholder="Search by user or VN name..."
                                className="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 text-sm text-gray-900 placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                            />
                            <svg
                                className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                            {currentSearch && (
                                <button
                                    type="button"
                                    onClick={clearSearch}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={isLoading}
                            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                        >
                            Search
                        </button>
                    </form>

                    {/* Sort Dropdown */}
                    <div className="flex items-center gap-2">
                        <label htmlFor="sort" className="text-sm text-gray-600 dark:text-gray-400">
                            Sort by:
                        </label>
                        <select
                            id="sort"
                            value={currentSort}
                            onChange={(e) => handleSortChange(e.target.value)}
                            disabled={isLoading}
                            className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        >
                            <option value="default">Default</option>
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="most_entries">Most Games</option>
                            <option value="recently_updated">Recently Updated</option>
                        </select>
                    </div>
                </div>

                {/* Active Search Indicator */}
                {currentSearch && (
                    <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <span>Showing results for:</span>
                        <span className="rounded-full bg-blue-100 px-3 py-1 font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            "{currentSearch}"
                        </span>
                        <button
                            onClick={clearSearch}
                            className="text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Clear
                        </button>
                    </div>
                )}

                {/* Game Filter Indicator */}
                {filterGame && (
                    <div className="flex items-center gap-2 rounded-lg bg-purple-50 p-3 text-sm dark:bg-purple-900/20">
                        <svg className="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span className="text-gray-700 dark:text-gray-300">
                            Showing lists containing:
                        </span>
                        <Link
                            href={route('games.show', filterGame.slug)}
                            className="font-medium text-purple-700 hover:underline dark:text-purple-300"
                        >
                            {filterGame.name}
                        </Link>
                        <button
                            onClick={clearGameFilter}
                            className="ml-auto rounded-full p-1 text-purple-600 hover:bg-purple-100 dark:text-purple-400 dark:hover:bg-purple-900/30"
                            title="Clear filter"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                )}

                {/* Tabbed View */}
                <div className="rounded-xl bg-white/70 p-6 shadow-lg backdrop-blur-xl dark:bg-gray-800/70">
                    <div className="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
                        {[
                            {
                                key: 'all',
                                label: 'All Lists',
                                count: localCounts.all,
                            },
                            {
                                key: 'plan_to_read',
                                label: typeLabel('plan_to_read'),
                                count: localCounts.plan_to_read,
                            },
                            {
                                key: 'reading',
                                label: typeLabel('reading'),
                                count: localCounts.reading,
                            },
                            {
                                key: 'completed',
                                label: typeLabel('completed'),
                                count: localCounts.completed,
                            },
                            {
                                key: 'on_hold',
                                label: typeLabel('on_hold'),
                                count: localCounts.on_hold,
                            },
                            {
                                key: 'dropped',
                                label: typeLabel('dropped'),
                                count: localCounts.dropped,
                            },
                            {
                                key: 'custom',
                                label: 'Custom',
                                count: localCounts.custom,
                            },
                        ].map((tab) => (
                            <Link
                                key={tab.key}
                                href={route('lists.public', tab.key === 'all' ? {} : { type: tab.key })}
                                onClick={(e) => {
                                    e.preventDefault();
                                    handleTabChange(tab.key);
                                }}
                                className={`rounded-t-lg px-4 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-blue-500 ${
                                    type === tab.key
                                        ? 'border-b-2 border-blue-600 bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200'
                                }`}
                            >
                                {tab.label} ({tab.count})
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Lists Grid */}
                {localLists.length > 0 ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {localLists.map((list) => (
                            <VnListCard key={list.id} list={list} showUser={true}/>
                        ))}
                    </div>
                ) : (
                    <div className="py-12 text-center">
                        <div
                            className="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                            <svg
                                className="h-12 w-12 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                />
                            </svg>
                        </div>
                        <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                            No public lists found
                        </h3>
                        <p className="text-gray-600 dark:text-gray-400">
                            There are no public lists available for this
                            category.
                        </p>
                    </div>
                )}

                <AdvancedPagination
                    meta={{
                        current_page: lists.current_page,
                        last_page: lists.last_page,
                        total: lists.total,
                        from: localLists.length
                            ? (lists.current_page - 1) * lists.per_page + 1
                            : 0,
                        to: localLists.length
                            ? (lists.current_page - 1) * lists.per_page + localLists.length
                            : 0,
                        per_page: lists.per_page,
                    }}
                    onPageChange={handlePageChange}
                    onPerPageChange={handlePerPageChange}
                    isLoading={isLoading}
                    label="results"
                    perPageOptions={[8, 16, 24, 32]}
                    buildPageUrl={buildPageUrl}
                />
            </div>
        </>
    );
}
