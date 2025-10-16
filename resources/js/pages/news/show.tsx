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
    type: 'announcement' | 'update' | 'maintenance' | 'incident';
    is_published: boolean;
    published_at: string;
    author: Author;
    created_at: string;
    updated_at: string;
}

interface NewsShowProps {
    newsItem: NewsItem;
    metaTags?: {
        title?: string;
        description?: string;
        image?: string;
    };
}

export default function NewsShow({newsItem, metaTags}: NewsShowProps) {
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
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <>
            <Head title={metaTags?.title || newsItem.title}/>

            {/* Back Link */}
            <div className="mb-8">
                <Link
                    href="/news"
                    className="inline-flex items-center text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                >
                    <svg
                        className="mr-2 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    Back to News
                </Link>
            </div>

                {/* Article */}
                <article className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    <div className="p-8 sm:p-10 lg:p-12">
                        {/* Type Badge */}
                        <div className="mb-6 flex items-center gap-2">
                            <span
                                className={`inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium ${getTypeColor(newsItem.type)}`}
                            >
                                <span aria-hidden="true" className="text-base">
                                    {getTypeIcon(newsItem.type)}
                                </span>
                                {newsItem.type.charAt(0).toUpperCase() + newsItem.type.slice(1)}
                            </span>
                        </div>

                        {/* Title */}
                        <h1 className="mb-6 text-4xl font-bold leading-tight text-gray-900 dark:text-white sm:text-5xl">
                            {newsItem.title}
                        </h1>

                        {/* Meta Information */}
                        <div className="mb-10 flex items-center gap-4 border-b border-gray-200 pb-8 dark:border-gray-700">
                            <div className="flex items-center gap-3">
                                {newsItem.author.avatar ? (
                                    <img
                                        src={newsItem.author.avatar}
                                        alt={newsItem.author.name}
                                        className="h-12 w-12 rounded-full"
                                        referrerPolicy="no-referrer"
                                    />
                                ) : (
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 text-base font-medium text-white">
                                        {newsItem.author.name.charAt(0).toUpperCase()}
                                    </div>
                                )}
                                <div>
                                    <div className="text-base font-medium text-gray-900 dark:text-white">
                                        {newsItem.author.name}
                                    </div>
                                    <div className="text-sm text-gray-500 dark:text-gray-400">
                                        {formatDate(newsItem.published_at)}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Content */}
                        <div
                            className="news-content prose prose-lg max-w-none dark:prose-invert text-gray-700 dark:text-gray-300"
                            dangerouslySetInnerHTML={{__html: newsItem.content}}
                        />

                        {/* Updated At (if different from published) */}
                        {newsItem.updated_at !== newsItem.created_at && (
                            <div className="mt-12 border-t border-gray-200 pt-6 dark:border-gray-700">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Last updated: {formatDate(newsItem.updated_at)}
                                </p>
                            </div>
                        )}
                    </div>
                </article>

            {/* Back Link (Bottom) */}
            <div className="mt-8">
                <Link
                    href="/news"
                    className="inline-flex items-center text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                >
                    <svg
                        className="mr-2 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    Back to News
                </Link>
            </div>
        </>
    );
}

