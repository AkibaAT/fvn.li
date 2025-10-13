import {Link} from '@inertiajs/react';
import React, {useState} from 'react';

export interface Game {
    id: number;
    name: string;
    slug: string;
    thumb_url?: string;
    optimized_thumbnails?: { default?: { path: string } } | null;
    is_nsfw: boolean;
    is_paid: boolean;
    has_demo: boolean;
    is_on_sale: boolean;
    min_price?: number;
}

export interface VnListEntry {
    id: number;
    game: Game;
    sort_order: number;
}

export interface User {
    id: number;
    name: string;
    avatar?: string;
}

export interface VnList {
    id: number;
    name: string;
    description?: string;
    type: string;
    is_default: boolean;
    is_public: boolean;
    created_at: string;
    updated_at?: string;
    entries: VnListEntry[];
    user: User;
}

export interface VnListCardProps {
    list: VnList;
    showUser?: boolean;
    showActions?: boolean;
    isOwner?: boolean;
    onToggleVisibility?: (list: VnList) => void | Promise<void>;
    onDelete?: (list: VnList) => void;
    className?: string;
}

// List Type Colors
const getListTypeColor = (type: string) => {
    switch (type) {
        case 'reading':
            return 'blue';
        case 'completed':
            return 'green';
        case 'plan_to_read':
            return 'yellow';
        case 'on_hold':
            return 'orange';
        case 'dropped':
            return 'red';
        default:
            return 'gray';
    }
};

export default function VnListCard({
                                       list,
                                       showUser = false,
                                       showActions = false,
                                       isOwner = false,
                                       onToggleVisibility,
                                       onDelete,
                                       className = '',
                                   }: VnListCardProps) {
    const color = getListTypeColor(list.type);
    const formatDate = (dateStr?: string) => {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };
    const getThumb = (game: Game) => {
        // Prefer optimized default thumbnail variant when available
        if (game.optimized_thumbnails?.default?.path) {
            // SSR-safe: use relative path instead of window.location.origin
            return `/storage/${game.optimized_thumbnails.default.path}`;
        }
        // Fallback to original thumb_url
        return game.thumb_url || '';
    };
    const [index, setIndex] = useState(0);
    const [isToggling, setIsToggling] = useState(false);
    const total = list.entries.length;
    const currentGame =
        total > 0 ? list.entries[index % total].game : undefined;
    const goPrev = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (total === 0) return;
        setIndex((prev) => (prev - 1 + total) % total);
    };
    const goNext = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (total === 0) return;
        setIndex((prev) => (prev + 1) % total);
    };

    const handleToggleVisibility = async () => {
        if (!onToggleVisibility) return;
        setIsToggling(true);
        try {
            await onToggleVisibility(list);
        } finally {
            setIsToggling(false);
        }
    };

    const handleDelete = () => {
        if (!onDelete) return;
        if (!confirm('Are you sure you want to delete this list?')) return;
        onDelete(list);
    };

    return (
        <div
            role="article"
            aria-labelledby={`list-title-${list.id}`}
            className={`overflow-hidden rounded-xl border-l-4 bg-white/70 shadow-lg backdrop-blur-xl dark:bg-gray-800/70 border-${color}-500 flex h-full flex-col transition-all duration-200 hover:shadow-xl ${className}`}
        >
            <div className="flex-grow p-6">
                <div className="mb-4 flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                        {showUser && list.user && (
                            <div className="mb-2 flex items-center gap-2">
                                <Link
                                    href={route('lists.user-public', list.user.id)}
                                    className="shrink-0"
                                >
                                    <div
                                        className="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        {list.user.avatar ? (
                                            <img
                                                src={list.user.avatar}
                                                alt={list.user.name}
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <span className="text-xs text-gray-600 dark:text-gray-300">
                                                {list.user.name
                                                    .charAt(0)
                                                    .toUpperCase()}
                                            </span>
                                        )}
                                    </div>
                                </Link>
                                <Link
                                    href={route('lists.user-public', list.user.id)}
                                    className="truncate text-sm text-gray-600 hover:underline dark:text-gray-300"
                                    title={list.user.name}
                                >
                                    {list.user.name}
                                </Link>
                            </div>
                        )}
                        <div className="mb-2 flex items-center gap-2">
                            {!list.is_default && (
                                <span
                                    className={`rounded-full px-2 py-0.5 text-[10px] font-semibold bg-${color}-100 text-${color}-800 dark:bg-${color}-900/20 dark:text-${color}-400 whitespace-nowrap`}
                                >
                                    {list.type
                                        .replace(/_/g, ' ')
                                        .replace(/\b\w/g, (l) =>
                                            l.toUpperCase(),
                                        )}
                                </span>
                            )}
                            {list.is_public && (
                                <span
                                    className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                                    Public
                                </span>
                            )}
                            {!list.is_public && (
                                <span
                                    className="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-orange-800 dark:bg-orange-900/20 dark:text-orange-400">
                                    Private
                                </span>
                            )}
                        </div>
                        <h2
                            id={`list-title-${list.id}`}
                            className="mb-2 truncate text-xl font-semibold text-gray-900 dark:text-white"
                        >
                            <Link
                                href={route('lists.show', list.id)}
                                className="transition-colors hover:text-blue-600 dark:hover:text-blue-400"
                                title={list.name}
                            >
                                {list.name}
                            </Link>
                        </h2>
                        <div className="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            {list.entries.length}{' '}
                            {list.entries.length === 1 ? 'game' : 'games'} ·
                            Updated{' '}
                            {formatDate(list.updated_at || list.created_at)}
                        </div>
                        <p className="line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                            {list.description || 'No description available.'}
                        </p>
                    </div>
                </div>

                {currentGame && (
                    <div
                        className="group relative mt-4"
                        role="group"
                        aria-roledescription="carousel"
                        aria-label={`Games in ${list.name}`}
                        tabIndex={0}
                        onKeyDown={(e) => {
                            if (e.key === 'ArrowLeft') {
                                goPrev(e as unknown as React.MouseEvent);
                            }
                            if (e.key === 'ArrowRight') {
                                goNext(e as unknown as React.MouseEvent);
                            }
                            if (e.key === 'Home') {
                                e.preventDefault();
                                e.stopPropagation();
                                setIndex(0);
                            }
                            if (e.key === 'End') {
                                e.preventDefault();
                                e.stopPropagation();
                                setIndex(total - 1);
                            }
                        }}
                    >
                        <span className="sr-only" aria-live="polite">
                            {currentGame?.name} – slide {index + 1} of {total}
                        </span>
                        <Link
                            href={route('games.show', currentGame.slug)}
                            className="block"
                        >
                            <div
                                className="relative aspect-[315/250] w-full overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200/60 dark:bg-gray-700 dark:ring-gray-700/60">
                                {getThumb(currentGame) ? (
                                    <img
                                        src={getThumb(currentGame)!}
                                        alt={currentGame.name}
                                        title={currentGame.name}
                                        className="h-full w-full object-cover transition-opacity group-hover:opacity-90"
                                    />
                                ) : (
                                    <div
                                        className="flex h-full w-full items-center justify-center text-sm text-gray-400">
                                        No image
                                    </div>
                                )}
                                <div
                                    className="absolute inset-0 bg-black/60 opacity-0 transition-opacity group-hover:opacity-100"/>
                                <div
                                    className="absolute top-2 right-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">
                                    {index + 1}/{total}
                                </div>
                                {total > 1 && (
                                    <>
                                        <button
                                            onClick={goPrev}
                                            aria-label="Previous"
                                            className="absolute top-1/2 left-2 -translate-y-1/2 rounded-full bg-black/50 p-1 text-white opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-black/60 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-white/80"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                className="h-5 w-5"
                                            >
                                                <path
                                                    fillRule="evenodd"
                                                    d="M15.78 4.22a.75.75 0 010 1.06L9.06 12l6.72 6.72a.75.75 0 11-1.06 1.06l-7.25-7.25a.75.75 0 010-1.06l7.25-7.25a.75.75 0 011.06 0z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            onClick={goNext}
                                            aria-label="Next"
                                            className="absolute top-1/2 right-2 -translate-y-1/2 rounded-full bg-black/50 p-1 text-white opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-black/60 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-white/80"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                className="h-5 w-5"
                                            >
                                                <path
                                                    fillRule="evenodd"
                                                    d="M8.22 19.78a.75.75 0 010-1.06L14.94 12 8.22 5.28a.75.75 0 111.06-1.06l7.25 7.25a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </>
                                )}
                            </div>
                        </Link>
                        <div className="mt-2">
                            <Link
                                href={route('games.show', currentGame.slug)}
                                className="block truncate text-sm text-gray-900 hover:underline dark:text-gray-100"
                                title={currentGame.name}
                            >
                                {currentGame.name}
                            </Link>
                        </div>
                    </div>
                )}

                {!currentGame && list.entries.length > 0 && (
                    <div className="border-t border-gray-200/50 px-6 py-4 dark:border-gray-700/50">
                        <h3 className="mb-3 text-sm font-medium text-gray-900 dark:text-white">
                            Featured Games
                        </h3>
                        <div className="flex flex-wrap gap-2">
                            {list.entries.slice(0, 3).map((entry) => (
                                <Link
                                    key={entry.id}
                                    href={route('games.show', entry.game.slug)}
                                    className="rounded-md bg-gray-100/80 px-2 py-1 text-xs text-gray-800 transition-colors hover:bg-gray-200 dark:bg-gray-700/80 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    {entry.game.name.length > 25
                                        ? entry.game.name.substring(0, 25) + '...'
                                        : entry.game.name}
                                </Link>
                            ))}
                            {list.entries.length > 3 && (
                                <span
                                    className="rounded-md bg-gray-100/80 px-2 py-1 text-xs text-gray-800 dark:bg-gray-700/80 dark:text-gray-200">
                                    +{list.entries.length - 3} more
                                </span>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <div className="mt-auto border-t border-gray-200/50 px-6 py-4 dark:border-gray-700/50">
                <div className="flex flex-wrap items-center gap-4">
                    <Link
                        href={route('lists.show', list.id)}
                        className="text-sm font-medium text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        View List
                    </Link>
                    {showUser && list.user && (
                        <Link
                            href={route('lists.user-public', list.user.id)}
                            className="text-sm font-medium text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        >
                            More by {list.user.name}
                        </Link>
                    )}
                    {showActions && isOwner && (
                        <>
                            {onToggleVisibility && (
                                <button
                                    onClick={handleToggleVisibility}
                                    disabled={isToggling}
                                    className={`${list.is_public ? 'text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300'} text-sm font-medium transition-colors disabled:opacity-50`}
                                >
                                    {isToggling
                                        ? 'Updating...'
                                        : list.is_public
                                            ? 'Make Private'
                                            : 'Make Public'}
                                </button>
                            )}
                            <Link
                                href={route('lists.edit', list.id)}
                                className="text-sm font-medium text-yellow-600 transition-colors hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                            >
                                Edit
                            </Link>
                            {!list.is_default && onDelete && (
                                <button
                                    onClick={handleDelete}
                                    className="text-sm font-medium text-red-600 transition-colors hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    Delete
                                </button>
                            )}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
