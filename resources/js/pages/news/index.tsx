import {Head, Link} from '@inertiajs/react';
import React from 'react';

interface Author {
    id: number;
    name: string;
    avatar?: string;
}

interface NewsItem {
    id: number;
    title: string;
    slug: string;
    content: string;
    excerpt?: string;
    type: 'announcement' | 'update' | 'maintenance' | 'incident';
    is_published: boolean;
    published_at: string;
    author: Author;
    created_at: string;
    updated_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedNews {
    data: NewsItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface NewsIndexProps {
    news: PaginatedNews;
    metaTags?: {
        title?: string;
        description?: string;
        image?: string;
    };
}

export default function NewsIndex({news, metaTags}: NewsIndexProps) {
    const getTypeColor = (type: string) => {
        switch (type) {
            case 'announcement':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            case 'update':
                return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
            case 'maintenance':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
            case 'incident':
                return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
        }
    };

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'announcement':
                return '📢';
            case 'update':
                return '✨';
            case 'maintenance':
                return '🔧';
            case 'incident':
                return '⚠️';
            default:
                return '📰';
        }
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    return (
        <>
            <Head title={metaTags?.title || 'News & Announcements'}/>

            {/* Header */}
            <div className="mb-10">
                <h1 className="text-4xl font-bold text-gray-900 dark:text-white sm:text-5xl">
                    News & Announcements
                </h1>
                <p className="mt-3 text-lg text-gray-600 dark:text-gray-400">
                    Stay updated with the latest news and updates from FVN.li
                </p>
            </div>

                {/* News List */}
                {news.data.length === 0 ? (
                    <div className="rounded-lg bg-white p-12 text-center shadow-sm dark:bg-gray-800">
                        <p className="text-lg text-gray-600 dark:text-gray-400">
                            No news items available at this time.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-8">
                        {news.data.map((item) => (
                            <article
                                key={item.id}
                                className="overflow-hidden rounded-lg bg-white shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800"
                            >
                                <div className="p-8">
                                    {/* Type Badge */}
                                    <div className="mb-4 flex items-center gap-3">
                                        <span
                                            className={`inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium ${getTypeColor(item.type)}`}
                                        >
                                            <span aria-hidden="true" className="text-base">
                                                {getTypeIcon(item.type)}
                                            </span>
                                            {item.type.charAt(0).toUpperCase() + item.type.slice(1)}
                                        </span>
                                        <span className="text-sm text-gray-500 dark:text-gray-400">
                                            {formatDate(item.published_at)}
                                        </span>
                                    </div>

                                    {/* Title */}
                                    <h2 className="mb-4 text-3xl font-bold leading-tight text-gray-900 dark:text-white">
                                        <Link
                                            href={`/news/${item.slug}`}
                                            className="hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                        >
                                            {item.title}
                                        </Link>
                                    </h2>

                                    {/* Excerpt */}
                                    <div className="mb-6 text-base leading-relaxed text-gray-600 dark:text-gray-300">
                                        {item.excerpt || ''}
                                    </div>

                                    {/* Read More Link */}
                                    <Link
                                        href={`/news/${item.slug}`}
                                        className="inline-flex items-center font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                                    >
                                        Read more
                                        <svg
                                            className="ml-1 h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </Link>

                                    {/* Author */}
                                    <div className="mt-6 flex items-center gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
                                        {item.author.avatar ? (
                                            <img
                                                src={item.author.avatar}
                                                alt={item.author.name}
                                                className="h-10 w-10 rounded-full"
                                                referrerPolicy="no-referrer"
                                            />
                                        ) : (
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-medium text-white">
                                                {item.author.name.charAt(0).toUpperCase()}
                                            </div>
                                        )}
                                        <span className="text-sm text-gray-600 dark:text-gray-400">
                                            By {item.author.name}
                                        </span>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {news.last_page > 1 && (
                    <div className="mt-8 flex justify-center">
                        <nav className="inline-flex rounded-md shadow-sm" aria-label="Pagination">
                            {news.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url || '#'}
                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-medium ${
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                    } ${
                                        index === 0
                                            ? 'rounded-l-md'
                                            : index === news.links.length - 1
                                                ? 'rounded-r-md'
                                                : ''
                                    } border border-gray-300 dark:border-gray-600 ${
                                        !link.url ? 'cursor-not-allowed opacity-50' : ''
                                    }`}
                                    preserveScroll
                                    disabled={!link.url}
                                    dangerouslySetInnerHTML={{__html: link.label}}
                                />
                            ))}
                        </nav>
                    </div>
                )}
        </>
    );
}

