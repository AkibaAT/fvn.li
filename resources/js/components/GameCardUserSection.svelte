<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import http from '@/utils/http';
    import { isDialogBackdropClick } from '@/utils/dialog';

    let {
        gameId,
        gameName,
        isPaid = false,
        userProgress = null,
    }: {
        gameId: number;
        gameName: string;
        isPaid?: boolean;
        userProgress?: { id: number; game_id: number; user_id: number; receive_updates: boolean } | null;
    } = $props();

    interface VnList {
        id: number;
        name: string;
        type: string;
        is_default?: boolean;
        is_public?: boolean;
        user?: { id: number; name: string };
    }

    const auth = $derived((page as any)?.props?.auth);
    const isAuthenticated = $derived(Boolean(auth?.user));

    let allLists = $state<VnList[]>([]);
    let userLists = $state<VnList[]>([]);
    let showListDialog = $state(false);
    let showUserLists = $state(false);
    let isTogglingNotifications = $state(false);
    let notificationStatus = $state(untrack(() => userProgress?.receive_updates ?? false));
    let message = $state<{ type: 'success' | 'error'; text: string } | null>(null);
    let newListName = $state('');
    let newListIsPublic = $state(false);
    let isCreatingList = $state(false);
    let listStates = $state<Record<number, boolean>>({});
    let loadingStates = $state<Record<number, boolean>>({});
    let dialogEl = $state<HTMLDialogElement | undefined>(undefined);

    let messageTimeout: ReturnType<typeof setTimeout>;

    const showMessage = (text: string, type: 'success' | 'error') => {
        message = { text, type };
        clearTimeout(messageTimeout);
        messageTimeout = setTimeout(() => (message = null), 5000);
    };

    $effect(() => {
        if (!isAuthenticated) return;
        notificationStatus = userProgress?.receive_updates ?? false;
    });

    $effect(() => {
        if (showListDialog) {
            dialogEl?.showModal();
        } else {
            dialogEl?.close();
        }
    });

    const loadUserListsForDialog = async () => {
        if (!isAuthenticated) return;
        try {
            const [listsResponse, membershipsResponse] = await Promise.all([
                http.get('/browser-api/user/lists'),
                http.get(`/browser-api/games/${gameId}/lists`),
            ]);

            if (listsResponse.data?.success) {
                const lists = listsResponse.data.lists || [];
                allLists = lists;
                userLists = lists.filter((list: VnList) => list.user?.id === auth?.user?.id);

                const currentListIds = membershipsResponse.data?.list_ids || [];
                const initialStates: Record<number, boolean> = {};
                lists.forEach((list: VnList) => {
                    initialStates[list.id] = currentListIds.includes(list.id);
                });
                listStates = initialStates;
            }
        } catch (error) {
            console.error('Failed to load user lists:', error);
            showMessage('Failed to load user lists', 'error');
        }
    };

    const handleDefaultListToggle = async (listType: string) => {
        const listId = allLists.find((list) => list.type === listType)?.id;
        if (!listId) return;

        loadingStates = { ...loadingStates, [listId]: true };
        try {
            const response = await http.post(`/browser-api/games/${gameId}/add-to-list`, { list_type: listType });

            if (response.data?.success) {
                const isRemoved = response.data.message.includes('removed');
                if (!isRemoved) {
                    const newStates: Record<number, boolean> = {};
                    allLists.forEach((list) => {
                        if (list.type && ['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped'].includes(list.type)) {
                            newStates[list.id] = list.type === listType;
                        } else {
                            newStates[list.id] = listStates[list.id] || false;
                        }
                    });
                    listStates = newStates;
                } else {
                    listStates = { ...listStates, [listId]: false };
                }
                showMessage(response.data.message, 'success');
            }
        } catch (error: any) {
            const msg = error?.response?.data?.message || 'Failed to update list';
            showMessage(msg, 'error');
        } finally {
            loadingStates = { ...loadingStates, [listId]: false };
        }
    };

    const handleCustomListToggle = async (listId: number) => {
        loadingStates = { ...loadingStates, [listId]: true };
        try {
            const response = await http.post(`/browser-api/lists/${listId}/add-game`, { game_id: gameId });
            if (response.data?.success) {
                const isRemoved = response.data.message.includes('removed');
                listStates = { ...listStates, [listId]: !isRemoved };
                showMessage(response.data.message, 'success');
            }
        } catch (error: any) {
            const msg = error?.response?.data?.message || 'Failed to update list';
            showMessage(msg, 'error');
        } finally {
            loadingStates = { ...loadingStates, [listId]: false };
        }
    };

    const handleCreateList = async (e: Event) => {
        e.preventDefault();
        if (!newListName.trim() || isCreatingList) return;

        isCreatingList = true;
        try {
            const response = await http.post('/browser-api/vn-lists', {
                name: newListName.trim(),
                is_public: newListIsPublic,
                game_id: gameId,
            });
            if (response.data?.success) {
                const newList = response.data.list;
                allLists = [...allLists, newList];
                userLists = [...userLists, newList];
                listStates = { ...listStates, [newList.id]: true };
                newListName = '';
                newListIsPublic = false;
                showMessage(response.data.message, 'success');
            }
        } catch (error: any) {
            const msg = error?.response?.data?.message || 'Failed to create list';
            showMessage(msg, 'error');
        } finally {
            isCreatingList = false;
        }
    };

    const handleToggleNotifications = async () => {
        if (isTogglingNotifications || isPaid) return;

        isTogglingNotifications = true;
        try {
            const newStatus = !notificationStatus;
            const response = await http.patch(`/browser-api/user-progress/${gameId}/toggle-updates`, {
                receive_updates: newStatus,
            });
            if (response.data?.success) {
                notificationStatus = response.data.receive_updates;
                document.dispatchEvent(
                    new CustomEvent('show-toast', {
                        detail: {
                            message: `Notifications ${response.data.receive_updates ? 'enabled' : 'disabled'} for "${gameName}"`,
                            type: 'success',
                        },
                    }),
                );
            }
        } catch (error: any) {
            const msg = error?.response?.data?.message || error?.message || 'Failed to toggle notifications';
            document.dispatchEvent(
                new CustomEvent('show-toast', {
                    detail: { message: msg, type: 'error' },
                }),
            );
        } finally {
            isTogglingNotifications = false;
        }
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

    const primaryListType = $derived(
        (() => {
            const priorityOrder = ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped'];
            for (const type of priorityOrder) {
                if (userLists.some((list) => list.type === type && listStates[list.id])) {
                    return type;
                }
            }
            return null;
        })(),
    );

    const badgeColor = $derived(getBadgeColor(primaryListType));
    const userListsInGame = $derived(userLists.filter((list) => listStates[list.id]));

    const defaultListTypes = ['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped'];
    const customLists = $derived(allLists.filter((list) => list.type === 'custom' || !list.type).sort((a, b) => a.name.localeCompare(b.name)));

    const handleDialogBackdropClick = (e: MouseEvent) => {
        if (isDialogBackdropClick(dialogEl, e)) {
            dialogEl?.close();
            showListDialog = false;
        }
    };

    const handleDialogCancel = (event: Event) => {
        event.preventDefault();
        dialogEl?.close();
        showListDialog = false;
    };
</script>

{#if isAuthenticated}
    <div class="border-t border-gray-100 pt-3 dark:border-gray-700/50">
        <!-- Fixed Top Controls Row -->
        <div class="flex flex-wrap gap-2">
            <!-- Manage Lists Button -->
            <button
                onclick={async () => {
                    showListDialog = true;
                    await loadUserListsForDialog();
                }}
                class="flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                {userListsInGame.length > 0 ? 'Manage in Lists' : 'Add to Lists'}
            </button>

            <!-- User Lists Toggle Button -->
            {#if userListsInGame.length > 0}
                <button
                    onclick={() => (showUserLists = !showUserLists)}
                    class="flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                    <span>My Lists</span>
                    <span
                        class="rounded-full px-1.5 py-0.5 text-xs font-medium bg-{badgeColor}-100 text-{badgeColor}-800 dark:bg-{badgeColor}-900 dark:text-{badgeColor}-200"
                    >
                        {userListsInGame.length}
                    </span>
                    <svg
                        class="h-4 w-4 transition-transform {showUserLists ? 'rotate-180' : ''}"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            {/if}

            <!-- Notifications Toggle -->
            {#if !isPaid}
                <label class="flex cursor-pointer items-center gap-2">
                    <input
                        type="checkbox"
                        checked={notificationStatus}
                        onchange={handleToggleNotifications}
                        disabled={isTogglingNotifications}
                        class="sr-only"
                    />
                    <div
                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {notificationStatus
                            ? 'bg-blue-600'
                            : 'bg-gray-300 dark:bg-gray-600'} {isTogglingNotifications ? 'opacity-50' : ''}"
                    >
                        <span
                            class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform {notificationStatus
                                ? 'translate-x-5'
                                : 'translate-x-1'}"
                        ></span>
                    </div>
                    <span class="text-xs text-gray-600 dark:text-gray-400">
                        {isTogglingNotifications ? 'Updating...' : notificationStatus ? 'Notifications on' : 'Notifications off'}
                    </span>
                </label>
            {/if}
        </div>

        <!-- User Lists Section -->
        {#if userListsInGame.length > 0 && showUserLists}
            <div class="mt-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700/50 dark:bg-gray-800/50">
                    <h3 class="mb-3 text-sm font-medium text-gray-900 dark:text-gray-100">My Lists</h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {#each userListsInGame as list (list.id)}
                            <div class="flex flex-col rounded-lg border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <a
                                            href={route('lists.show', list.id)}
                                            class="block truncate font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400"
                                        >
                                            {list.name}
                                        </a>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {list.type?.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase())}
                                            {#if list.is_public}
                                                <span
                                                    class="ml-1 rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                                >
                                                    Public
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                    <span
                                        class="flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-medium bg-{getBadgeColor(
                                            list.type,
                                        )}-100 text-{getBadgeColor(list.type)}-800 dark:bg-{getBadgeColor(list.type)}-900 dark:text-{getBadgeColor(
                                            list.type,
                                        )}-200"
                                    >
                                        {list.type?.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase())}
                                    </span>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        {/if}

        <!-- Comprehensive List Management Dialog -->
        <dialog
            bind:this={dialogEl}
            oncancel={handleDialogCancel}
            class="m-auto w-full max-w-md rounded-lg bg-white p-6 shadow-xl backdrop:backdrop-blur-md dark:bg-gray-800 dark:text-gray-100"
            onclick={handleDialogBackdropClick}
        >
            <div class="mb-4 flex items-center justify-between overflow-hidden">
                <h3 class="line-clamp-3 text-lg font-medium break-words text-gray-900 dark:text-white">
                    Manage Lists for "{gameName}"
                </h3>
                <button
                    onclick={() => {
                        dialogEl?.close();
                        showListDialog = false;
                    }}
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    aria-label="Close dialog"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Message Area -->
            <div class="mb-4 h-6 text-center text-sm">
                {#if message}
                    <span class={message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}>
                        {message.text}
                    </span>
                {/if}
            </div>

            <div class="space-y-6">
                <div>
                    <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Default Lists</h4>
                    <div class="space-y-1">
                        {#each defaultListTypes as listType (listType)}
                            {@const list = allLists.find((l) => l.type === listType)}
                            {@const isInList = list ? listStates[list.id] : false}
                            {@const isLoading = list ? loadingStates[list.id] : false}
                            <button
                                onclick={() => handleDefaultListToggle(listType)}
                                disabled={isLoading}
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-sm transition-colors {isInList
                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                    : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600'}"
                            >
                                <div class="flex items-center gap-2">
                                    <span>{listType.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase())}</span>
                                    <span class="h-3 w-3 rounded-full bg-{getBadgeColor(listType)}-500"></span>
                                </div>
                                {#if isLoading}
                                    <span class="text-sm">Updating...</span>
                                {:else if isInList}
                                    <span class="text-sm font-medium">Remove</span>
                                {/if}
                            </button>
                        {/each}
                    </div>
                </div>

                <div>
                    <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Custom Lists</h4>
                    <div class="space-y-1">
                        {#each customLists as list (list.id)}
                            {@const isInList = listStates[list.id]}
                            {@const isLoading = loadingStates[list.id]}
                            <button
                                onclick={() => handleCustomListToggle(list.id)}
                                disabled={isLoading}
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-sm transition-colors {isInList
                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                    : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600'}"
                            >
                                <span>{list.name}</span>
                                {#if isLoading}
                                    <span class="text-sm">Updating...</span>
                                {:else if isInList}
                                    <span class="text-sm font-medium">Remove</span>
                                {/if}
                            </button>
                        {/each}
                    </div>

                    <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <form onsubmit={handleCreateList}>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    bind:value={newListName}
                                    placeholder="New list name"
                                    class="flex-1 rounded-md border-0 bg-gray-100 px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 dark:bg-gray-700"
                                    required
                                />
                                <button
                                    type="submit"
                                    disabled={!newListName.trim() || isCreatingList}
                                    class="rounded-md bg-blue-600 px-3 py-1 text-sm text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {isCreatingList ? 'Creating...' : 'Create & Add'}
                                </button>
                            </div>
                            <div class="mt-2 flex items-center">
                                <input
                                    type="checkbox"
                                    id="is_public_{gameId}"
                                    bind:checked={newListIsPublic}
                                    class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:focus:ring-blue-600"
                                />
                                <label for="is_public_{gameId}" class="ml-2 block text-xs text-gray-600 dark:text-gray-400">
                                    Make this list public
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </dialog>
    </div>
{/if}
