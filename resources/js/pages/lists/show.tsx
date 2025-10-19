import {Head, Link} from '@inertiajs/react';
import React, {useCallback, useState} from 'react';

import {notify} from '@/components/toast';
import {authenticatedFetch} from '@/utils/csrf';
import {VersionComparisonModal} from '@/components/version-comparison-modal';
import {getListTypeConfig, listStatusConfig, StatusBadge} from '@/utils/status-indicators';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';

interface GameVersion {
    id: number;
    version: string;
    published_at: string;
}

interface Game {
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
    latest_version?: GameVersion;
    game_versions?: GameVersion[];
    user_progress?: UserGameProgress[];
    platform?: 'itch_io' | 'steam' | 'other';
}

interface UserGameProgress {
    id: number;
    user_id: number;
    game_id: number;
    game_version_id?: number;
    personal_notes?: string;
    started_at?: string;
    completed_at?: string;
    game_version?: GameVersion;
    receive_updates?: boolean;
}

interface VnListEntry {
    id: number;
    game: Game;
    sort_order: number;
    notes?: string;
    private_notes?: string;
    started_at?: string;
    completed_at?: string;
    user_progress?: UserGameProgress;
    // Additional fields that might exist
    personal_notes?: string;
    game_version_id?: number;
    game_version?: GameVersion;
}

interface User {
    id: number;
    name: string;
}

interface VnList {
    id: number;
    name: string;
    description?: string;
    type: string;
    is_default: boolean;
    is_public: boolean;
    created_at: string;
    updated_at: string;
    entries: VnListEntry[];
    user: User;
}

interface AvailableList {
    id: number;
    name: string;
    type: string;
}

interface ListShowProps {
    vnList: VnList;
    isOwner: boolean;
    availableLists?: AvailableList[];
    metaTags?: {
        title?: string;
        description?: string;
    };
}

// Isolated Notification Toggle Component
const NotificationToggle = React.memo(function NotificationToggle({
                                                                      game,
                                                                      userProgress,
                                                                      externalStatus,
                                                                      onToggle,
                                                                  }: {
    game: Game;
    userProgress?: UserGameProgress;
    externalStatus?: boolean;
    onToggle?: (gameId: number, newStatus: boolean) => void;
}) {
    const [isLoading, setIsLoading] = useState(false);
    const [notificationStatus, setNotificationStatus] = useState(
        userProgress?.receive_updates || false,
    );

    // Update local state when external status changes (from bulk toggle)
    React.useEffect(() => {
        if (externalStatus !== undefined) {
            setNotificationStatus(externalStatus);
        }
    }, [externalStatus]);

    const handleToggle = async () => {
        setIsLoading(true);
        try {
            const response = await authenticatedFetch(
                route('api.user-progress.toggle-updates', game.id),
                {
                    method: 'PATCH',
                    body: JSON.stringify({
                        receive_updates: !notificationStatus,
                    }),
                },
            );

            if (response.ok) {
                const data = await response.json();
                setNotificationStatus(data.receive_updates);

                // Notify parent component about the change
                onToggle?.(game.id, data.receive_updates);

                notify(
                    data.message ||
                    `Notifications ${data.receive_updates ? 'enabled' : 'disabled'} for "${game.name}"`,
                    'success',
                );
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(
                    errorData.message || 'Failed to toggle notifications',
                );
            }
        } catch (error) {
            console.error('Error toggling notifications:', error);
            notify('Failed to update notifications', 'error');
        } finally {
            setIsLoading(false);
        }
    };

    if (game.is_paid) return null;

    return (
        <label className="relative inline-flex cursor-pointer items-center">
            <input
                type="checkbox"
                checked={notificationStatus}
                onChange={handleToggle}
                disabled={isLoading}
                className="peer sr-only"
            />
            <div
                className="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:-translate-x-full dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"></div>
            <span className="sr-only">
                {notificationStatus
                    ? 'Turn off notifications'
                    : 'Turn on notifications'}
            </span>
        </label>
    );
});

// Game Entry Component
interface GameEntryProps {
    entry: VnListEntry;
    isOwner: boolean;
    vnList: VnList;
    availableLists?: AvailableList[];
    onEntryUpdate: (
        entryId: number,
        updatedData?: {
            entry?: Partial<VnListEntry>;
            progress?: Partial<UserGameProgress>;
        },
    ) => void;
    onEntryRemove: (entryId: number) => void;
    onCompareVersions: (
        gameId: number,
        fromVersionId: number,
        toVersionId: number,
    ) => void;
    externalNotificationStatus?: {
        gameIds: number[];
        status: boolean;
        timestamp: number;
    } | null;
    onIndividualNotificationToggle?: (gameId: number, newStatus: boolean) => void;
}

interface SortableGameEntryProps extends GameEntryProps {
    isDragging?: boolean;
}

function SortableGameEntry(props: SortableGameEntryProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id: props.entry.id});

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={isDragging ? 'opacity-50 scale-105 z-50 shadow-lg' : 'transition-transform duration-200'}
        >
            <GameEntry
                {...props}
                dragAttributes={attributes}
                dragListeners={
                    listeners as Record<
                        string,
                        (event: React.SyntheticEvent) => void
                    >
                }
            />
        </div>
    );
}

interface GameEntryPropsWithDrag extends GameEntryProps {
    dragAttributes?: React.HTMLAttributes<HTMLDivElement>;
    dragListeners?: Record<string, (event: React.SyntheticEvent) => void>;
}

const GameEntry = React.memo(function GameEntry({
                                                    entry,
                                                    isOwner,
                                                    vnList,
                                                    availableLists = [],
                                                    onEntryUpdate,
                                                    onEntryRemove,
                                                    onCompareVersions,
                                                    externalNotificationStatus,
                                                    onIndividualNotificationToggle,
                                                    dragAttributes,
                                                    dragListeners,
                                                }: GameEntryPropsWithDrag) {
    const {game} = entry;
    const [isEditing, setIsEditing] = useState(false);
    const [isMoving, setIsMoving] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    // Get user progress - it will be loaded from the game relationship
    const userProgress = game.user_progress?.[0] || entry.user_progress;

    // Form state - use the user progress data from the game
    const [formData, setFormData] = useState({
        game_version_id:
            userProgress?.game_version_id || entry.game_version_id || '',
        personal_notes:
            userProgress?.personal_notes ||
            entry.personal_notes ||
            entry.notes ||
            '',
        private_notes: entry.private_notes || '',
        started_at: userProgress?.started_at
            ? userProgress.started_at.split('T')[0]
            : entry.started_at
                ? entry.started_at.split('T')[0]
                : '',
        completed_at: userProgress?.completed_at
            ? userProgress.completed_at.split('T')[0]
            : entry.completed_at
                ? entry.completed_at.split('T')[0]
                : '',
        target_list_id: '',
    });

    const getOptimizedThumbnail = (game: Game) => {
        // Prefer optimized default thumbnail variant when available
        if (game.optimized_thumbnails?.default?.path) {
            // SSR-safe: use relative path instead of window.location.origin
            return `/storage/${game.optimized_thumbnails.default.path}`;
        }
        // Fallback to original thumb_url
        return game.thumb_url || '';
    };

    const handleSaveEdit = async () => {
        setIsLoading(true);
        try {
            const response = await authenticatedFetch(
                route('api.list-entries.update', entry.id),
                {
                    method: 'PUT',
                    body: JSON.stringify(formData),
                },
            );

            if (response.ok) {
                const data = await response.json();
                setIsEditing(false);
                onEntryUpdate(entry.id, data);
            }
        } catch (error) {
            console.error('Error updating entry:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleMove = async () => {
        if (!formData.target_list_id) return;

        setIsLoading(true);
        try {
            const response = await authenticatedFetch(
                route('api.list-entries.move', entry.id),
                {
                    method: 'POST',
                    body: JSON.stringify({
                        target_list_id: formData.target_list_id,
                    }),
                },
            );

            if (response.ok) {
                onEntryRemove(entry.id);
            }
        } catch (error) {
            console.error('Error moving entry:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleRemove = async () => {
        if (
            !confirm('Are you sure you want to remove this game from the list?')
        )
            return;

        setIsLoading(true);
        try {
            const response = await authenticatedFetch(
                route('api.list-entries.destroy', entry.id),
                {
                    method: 'DELETE',
                },
            );

            if (response.ok) {
                onEntryRemove(entry.id);
            }
        } catch (error) {
            console.error('Error removing entry:', error);
        } finally {
            setIsLoading(false);
        }
    };

    // Get current version from user progress
    const currentVersion =
        userProgress?.game_version || entry.game_version || null;
    const hasUpdate =
        game.latest_version &&
        currentVersion &&
        game.latest_version.id !== currentVersion.id;

    // Filter available lists to exclude lists that already contain this game
    const availableListsForMove = availableLists.filter(
        (list) =>
            list.id !== vnList.id &&
            // TODO: Add logic to filter out lists that already contain this game
            true,
    );

    return (
        <div className="rounded-lg bg-white shadow-sm md:rounded-lg lg:rounded-none dark:bg-gray-800">
            {/* Desktop View */}
            <div
                className={`hidden items-center p-3 lg:flex pr-5${isOwner ? ' cursor-grab active:cursor-grabbing' : ''}`}
            >
                {isOwner ? (
                    <div
                        className="flex w-8 cursor-grab hover:cursor-grab active:cursor-grabbing"
                        {...dragAttributes}
                        {...dragListeners}
                        title="Drag to reorder"
                    >
                        <svg
                            className="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M4 8h16M4 16h16"
                            />
                        </svg>
                    </div>
                ) : (
                    <div className="w-8"></div>
                )}

                {/* Thumbnail */}
                <div className="mr-2 w-20">
                    <Link href={route('games.show', game.slug)}>
                        <img
                            src={getOptimizedThumbnail(game)}
                            alt={game.name}
                            className="h-16 w-16 rounded object-cover"
                            loading="lazy"
                        />
                    </Link>
                </div>

                {/* Title */}
                <div className="flex-grow">
                    <Link
                        href={route('games.show', game.slug)}
                        className="font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                        {game.name}
                    </Link>

                    {/* Notes Display */}
                    {(userProgress?.personal_notes ||
                        entry.personal_notes ||
                        entry.notes) && (
                        <div className="max-w-md truncate text-xs italic">
                            <span className="text-gray-500 dark:text-gray-400">
                                Public:
                            </span>{' '}
                            "
                            {userProgress?.personal_notes ||
                                entry.personal_notes ||
                                entry.notes}
                            "
                        </div>
                    )}
                    {isOwner && entry.private_notes && (
                        <div className="mt-1 max-w-md truncate text-xs italic">
                            <span className="text-blue-500 dark:text-blue-400">
                                Private:
                            </span>{' '}
                            "{entry.private_notes}"
                        </div>
                    )}
                </div>

                {/* Version */}
                <div className="w-52">
                    {currentVersion ? (
                        <div
                            className={`border-l-4 pl-3 ${hasUpdate ? 'border-yellow-500' : 'border-transparent'}`}
                        >
                            v{currentVersion.version}
                            <span className="text-gray-400">
                                (
                                {new Date(
                                    currentVersion.published_at,
                                ).toLocaleDateString()}
                                )
                            </span>
                            {/* Version Info Block - Desktop Layout */}
                            {hasUpdate && (
                                <>
                                    <div className="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
                                        Latest: v{game.latest_version?.version}
                                        <span className="text-gray-400">
                                            (
                                            {new Date(
                                                game.latest_version
                                                    ?.published_at || '',
                                            ).toLocaleDateString()}
                                            )
                                        </span>
                                    </div>

                                    {/* Version comparison button */}
                                    {game.latest_version && currentVersion && (
                                        <button
                                            type="button"
                                            className="cursor-pointer mt-1 inline-flex items-center text-xs text-blue-600 hover:underline dark:text-blue-400"
                                            onClick={() => {
                                                onCompareVersions(
                                                    game.id,
                                                    currentVersion.id,
                                                    game.latest_version!.id,
                                                );
                                            }}
                                        >
                                            <svg
                                                className="mr-1 h-3 w-3"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M9 5l7 7-7 7"
                                                />
                                            </svg>
                                            Compare changes
                                        </button>
                                    )}
                                </>
                            )}
                        </div>
                    ) : (
                        <span className="text-xs text-gray-500 dark:text-gray-400">
                            Not started
                        </span>
                    )}
                </div>

                {/* Started Date */}
                <div className="w-30 text-sm">
                    {userProgress?.started_at || entry.started_at
                        ? new Date(
                            userProgress?.started_at || entry.started_at!,
                        ).toLocaleDateString()
                        : '-'}
                </div>

                {/* Completed Date (if applicable) */}
                {(vnList.type === 'custom' || vnList.type === 'completed') && (
                    <div className="w-28 text-sm">
                        {userProgress?.completed_at || entry.completed_at
                            ? new Date(
                                userProgress?.completed_at ||
                                entry.completed_at!,
                            ).toLocaleDateString()
                            : '-'}
                    </div>
                )}

                {/* Actions */}
                {isOwner && (
                    <div className="w-20 space-y-2 text-sm">
                        <button
                            onClick={() => setIsEditing(!isEditing)}
                            className="block w-full cursor-pointer text-left text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        >
                            Edit
                        </button>

                        {availableListsForMove.length > 0 && (
                            <button
                                onClick={() => setIsMoving(!isMoving)}
                                className="block w-full cursor-pointer text-left text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                            >
                                Move
                            </button>
                        )}

                        <button
                            onClick={handleRemove}
                            disabled={isLoading}
                            className="block w-full cursor-pointer text-left text-red-600 hover:text-red-800 disabled:opacity-50 dark:text-red-400 dark:hover:text-red-300"
                        >
                            Remove
                        </button>
                    </div>
                )}

                {/* Notification Toggle */}
                {isOwner && (
                    <div className="w-30 pr-1">
                        <NotificationToggle
                            game={game}
                            userProgress={userProgress}
                            externalStatus={
                                externalNotificationStatus?.gameIds.includes(game.id)
                                    ? externalNotificationStatus.status
                                    : undefined
                            }
                            onToggle={onIndividualNotificationToggle}
                        />
                    </div>
                )}
            </div>

            {/* Mobile/Tablet View */}
            <div
                className={`flex p-4 lg:hidden relative${isOwner ? ' cursor-grab active:cursor-grabbing' : ''}`}
            >
                {isOwner && (
                    <div className="absolute top-1/2 -left-1 flex -translate-y-1/2 transform items-center">
                        <div
                            className="flex h-10 w-6 items-center justify-center rounded-r-md bg-gray-100 cursor-grab hover:cursor-grab active:cursor-grabbing hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600"
                            {...dragAttributes}
                            {...dragListeners}
                            title="Drag to reorder"
                        >
                            <svg
                                className="h-4 w-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M4 8h16M4 16h16"
                                />
                            </svg>
                        </div>
                    </div>
                )}

                <div className={`flex gap-4${isOwner ? 'pl-4' : ''}`}>
                    {/* Thumbnail */}
                    <Link href={route('games.show', game.slug)}>
                        <img
                            src={getOptimizedThumbnail(game)}
                            alt={game.name}
                            className={`h-32 w-32 rounded ${game.platform === 'steam' ? 'object-contain' : 'object-cover'}`}
                            loading="lazy"
                        />
                    </Link>

                    {/* Game Info */}
                    <div className="flex-1">
                        <Link
                            href={route('games.show', game.slug)}
                            className="text-lg font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            {game.name}
                        </Link>

                        <div className="mt-2 flex items-center gap-2">
                            {/* Version Badge */}
                            {currentVersion && (
                                <span
                                    className={`mb-1 rounded-full px-2 py-1 text-xs ${
                                        hasUpdate
                                            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300'
                                            : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    }`}
                                >
                                    v{currentVersion.version}
                                </span>
                            )}
                        </div>

                        <div className="text-sm">
                            {/* Started Date */}
                            <div>
                                <span>Started:</span>
                                <span className="ml-1">
                                    {userProgress?.started_at ||
                                    entry.started_at
                                        ? new Date(
                                            userProgress?.started_at ||
                                            entry.started_at!,
                                        ).toLocaleDateString()
                                        : 'Not started'}
                                </span>
                            </div>

                            {/* Completed Date */}
                            {(vnList.type === 'custom' ||
                                vnList.type === 'completed') && (
                                <div>
                                    <span>Completed:</span>
                                    <span className="ml-1">
                                        {userProgress?.completed_at ||
                                        entry.completed_at
                                            ? new Date(
                                                userProgress?.completed_at ||
                                                entry.completed_at!,
                                            ).toLocaleDateString()
                                            : '-'}
                                    </span>
                                </div>
                            )}

                            {/* Update Available Notice */}
                            {hasUpdate && (
                                <div className="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
                                    Latest: v{game.latest_version?.version}
                                    <span className="ml-1 text-gray-400">
                                        (
                                        {new Date(
                                            game.latest_version?.published_at ||
                                            '',
                                        ).toLocaleDateString()}
                                        )
                                    </span>
                                    {/* Version comparison button for mobile */}
                                    {game.latest_version && (
                                        <button
                                            type="button"
                                            className="cursor-pointer ml-2 inline-flex items-center text-xs text-blue-600 hover:underline dark:text-blue-400"
                                            onClick={() => {
                                                // showToast?.('Version comparison feature coming soon', 'info'); // Removed showToast
                                            }}
                                        >
                                            <svg
                                                className="mr-1 h-3 w-3"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M9 5l7 7-7 7"
                                                />
                                            </svg>
                                            Compare changes
                                        </button>
                                    )}
                                </div>
                            )}

                            {/* Notes Preview (truncated) */}
                            {(userProgress?.personal_notes ||
                                entry.personal_notes ||
                                entry.notes) && (
                                <div className="mt-1 truncate text-xs italic">
                                    <span className="text-gray-500 dark:text-gray-400">
                                        Public:
                                    </span>{' '}
                                    "
                                    {userProgress?.personal_notes ||
                                        entry.personal_notes ||
                                        entry.notes}
                                    "
                                </div>
                            )}

                            {isOwner && entry.private_notes && (
                                <div className="mt-1 truncate text-xs italic">
                                    <span className="text-blue-500 dark:text-blue-400">
                                        Private:
                                    </span>{' '}
                                    "{entry.private_notes}"
                                </div>
                            )}
                        </div>

                        {isOwner && (
                            <div className="mt-3 flex flex-col gap-3">
                                <div className="flex space-x-2 text-sm">
                                    <button
                                        onClick={() => setIsEditing(!isEditing)}
                                        className="cursor-pointer text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        Edit
                                    </button>

                                    {availableListsForMove.length > 0 && (
                                        <button
                                            onClick={() =>
                                                setIsMoving(!isMoving)
                                            }
                                            className="cursor-pointer text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                                        >
                                            Move
                                        </button>
                                    )}

                                    <button
                                        onClick={handleRemove}
                                        disabled={isLoading}
                                        className="cursor-pointer text-red-600 hover:text-red-800 disabled:opacity-50 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        Remove
                                    </button>
                                </div>

                                <div className="h-px bg-gray-200 dark:bg-gray-700"></div>

                                <div className="flex items-center justify-start gap-3">
                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Notifications
                                    </span>
                                    <NotificationToggle
                                        game={game}
                                        userProgress={userProgress}
                                        externalStatus={
                                            externalNotificationStatus?.gameIds.includes(game.id)
                                                ? externalNotificationStatus.status
                                                : undefined
                                        }
                                        onToggle={onIndividualNotificationToggle}
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Edit Form */}
            {isEditing && isOwner && (
                <div className="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700">
                    <div className="space-y-4">
                        <div
                            className={`grid grid-cols-1 md:grid-cols-2 ${vnList.type === 'custom' || vnList.type === 'completed' ? 'lg:grid-cols-3' : 'lg:grid-cols-2'} gap-4`}
                        >
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Last Read Version
                                </label>
                                <select
                                    value={formData.game_version_id}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            game_version_id: e.target.value,
                                        })
                                    }
                                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                >
                                    <option value="">Not started</option>
                                    {game.game_versions?.map((version) => (
                                        <option
                                            key={version.id}
                                            value={version.id}
                                        >
                                            {version.version} (
                                            {new Date(
                                                version.published_at,
                                            ).toLocaleDateString()}
                                            )
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Started At
                                </label>
                                <input
                                    type="date"
                                    value={formData.started_at}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            started_at: e.target.value,
                                        })
                                    }
                                    className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>

                            {(vnList.type === 'custom' ||
                                vnList.type === 'completed') && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Completed At
                                    </label>
                                    <input
                                        type="date"
                                        value={formData.completed_at}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                completed_at: e.target.value,
                                            })
                                        }
                                        className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>
                            )}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Public Notes
                            </label>
                            <textarea
                                value={formData.personal_notes}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        personal_notes: e.target.value,
                                    })
                                }
                                rows={4}
                                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                These notes will be visible to anyone who can
                                see this list.
                            </p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Private Notes
                            </label>
                            <textarea
                                value={formData.private_notes}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        private_notes: e.target.value,
                                    })
                                }
                                rows={4}
                                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                These notes will only be visible to you, even if
                                the list is public.
                            </p>
                        </div>

                        <div className="flex justify-end space-x-2">
                            <button
                                onClick={() => setIsEditing(false)}
                                className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleSaveEdit}
                                disabled={isLoading}
                                className="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-50"
                            >
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Move Form */}
            {isMoving && isOwner && availableListsForMove.length > 0 && (
                <div className="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700">
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Target List
                            </label>
                            <select
                                value={formData.target_list_id}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        target_list_id: e.target.value,
                                    })
                                }
                                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >
                                <option value="">Select a list...</option>
                                {availableListsForMove.map((list) => (
                                    <option key={list.id} value={list.id}>
                                        {list.name} (
                                        {list.type
                                            .replace(/_/g, ' ')
                                            .replace(/\b\w/g, (l) =>
                                                l.toUpperCase(),
                                            )}
                                        )
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex justify-end space-x-2">
                            <button
                                onClick={() => setIsMoving(false)}
                                className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleMove}
                                disabled={isLoading || !formData.target_list_id}
                                className="inline-flex items-center rounded-md border border-transparent bg-yellow-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-yellow-700 disabled:opacity-50"
                            >
                                Move to List
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
});

export default function ListShow({
                                     vnList,
                                     isOwner,
                                     availableLists = [],
                                     metaTags,
                                 }: ListShowProps) {
    const statusConfig = getListTypeConfig(vnList?.type || 'custom');
    const color = statusConfig.color;
    const [entries, setEntries] = useState<VnListEntry[]>(vnList.entries);
    const [isPublic, setIsPublic] = useState<boolean>(vnList.is_public);
    const [listData, setListData] = useState({
        name: vnList.name,
        description: vnList.description,
    });
    const [isToggleVisibilityLoading, setIsToggleVisibilityLoading] =
        useState(false);
    const [isEditingList, setIsEditingList] = useState(false);
    const [isListSaveLoading, setIsListSaveLoading] = useState(false);
    const [isListDeleteLoading, setIsListDeleteLoading] = useState(false);
    const [listFormData, setListFormData] = useState({
        name: vnList.name,
        description: vnList.description || '',
    });
    const [showVersionComparison, setShowVersionComparison] = useState(false);
    const [comparisonVersions, setComparisonVersions] = useState<{
        gameId: number;
        fromVersionId: number;
        toVersionId: number;
    } | null>(null);

    // State for tracking external notification status updates (from bulk toggle)
    const [externalNotificationStatus, setExternalNotificationStatus] = useState<{
        gameIds: number[];
        status: boolean;
        timestamp: number;
    } | null>(null);


    // Drag and drop sensors
    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    // Reorder function
    const reorderEntries = useCallback(async (entryIds: number[], originalEntries: VnListEntry[]) => {
        try {
            const response = await authenticatedFetch(
                route('api.lists.reorder', vnList.id),
                {
                    method: 'POST',
                    body: JSON.stringify({entry_ids: entryIds}),
                },
            );

            if (response.ok) {
                const data = await response.json();
                notify(data.message || 'List order updated', 'success');
            } else {
                // Revert to original order on server error
                setEntries(originalEntries);
                throw new Error('Failed to update order');
            }
        } catch (error) {
            console.error('Error updating order:', error);
            notify('Failed to update order', 'error');
            // Ensure we revert to original order if not already done
            setEntries(originalEntries);
        }
    }, [vnList.id]);

    // Handle drag end
    const handleDragEnd = async (event: DragEndEvent) => {
        const {active, over} = event;

        if (active.id !== over?.id) {
            const originalEntries = [...entries]; // Store original order for rollback
            const oldIndex = entries.findIndex(
                (entry) => entry.id === active.id,
            );
            const newIndex = entries.findIndex(
                (entry) => entry.id === over?.id,
            );

            const newEntries = arrayMove(entries, oldIndex, newIndex);
            setEntries(newEntries);

            // Update the server with new order immediately
            const entryIds = newEntries.map((entry) => entry.id);
            reorderEntries(entryIds, originalEntries);
        }
    };

    // Handle entry updates
    const handleEntryUpdate = useCallback(
        (
            entryId: number,
            updatedData?: {
                entry?: Partial<VnListEntry>;
                progress?: Partial<UserGameProgress>;
            },
        ) => {
            if (updatedData) {
                // Update the entry in state with the fresh data from server
                setEntries((prev) =>
                    prev.map((entry) => {
                        if (entry.id === entryId) {
                            // Merge the updated entry data and progress data
                            const updatedEntry = {...entry};

                            // Update entry fields
                            if (updatedData.entry) {
                                Object.assign(updatedEntry, updatedData.entry);
                            }

                            // Update user progress data on the game
                            if (
                                updatedData.progress &&
                                updatedEntry.game.user_progress
                            ) {
                                updatedEntry.game.user_progress[0] = {
                                    ...updatedEntry.game.user_progress[0],
                                    ...updatedData.progress,
                                };
                            } else if (
                                updatedData.progress &&
                                updatedEntry.game.user_progress?.[0]
                            ) {
                                updatedEntry.game.user_progress = [
                                    {
                                        ...updatedEntry.game.user_progress[0],
                                        ...updatedData.progress,
                                    },
                                ];
                            } else if (updatedData.progress) {
                                updatedEntry.game.user_progress = [
                                    {
                                        id: 0,
                                        user_id: 0,
                                        game_id: updatedEntry.game.id,
                                        ...updatedData.progress,
                                    },
                                ];
                            }

                            return updatedEntry;
                        }
                        return entry;
                    }),
                );
            }

            notify('Entry updated', 'success');
        },
        [],
    );

    // Handle entry removal
    const handleEntryRemove = useCallback((entryId: number) => {
        setEntries((prev) => prev.filter((entry) => entry.id !== entryId));
    }, []);

    // Handle list editing
    const handleSaveList = async () => {
        setIsListSaveLoading(true);
        try {
            const response = await authenticatedFetch(
                route('api.vn-lists.update', vnList.id),
                {
                    method: 'PUT',
                    body: JSON.stringify({
                        ...listFormData,
                        is_public: isPublic, // Include current visibility state
                    }),
                },
            );

            if (response.ok) {
                const data = await response.json();
                setIsEditingList(false);

                // Update local list data with the response
                if (data.vnList) {
                    setListData({
                        name: data.vnList.name,
                        description: data.vnList.description,
                    });
                    setIsPublic(data.vnList.is_public);
                }

                // Update the page title
                document.title = `${listFormData.name} - ${document.title.split(' - ').slice(1).join(' - ')}`;
                notify('List updated successfully', 'success');
            } else {
                throw new Error('Failed to update list');
            }
        } catch (error) {
            console.error('Error updating list:', error);
            notify('Failed to update list', 'error');
        } finally {
            setIsListSaveLoading(false);
        }
    };

    const handleCancelListEdit = () => {
        setListFormData({
            name: listData.name,
            description: listData.description || '',
        });
        setIsEditingList(false);
    };

    // Handle list deletion
    const handleDeleteList = async () => {
        if (!confirm('Are you sure you want to delete this list? This action cannot be undone.')) {
            return;
        }

        setIsListDeleteLoading(true);
        try {
            const response = await authenticatedFetch(
                route('api.vn-lists.destroy', vnList.id),
                {
                    method: 'DELETE',
                },
            );

            if (response.ok) {
                notify('List deleted successfully', 'success');
                // Redirect to lists index after deletion
                window.location.href = route('lists.index');
            } else {
                throw new Error('Failed to delete list');
            }
        } catch (error) {
            console.error('Error deleting list:', error);
            notify('Failed to delete list', 'error');
        } finally {
            setIsListDeleteLoading(false);
        }
    };

    // Handle toggle visibility
    const handleToggleVisibility = async () => {
        setIsToggleVisibilityLoading(true);
        try {
            const response = await authenticatedFetch(
                route('api.vn-lists.toggle-visibility', vnList.id),
                {
                    method: 'POST',
                },
            );

            if (response.ok) {
                const data = await response.json();
                // Update local state with the actual value from backend
                setIsPublic(data.is_public);
                notify(data.message || 'List visibility updated', 'success');
            } else {
                throw new Error('Failed to toggle visibility');
            }
        } catch (error) {
            console.error('Error toggling visibility:', error);
            notify('Failed to update visibility', 'error');
        } finally {
            setIsToggleVisibilityLoading(false);
        }
    };

    // Handle version comparison
    const handleCompareVersions = useCallback(
        (gameId: number, fromVersionId: number, toVersionId: number) => {
            setComparisonVersions({gameId, fromVersionId, toVersionId});
            setShowVersionComparison(true);
        },
        [],
    );

    const handleCloseVersionComparison = useCallback(() => {
        setShowVersionComparison(false);
        setComparisonVersions(null);
    }, []);

    // Handle individual notification toggle changes
    const handleIndividualNotificationToggle = useCallback((gameId: number, newStatus: boolean) => {
        setEntries(prevEntries =>
            prevEntries.map(entry => {
                if (entry.game.id === gameId) {
                    return {
                        ...entry,
                        game: {
                            ...entry.game,
                            user_progress: entry.game.user_progress && entry.game.user_progress.length > 0
                                ? entry.game.user_progress.map(progress => ({
                                    ...progress,
                                    receive_updates: newStatus
                                }))
                                : [{
                                    id: 0, // Temporary ID since we don't have the real one
                                    user_id: 0, // Will be set by backend
                                    game_id: gameId,
                                    receive_updates: newStatus,
                                    created_at: new Date().toISOString(),
                                    updated_at: new Date().toISOString(),
                                }]
                        }
                    };
                }
                return entry;
            })
        );
    }, []);

    // Calculate free games count and notification status for bulk toggle
    const freeGames = entries.filter((entry) => !entry.game.is_paid);
    const allFreeGamesReceiveUpdates =
        freeGames.length > 0 &&
        freeGames.every(
            (entry) => entry.game.user_progress?.[0]?.receive_updates ?? false,
        );

    const handleToggleAllNotifications = async () => {
        const newStatus = !allFreeGamesReceiveUpdates;

        try {
            const response = await authenticatedFetch(
                route('api.vn-lists.toggle-all-updates', vnList.id),
                {
                    method: 'PATCH',
                    body: JSON.stringify({receive_updates: newStatus}),
                },
            );

            if (response.ok) {
                const data = await response.json();
                notify(
                    data.message ||
                    `Notifications ${newStatus ? 'enabled' : 'disabled'} for all games`,
                    'success',
                );

                // Update both the entries state and trigger external notification status
                if (data.updated_game_ids && Array.isArray(data.updated_game_ids)) {
                    // Update the entries state to reflect the new notification status
                    setEntries(prevEntries =>
                        prevEntries.map(entry => {
                            if (data.updated_game_ids.includes(entry.game.id)) {
                                return {
                                    ...entry,
                                    game: {
                                        ...entry.game,
                                        user_progress: entry.game.user_progress && entry.game.user_progress.length > 0
                                            ? entry.game.user_progress.map(progress => ({
                                                ...progress,
                                                receive_updates: data.receive_updates
                                            }))
                                            : [{
                                                id: 0, // Temporary ID since we don't have the real one
                                                user_id: 0, // Will be set by backend
                                                game_id: entry.game.id,
                                                receive_updates: data.receive_updates,
                                                created_at: new Date().toISOString(),
                                                updated_at: new Date().toISOString(),
                                            }]
                                    }
                                };
                            }
                            return entry;
                        })
                    );

                    // Also trigger external notification status for immediate UI feedback
                    setExternalNotificationStatus({
                        gameIds: data.updated_game_ids,
                        status: data.receive_updates,
                        timestamp: Date.now(), // Force re-render even if status is the same
                    });
                }
            } else {
                throw new Error('Failed to toggle notifications');
            }
        } catch (error) {
            console.error('Error toggling all notifications:', error);
            notify('Failed to update notifications', 'error');
        }
    };

    return (
        <>
            <Head title={metaTags?.title || (isEditingList ? listFormData.name : listData.name)}/>

            <div className="space-y-6">
                {/* Header Card */}
                <div
                    className={`mb-6 rounded-lg border-l-4 bg-white p-4 shadow-sm md:p-6 dark:bg-gray-800 ${
                        color === 'blue'
                            ? 'border-blue-500'
                            : color === 'green'
                                ? 'border-green-500'
                                : color === 'yellow'
                                    ? 'border-yellow-500'
                                    : color === 'orange'
                                        ? 'border-orange-500'
                                        : color === 'red'
                                            ? 'border-red-500'
                                            : 'border-gray-500'
                    }`}
                >
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900 md:text-2xl dark:text-white">
                                {isEditingList ? listFormData.name : listData.name}
                            </h1>
                            {(isEditingList ? listFormData.description : listData.description) && (
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    {(isEditingList ? listFormData.description : listData.description)!
                                        .split('\n')
                                        .map((line, index) => (
                                            <span key={index}>
                                                {line}
                                                {index <
                                                    (isEditingList ? listFormData.description : listData.description)!.split(
                                                        '\n',
                                                    ).length -
                                                    1 && <br/>}
                                            </span>
                                        ))}
                                </p>
                            )}
                            {!isOwner && vnList?.user?.name && (
                                <div className="mt-2 text-sm text-gray-500">
                                    By{' '}
                                    <Link
                                        href={route(
                                            'lists.user-public',
                                            vnList.user.id,
                                        )}
                                        className="text-blue-600 hover:underline dark:text-blue-400"
                                    >
                                        {vnList.user.name}
                                    </Link>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {!vnList.is_default && (
                                <StatusBadge
                                    status={vnList.type}
                                    config={listStatusConfig}
                                    size="md"
                                    showIcon={true}
                                    showText={true}
                                />
                            )}

                            {isPublic && (
                                <span
                                    className="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    Public
                                </span>
                            )}

                            {isOwner && (
                                <div className="flex gap-2">
                                    <Link
                                        href={route('lists.index')}
                                        className="inline-flex items-center rounded-md border border-transparent bg-gray-200 px-3 py-1 text-xs font-semibold tracking-widest text-gray-800 uppercase hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                                    >
                                        Back to Lists
                                    </Link>

                                    <button
                                        onClick={handleToggleVisibility}
                                        disabled={isToggleVisibilityLoading}
                                        className={`inline-flex items-center rounded-md border border-transparent px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase disabled:opacity-50 ${
                                            isPublic
                                                ? 'bg-blue-500 hover:bg-blue-400'
                                                : 'bg-gray-500 hover:bg-gray-400'
                                        }`}
                                    >
                                        {isPublic
                                            ? 'Make Private'
                                            : 'Make Public'}
                                    </button>

                                    <button
                                        onClick={() => setIsEditingList(!isEditingList)}
                                        className="inline-flex items-center rounded-md border border-transparent bg-yellow-500 px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase hover:bg-yellow-400"
                                    >
                                        {isEditingList ? 'Cancel Edit' : 'Edit List'}
                                    </button>

                                    {/* Delete button - only for custom lists */}
                                    {vnList.type === 'custom' && !vnList.is_default && (
                                        <button
                                            onClick={handleDeleteList}
                                            disabled={isListDeleteLoading}
                                            className="inline-flex items-center rounded-md border border-transparent bg-red-600 px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase hover:bg-red-700 disabled:opacity-50"
                                        >
                                            {isListDeleteLoading ? 'Deleting...' : 'Delete List'}
                                        </button>
                                    )}
                                </div>
                            )}

                            {!isOwner && (
                                <Link
                                    href={route('lists.public')}
                                    className="inline-flex items-center rounded-md border border-transparent bg-gray-200 px-3 py-1 text-xs font-semibold tracking-widest text-gray-800 uppercase hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                                >
                                    Back to Public Lists
                                </Link>
                            )}
                        </div>
                    </div>
                </div>

                {/* List Edit Form */}
                {isEditingList && isOwner && (
                    <div
                        className="mb-4 rounded-lg bg-white p-4 shadow-sm border-l-4 border-yellow-500 dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Edit List
                        </h3>
                        <div className="space-y-4">
                            <div>
                                <label htmlFor="list-name"
                                       className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    List Name
                                </label>
                                <input
                                    id="list-name"
                                    type="text"
                                    value={listFormData.name}
                                    onChange={(e) => setListFormData(prev => ({...prev, name: e.target.value}))}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    placeholder="Enter list name"
                                />
                            </div>
                            <div>
                                <label htmlFor="list-description"
                                       className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Description (Optional)
                                </label>
                                <textarea
                                    id="list-description"
                                    value={listFormData.description}
                                    onChange={(e) => setListFormData(prev => ({...prev, description: e.target.value}))}
                                    rows={3}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    placeholder="Enter list description"
                                />
                            </div>
                            <div className="flex justify-end space-x-2">
                                <button
                                    onClick={handleCancelListEdit}
                                    className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleSaveList}
                                    disabled={isListSaveLoading || !listFormData.name.trim()}
                                    className="inline-flex items-center rounded-md border border-transparent bg-yellow-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-yellow-700 disabled:opacity-50"
                                >
                                    {isListSaveLoading ? 'Saving...' : 'Save Changes'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* List Stats Card */}
                <div className="mb-4 rounded-lg bg-white p-4 shadow-sm md:pr-6 md:pl-7 dark:bg-gray-800">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            List Entries ({entries.length})
                        </h2>
                        {isOwner && freeGames.length > 0 && (
                            <div className="flex items-center">
                                <div className="flex items-center justify-end gap-3">
                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Notifications for all free entries:
                                    </span>
                                    <label className="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            checked={allFreeGamesReceiveUpdates}
                                            onChange={
                                                handleToggleAllNotifications
                                            }
                                            className="peer sr-only"
                                        />
                                        <div
                                            className="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:-translate-x-full dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"></div>
                                        <span className="sr-only">
                                            {allFreeGamesReceiveUpdates
                                                ? 'Turn off notifications for all free entries'
                                                : 'Turn on notifications for all free entries'}
                                        </span>
                                    </label>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {entries.length === 0 ? (
                    <div className="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-700">
                        <p className="text-gray-500 dark:text-gray-400">
                            No visual novels in this list yet.
                        </p>
                        {isOwner && (
                            <p className="mt-2 text-gray-500 dark:text-gray-400">
                                Browse games and add them to your list!
                            </p>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Desktop Table Header */}
                        <div
                            className="hidden rounded-t-lg bg-gray-100 p-3 pr-5 text-sm font-medium text-gray-500 uppercase lg:flex dark:bg-gray-700 dark:text-gray-300">
                            <div className="w-8"></div>
                            <div className="mr-2 w-20"></div>
                            <div className="flex-grow">Title</div>
                            <div className="w-52">Version</div>
                            <div className="w-30">Started</div>
                            {(vnList.type === 'custom' ||
                                vnList.type === 'completed') && (
                                <div className="w-28">Completed</div>
                            )}
                            {isOwner && (
                                <>
                                    <div className="w-20">Actions</div>
                                    <div className="w-30 pr-1.5 text-right">
                                        Notifications
                                    </div>
                                </>
                            )}
                        </div>

                        {/* Entry List */}
                        {isOwner ? (
                            <DndContext
                                sensors={sensors}
                                collisionDetection={closestCenter}
                                onDragEnd={handleDragEnd}
                            >
                                <SortableContext
                                    items={entries.map((entry) => entry.id)}
                                    strategy={verticalListSortingStrategy}
                                >
                                    <div
                                        className="space-y-3 text-gray-700 md:grid md:grid-cols-2 md:gap-3 md:space-y-0 lg:block lg:space-y-3 dark:text-gray-300">
                                        {entries.map((entry) => (
                                            <SortableGameEntry
                                                key={entry.id}
                                                entry={entry}
                                                isOwner={isOwner}
                                                vnList={vnList}
                                                availableLists={availableLists}
                                                onEntryUpdate={
                                                    handleEntryUpdate
                                                }
                                                onEntryRemove={
                                                    handleEntryRemove
                                                }
                                                onCompareVersions={
                                                    handleCompareVersions
                                                }
                                                externalNotificationStatus={externalNotificationStatus}
                                                onIndividualNotificationToggle={handleIndividualNotificationToggle}
                                            />
                                        ))}
                                    </div>
                                </SortableContext>
                            </DndContext>
                        ) : (
                            <div
                                className="space-y-3 text-gray-700 md:grid md:grid-cols-2 md:gap-3 md:space-y-0 lg:block lg:space-y-3 dark:text-gray-300">
                                {entries.map((entry) => (
                                    <GameEntry
                                        key={entry.id}
                                        entry={entry}
                                        isOwner={isOwner}
                                        vnList={vnList}
                                        availableLists={availableLists}
                                        onEntryUpdate={handleEntryUpdate}
                                        onEntryRemove={handleEntryRemove}
                                        onCompareVersions={
                                            handleCompareVersions
                                        }
                                        externalNotificationStatus={externalNotificationStatus}
                                        onIndividualNotificationToggle={handleIndividualNotificationToggle}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Version Comparison Modal */}
            <VersionComparisonModal
                isOpen={showVersionComparison}
                onClose={handleCloseVersionComparison}
                gameId={comparisonVersions?.gameId || 0}
                fromVersionId={comparisonVersions?.fromVersionId}
                toVersionId={comparisonVersions?.toVersionId}
            />
        </>
    );
}
