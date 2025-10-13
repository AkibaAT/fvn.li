import AdvancedPagination from '@/components/advanced-pagination';
import type {VnList} from '@/components/vn-list-card';
import VnListCard from '@/components/vn-list-card';
import {Head, Link, router} from '@inertiajs/react';
import React, {useEffect, useState} from 'react';

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

    // Sync local state when props change (from navigation)
    useEffect(() => {
        setLocalLists(lists.data);
        setLocalCounts(counts);
    }, [lists.data, counts]);

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
        params.set('per_page', lists.per_page.toString());
        params.set('page', page.toString());
        return `/lists/public?${params.toString()}`;
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
