import AdvancedPagination from '@/components/advanced-pagination';
import type {User, VnList} from '@/components/vn-list-card';
import VnListCard from '@/components/vn-list-card';
import {Head, Link, router} from '@inertiajs/react';
import React, {useState} from 'react';

interface UserPublicListsProps {
    lists: {
        data: VnList[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    user: User;
    metaTags?: {
        title?: string;
        description?: string;
    };
}

export default function UserPublicLists({
                                            lists,
                                            user,
                                            metaTags,
                                        }: UserPublicListsProps) {
    const [isLoading, setIsLoading] = useState(false);

    const handlePageChange = (page: number) => {
        setIsLoading(true);
        router.get(
            route('lists.user-public', {
                user: user.id,
                page,
            }),
            {},
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
            route('lists.user-public', user.id),
            {
                per_page: perPage,
                page: 1,
            },
            {
                preserveState: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    // Build SSR-friendly URLs for pagination
    const buildPageUrl = (page: number): string => {
        const params = new URLSearchParams();
        params.set('per_page', lists.per_page.toString());
        params.set('page', page.toString());
        return `/lists/user/${user.id}?${params.toString()}`;
    };

    return (
        <>
            <Head
                title={metaTags?.title || `${user.name}'s Visual Novel Lists`}
            />

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-[var(--color-ui-text)]">
                            {user.name}'s Visual Novel Lists
                        </h1>
                        <p className="mt-2 text-[var(--color-ui-text-muted)]">
                            Browse {user.name}'s public visual novel collections
                        </p>
                    </div>
                    <div className="mt-4 flex space-x-3 sm:mt-0">
                        <Link
                            href={route('lists.public')}
                            className="inline-flex items-center rounded-lg bg-[var(--color-brand-primary)] px-4 py-2 font-medium text-white transition-colors hover:bg-[var(--color-brand-primary-dark)]"
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
                            All Public Lists
                        </Link>
                        <Link
                            href={route('lists.index')}
                            className="inline-flex items-center rounded-lg bg-[var(--color-brand-primary)] px-4 py-2 font-medium text-white transition-colors hover:bg-[var(--color-brand-primary-dark)]"
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


                {/* Lists Grid */}
                {lists.data.length > 0 ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {lists.data.map((list) => (
                            <VnListCard key={list.id} list={list}/>
                        ))}
                    </div>
                ) : (
                    <div className="py-12 text-center">
                        <div
                            className="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-full bg-[var(--color-ui-surface-alt)]">
                            <svg
                                className="h-12 w-12 text-[var(--color-ui-text-muted)]"
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
                        <h3 className="mb-2 text-lg font-medium text-[var(--color-ui-text)]">
                            No public lists found
                        </h3>
                        <p className="text-[var(--color-ui-text-muted)]">
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
