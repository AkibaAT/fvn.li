import AdvancedPagination from '@/components/advanced-pagination';
import type {VnList} from '@/components/vn-list-card';
import VnListCard from '@/components/vn-list-card';
import {Head, Link, router} from '@inertiajs/react';
import React, {useEffect, useState} from 'react';
import {toast} from '@/utils/toast';

interface ListsIndexProps {
    lists: {
        data: VnList[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    visibility: string;
    metaTags?: {
        title?: string;
        description?: string;
    };
    counts?: {
        all: number;
        public: number;
        private: number;
    };
}


export default function ListsIndex({
                                       lists,
                                       visibility,
                                       metaTags,
                                       counts = {
                                           all: 0,
                                           public: 0,
                                           private: 0,
                                       },
                                   }: ListsIndexProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [localLists, setLocalLists] = useState(lists.data);
    const [localCounts, setLocalCounts] = useState(counts);

    // Sync local state when props change (from navigation)
    useEffect(() => {
        setLocalLists(lists.data);
        setLocalCounts(counts);
    }, [lists.data, counts]);

    const handleTabChange = (newVisibility: string) => {
        setIsLoading(true);
        router.get(
            route('lists.index'),
            {
                visibility: newVisibility === 'all' ? undefined : newVisibility,
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
            route('lists.index'),
            {
                visibility: visibility === 'all' ? undefined : visibility,
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
            route('lists.index'),
            {
                visibility: visibility === 'all' ? undefined : visibility,
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
        if (visibility && visibility !== 'all') params.set('visibility', visibility);
        params.set('per_page', lists.per_page.toString());
        params.set('page', page.toString());
        return `/lists?${params.toString()}`;
    };

    const handleToggleVisibility = async (list: VnList) => {
        // Optimistic update
        const newIsPublic = !list.is_public;
        const updatedLists = localLists.map(l =>
            l.id === list.id ? {...l, is_public: newIsPublic} : l
        );
        setLocalLists(updatedLists);

        // Update counts optimistically
        const newCounts = {...localCounts};
        if (newIsPublic) {
            newCounts.public += 1;
            newCounts.private -= 1;
        } else {
            newCounts.public -= 1;
            newCounts.private += 1;
        }
        setLocalCounts(newCounts);

        try {
            const response = await fetch(route('api.vn-lists.toggle-visibility', list.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to toggle visibility');
            }

            const data = await response.json();
            toast.success(data.message);
        } catch (error) {
            // Revert optimistic update on error
            setLocalLists(localLists);
            setLocalCounts(localCounts);
            toast.error('Failed to update list visibility');
            console.error('Error toggling visibility:', error);
        }
    };
    return (
        <>
            <Head title={metaTags?.title || 'Your Visual Novel Lists'}/>

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-blue-600">
                            Your Visual Novel Lists
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Organize and manage your visual novel collections
                        </p>
                    </div>
                    <div className="mt-4 flex space-x-3 sm:mt-0">
                        <Link
                            href={route('lists.public')}
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
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                />
                            </svg>
                            Public Lists
                        </Link>
                        <Link
                            href={route('lists.create')}
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
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                />
                            </svg>
                            New List
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
                                key: 'public',
                                label: 'Public Lists',
                                count: localCounts.public,
                            },
                            {
                                key: 'private',
                                label: 'Private Lists',
                                count: localCounts.private,
                            },
                        ].map((tab) => (
                            <Link
                                key={tab.key}
                                href={route('lists.index', tab.key === 'all' ? {} : { visibility: tab.key })}
                                onClick={(e) => {
                                    e.preventDefault();
                                    handleTabChange(tab.key);
                                }}
                                className={`rounded-t-lg px-4 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-blue-500 ${
                                    visibility === tab.key
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
                            <VnListCard
                                key={list.id}
                                list={list}
                                isOwner={true}
                                showActions={true}
                                onToggleVisibility={handleToggleVisibility}
                                onDelete={(list) => {
                                    router.delete(route('api.vn-lists.destroy', list.id), {
                                        onSuccess: () => {
                                            router.reload();
                                        },
                                    });
                                }}
                            />
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
                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                        </div>
                        <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                            No lists found
                        </h3>
                        <p className="mb-6 text-gray-600 dark:text-gray-400">
                            {visibility === 'all'
                                ? "You haven't created any lists yet."
                                : `No ${visibility} lists found.`}
                        </p>
                        <Link
                            href={route('lists.create')}
                            className="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 font-medium text-white transition-colors hover:bg-blue-700"
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
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                />
                            </svg>
                            Create Your First List
                        </Link>
                    </div>
                )}

                <AdvancedPagination
                    meta={{
                        current_page: lists.current_page,
                        last_page: lists.last_page,
                        total: lists.total,
                        from: lists.data.length
                            ? (lists.current_page - 1) * lists.per_page + 1
                            : 0,
                        to: lists.data.length
                            ? (lists.current_page - 1) * lists.per_page + lists.data.length
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
