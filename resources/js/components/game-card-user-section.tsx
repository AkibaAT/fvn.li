import {usePage} from '@inertiajs/react';
import React, {useCallback, useEffect, useRef, useState} from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin?: boolean;
}

interface InertiaPageProps {
    auth?: {
        user: User | null;
    };
}

interface VnList {
    id: number;
    name: string;
    type: string;
    is_default?: boolean;
    is_public?: boolean;
    user?: {
        id: number;
        name: string;
    };
}

interface UserGameProgress {
    id: number;
    game_id: number;
    user_id: number;
    receive_updates: boolean;
}

interface UserListMembership {
    list_id: number;
    name: string;
    type: string;
    is_default: boolean;
}

interface GameCardUserSectionProps {
    gameId: number;
    gameName: string;
    isPaid?: boolean;
    userProgress?: UserGameProgress | null;
    userListMemberships?: UserListMembership[];
}

interface ApiError {
    response?: {
        data?: {
            message?: string;
        };
    };
    message?: string;
}

export default function GameCardUserSection({
                                                gameId,
                                                gameName,
                                                isPaid = false,
                                                userProgress = null,
                                                userListMemberships = [],
                                            }: GameCardUserSectionProps) {
    const {auth} = (usePage().props as InertiaPageProps) ?? {};
    const isAuthenticated = Boolean(auth?.user);

    const [allLists, setAllLists] = useState<VnList[]>([]);
    const [userLists, setUserLists] = useState<VnList[]>([]);
    const [showListDialog, setShowListDialog] = useState(false);
    const [showUserLists, setShowUserLists] = useState(false);
    const [isTogglingNotifications, setIsTogglingNotifications] = useState(false);
    const [notificationStatus, setNotificationStatus] = useState(
        userProgress?.receive_updates ?? false,
    );
    const [message, setMessage] = useState<{
        type: 'success' | 'error';
        text: string;
    } | null>(null);
    const [newListName, setNewListName] = useState('');
    const [newListIsPublic, setNewListIsPublic] = useState(false);
    const [isCreatingList, setIsCreatingList] = useState(false);
    const [listStates, setListStates] = useState<Record<number, boolean>>({});
    const [loadingStates, setLoadingStates] = useState<Record<number, boolean>>({});
    const dialogRef = useRef<HTMLDialogElement>(null);

    const showMessage = useCallback((text: string, type: 'success' | 'error') => {
        setMessage({text, type});
        setTimeout(() => setMessage(null), 5000);
    }, []);

    // Initialize notification status from pre-loaded data on mount
    useEffect(() => {
        if (!isAuthenticated) return;

        const notificationStatus = userProgress?.receive_updates ?? false;
        setNotificationStatus(notificationStatus);
    }, [isAuthenticated, userProgress]);

    // Load user lists when dialog is opened
    const loadUserListsForDialog = useCallback(async () => {
        if (!isAuthenticated) return;

        try {
            const url = '/react-api/user/lists';

            const listsResponse = await window.axios.get(url);

            if (listsResponse.data?.success) {
                const lists = listsResponse.data.lists || [];
                setAllLists(lists);

                // Filter user's own lists
                const userOwnedLists = lists.filter((list: VnList) =>
                    list.user?.id === auth?.user?.id
                );

                setUserLists(userOwnedLists);

                // Initialize list states based on pre-loaded membership data
                const initialStates: Record<number, boolean> = {};
                lists.forEach((list: VnList) => {
                    // Check if this game is in this list based on pre-loaded data
                    const isInList = userListMemberships.some(membership =>
                        membership.list_id === list.id
                    );
                    initialStates[list.id] = isInList;
                });
                setListStates(initialStates);
            }
        } catch (error) {
            console.error('Failed to load user lists:', error);
            showMessage('Failed to load user lists', 'error');
        }
    }, [isAuthenticated, auth?.user?.id, userListMemberships, showMessage]);

    // Handle dialog open/close
    useEffect(() => {
        if (showListDialog) {
            dialogRef.current?.showModal();
        } else {
            dialogRef.current?.close();
        }
    }, [showListDialog]);

    const handleDefaultListToggle = useCallback(async (listType: string) => {
        const listId = allLists.find(list => list.type === listType)?.id;
        if (!listId) {
            return;
        }

        setLoadingStates(prev => ({...prev, [listId]: true}));

        try {
            const url = `/react-api/games/${gameId}/add-to-list`;

            const response = await window.axios.post(url, {
                list_type: listType,
            });

            if (response.data?.success) {
                const isRemoved = response.data.message.includes('removed');

                // Update list states - for default lists, only one can be active at a time
                if (!isRemoved) {
                    const newStates: Record<number, boolean> = {};
                    allLists.forEach(list => {
                        if (list.type && ['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped'].includes(list.type)) {
                            newStates[list.id] = list.type === listType;
                        } else {
                            newStates[list.id] = listStates[list.id] || false;
                        }
                    });
                    setListStates(newStates);
                } else {
                    setListStates(prev => ({...prev, [listId]: false}));
                }

                showMessage(response.data.message, 'success');
            }
        } catch (error: unknown) {
            console.error('Error toggling default list:', error);
            const apiError = error as ApiError;
            const message = apiError?.response?.data?.message || 'Failed to update list';
            showMessage(message, 'error');
        } finally {
            setLoadingStates(prev => ({...prev, [listId]: false}));
        }
    }, [allLists, gameId, listStates, showMessage]);

    const handleCustomListToggle = useCallback(async (listId: number) => {
        setLoadingStates(prev => ({...prev, [listId]: true}));

        try {
            const url = `/react-api/lists/${listId}/add-game`;

            const response = await window.axios.post(url, {
                game_id: gameId,
            });

            if (response.data?.success) {
                const isRemoved = response.data.message.includes('removed');
                setListStates(prev => ({...prev, [listId]: !isRemoved}));
                showMessage(response.data.message, 'success');
            }
        } catch (error: unknown) {
            console.error('Error toggling custom list:', error);
            const apiError = error as ApiError;
            const message = apiError?.response?.data?.message || 'Failed to update list';
            showMessage(message, 'error');
        } finally {
            setLoadingStates(prev => ({...prev, [listId]: false}));
        }
    }, [gameId, showMessage]);

    const handleCreateList = useCallback(async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newListName.trim() || isCreatingList) return;

        setIsCreatingList(true);
        try {
            const url = '/react-api/vn-lists';

            const response = await window.axios.post(url, {
                name: newListName.trim(),
                is_public: newListIsPublic,
                game_id: gameId,
            });

            if (response.data?.success) {
                const newList = response.data.list;
                setAllLists(prev => [...prev, newList]);
                setUserLists(prev => [...prev, newList]);
                setListStates(prev => ({...prev, [newList.id]: true}));
                setNewListName('');
                setNewListIsPublic(false);
                showMessage(response.data.message, 'success');
            }
        } catch (error: unknown) {
            const apiError = error as ApiError;
            const message = apiError?.response?.data?.message || 'Failed to create list';
            showMessage(message, 'error');
        } finally {
            setIsCreatingList(false);
        }
    }, [newListName, newListIsPublic, isCreatingList, gameId, showMessage]);

    const handleToggleNotifications = useCallback(async () => {
        if (isTogglingNotifications || isPaid) return;

        setIsTogglingNotifications(true);
        try {
            const url = `/react-api/user-progress/${gameId}/toggle-updates`;
            const newStatus = !notificationStatus;

            const response = await window.axios.patch(url, {
                receive_updates: newStatus,
            });

            if (response.data?.success) {
                const newStatus = response.data.receive_updates;
                setNotificationStatus(newStatus);

                // Show success message
                const event = new CustomEvent('show-toast', {
                    detail: {
                        message: `Notifications ${newStatus ? 'enabled' : 'disabled'} for "${gameName}"`,
                        type: 'success',
                    },
                });
                document.dispatchEvent(event);
            }
        } catch (error: unknown) {
            const apiError = error as ApiError;
            const message =
                apiError?.response?.data?.message ||
                apiError?.message ||
                'Failed to toggle notifications';

            // Show error message
            const event = new CustomEvent('show-toast', {
                detail: {
                    message,
                    type: 'error',
                },
            });
            document.dispatchEvent(event);
        } finally {
            setIsTogglingNotifications(false);
        }
    }, [isTogglingNotifications, isPaid, gameId, gameName, notificationStatus]);

    // Don't render anything if user is not authenticated
    if (!isAuthenticated) {
        return null;
    }

    // Get primary list type for badge color
    const getPrimaryListType = () => {
        const priorityOrder = ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped'];
        for (const type of priorityOrder) {
            if (userLists.some(list => list.type === type && listStates[list.id])) {
                return type;
            }
        }
        return null;
    };

    const getBadgeColor = (listType: string | null) => {
        switch (listType) {
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
                return 'blue';
        }
    };

    const primaryListType = getPrimaryListType();
    const badgeColor = getBadgeColor(primaryListType);
    const userListsInGame = userLists.filter(list => listStates[list.id]);

    return (
        <div className="border-t border-gray-100 pt-3 dark:border-gray-700/50">
            {/* Fixed Top Controls Row */}
            <div className="flex flex-wrap gap-2">
                {/* Manage Lists Button */}
                <button
                    onClick={async () => {
                        setShowListDialog(true);
                        await loadUserListsForDialog();
                    }}
                    className="flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                >
                    <svg
                        className="h-4 w-4"
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
                    {userListsInGame.length > 0 ? 'Manage in Lists' : 'Add to Lists'}
                </button>

                {/* User Lists Toggle Button */}
                {userListsInGame.length > 0 && (
                    <button
                        onClick={() => setShowUserLists(!showUserLists)}
                        className="flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    >
                        <span>My Lists</span>
                        <span
                            className={`rounded-full px-1.5 py-0.5 text-xs font-medium bg-${badgeColor}-100 text-${badgeColor}-800 dark:bg-${badgeColor}-900 dark:text-${badgeColor}-200`}>
                            {userListsInGame.length}
                        </span>
                        <svg
                            className={`h-4 w-4 transition-transform ${showUserLists ? 'rotate-180' : ''}`}
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                )}

                {/* Notifications Toggle - Only for free games */}
                {!isPaid && (
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={notificationStatus}
                            onChange={handleToggleNotifications}
                            disabled={isTogglingNotifications}
                            className="sr-only"
                        />
                        <div
                            className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${
                                notificationStatus
                                    ? 'bg-blue-600'
                                    : 'bg-gray-300 dark:bg-gray-600'
                            } ${isTogglingNotifications ? 'opacity-50' : ''}`}
                        >
                            <span
                                className={`inline-block h-3 w-3 transform rounded-full bg-white transition-transform ${
                                    notificationStatus ? 'translate-x-5' : 'translate-x-1'
                                }`}
                            />
                        </div>
                        <span className="text-xs text-gray-600 dark:text-gray-400">
                            {isTogglingNotifications
                                ? 'Updating...'
                                : notificationStatus
                                    ? 'Notifications on'
                                    : 'Notifications off'}
                        </span>
                    </label>
                )}
            </div>

            {/* User Lists Section */}
            {userListsInGame.length > 0 && showUserLists && (
                <div className="mt-3">
                    <div
                        className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700/50 dark:bg-gray-800/50">
                        <h3 className="mb-3 text-sm font-medium text-gray-900 dark:text-gray-100">My Lists</h3>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {userListsInGame.map((list) => (
                                <div
                                    key={list.id}
                                    className="flex flex-col rounded-lg border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <a
                                                href={route('lists.show', list.id)}
                                                className="block truncate font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400"
                                            >
                                                {list.name}
                                            </a>
                                            <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {list.type?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                                {list.is_public && (
                                                    <span
                                                        className="ml-1 rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        Public
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <span
                                            className={`flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-medium bg-${getBadgeColor(list.type)}-100 text-${getBadgeColor(list.type)}-800 dark:bg-${getBadgeColor(list.type)}-900 dark:text-${getBadgeColor(list.type)}-200`}>
                                            {list.type?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Comprehensive List Management Dialog */}
            <dialog
                ref={dialogRef}
                className="m-auto rounded-lg bg-white p-6 shadow-xl w-full max-w-md dark:bg-gray-800 dark:text-gray-100 backdrop:backdrop-blur-md"
                onClick={(e) => {
                    // Close dialog when clicking on backdrop
                    const dialogDimensions = e.currentTarget.getBoundingClientRect();
                    if (
                        e.clientX < dialogDimensions.left ||
                        e.clientX > dialogDimensions.right ||
                        e.clientY < dialogDimensions.top ||
                        e.clientY > dialogDimensions.bottom
                    ) {
                        dialogRef.current?.close();
                        setShowListDialog(false);
                    }
                }}
            >
                <div onClick={(e) => e.stopPropagation()}>
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                            Manage Lists for "{gameName}"
                        </h3>
                        <button
                            onClick={() => {
                                dialogRef.current?.close();
                                setShowListDialog(false);
                            }}
                            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        >
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {/* Message Area */}
                    <div className="mb-4 h-6 text-center text-sm">
                        {message && (
                            <span
                                className={message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}>
                                    {message.text}
                                </span>
                        )}
                    </div>

                    <div className="space-y-6">
                        {/* Default Lists */}
                        <div>
                            <h4 className="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Default Lists</h4>
                            <div className="space-y-1">
                                {['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped'].map((listType) => {
                                    const list = allLists.find(l => l.type === listType);
                                    const isInList = list ? listStates[list.id] : false;
                                    const isLoading = list ? loadingStates[list.id] : false;

                                    return (
                                        <button
                                            key={listType}
                                            onClick={() => handleDefaultListToggle(listType)}
                                            disabled={isLoading}
                                            className={`flex w-full items-center justify-between px-4 py-2 text-left text-sm transition-colors ${
                                                isInList
                                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                                    : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600'
                                            }`}
                                        >
                                            <div className="flex items-center gap-2">
                                                <span>{listType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                                <span
                                                    className={`h-3 w-3 rounded-full bg-${getBadgeColor(listType)}-500`}></span>
                                            </div>
                                            {isLoading ? (
                                                <span className="text-sm">Updating...</span>
                                            ) : isInList ? (
                                                <span className="text-sm font-medium">Remove</span>
                                            ) : null}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Custom Lists */}
                        <div>
                            <h4 className="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Custom Lists</h4>
                            <div className="space-y-1">
                                {allLists
                                    .filter(list => list.type === 'custom' || !list.type)
                                    .sort((a, b) => a.name.localeCompare(b.name))
                                    .map((list) => {
                                        const isInList = listStates[list.id];
                                        const isLoading = loadingStates[list.id];

                                        return (
                                            <button
                                                key={list.id}
                                                onClick={() => handleCustomListToggle(list.id)}
                                                disabled={isLoading}
                                                className={`flex w-full items-center justify-between px-4 py-2 text-left text-sm transition-colors ${
                                                    isInList
                                                        ? 'bg-blue-600 text-white hover:bg-blue-700'
                                                        : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600'
                                                }`}
                                            >
                                                <span>{list.name}</span>
                                                {isLoading ? (
                                                    <span className="text-sm">Updating...</span>
                                                ) : isInList ? (
                                                    <span className="text-sm font-medium">Remove</span>
                                                ) : null}
                                            </button>
                                        );
                                    })}
                            </div>

                            {/* Quick List Creation Form */}
                            <div className="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                                <form onSubmit={handleCreateList}>
                                    <div className="flex gap-2">
                                        <input
                                            type="text"
                                            value={newListName}
                                            onChange={(e) => setNewListName(e.target.value)}
                                            placeholder="New list name"
                                            className="flex-1 rounded-md border-0 bg-gray-100 px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 dark:bg-gray-700"
                                            required
                                        />
                                        <button
                                            type="submit"
                                            disabled={!newListName.trim() || isCreatingList}
                                            className="rounded-md bg-blue-600 px-3 py-1 text-sm text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {isCreatingList ? 'Creating...' : 'Create & Add'}
                                        </button>
                                    </div>
                                    <div className="mt-2 flex items-center">
                                        <input
                                            type="checkbox"
                                            id={`is_public_${gameId}`}
                                            checked={newListIsPublic}
                                            onChange={(e) => setNewListIsPublic(e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:focus:ring-blue-600"
                                        />
                                        <label htmlFor={`is_public_${gameId}`}
                                               className="ml-2 block text-xs text-gray-600 dark:text-gray-400">
                                            Make this list public
                                        </label>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </dialog>
        </div>
    );
}
