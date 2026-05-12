<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import http from '@/utils/http';
    import { Badge, Button, Dialog, TextInput, Checkbox } from '@/components/ui';
    import { formatListType, listTypeDotClass, listTypeTone } from '@/components/ui/tones';

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

    const auth = $derived(($page as any).props?.auth);
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

    const badgeTone = $derived(listTypeTone(primaryListType ?? undefined));
    const userListsInGame = $derived(userLists.filter((list) => listStates[list.id]));

    const defaultListTypes = ['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped'];
    const customLists = $derived(allLists.filter((list) => list.type === 'custom' || !list.type).sort((a, b) => a.name.localeCompare(b.name)));

    const closeListDialog = () => {
        showListDialog = false;
    };
</script>

{#if isAuthenticated}
    <div class="border-t border-gray-100 pt-3 dark:border-gray-700/50">
        <!-- Fixed Top Controls Row -->
        <div class="flex flex-wrap gap-2">
            <!-- Manage Lists Button -->
            <Button
                onclick={async () => {
                    showListDialog = true;
                    await loadUserListsForDialog();
                }}
                size="sm"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                {userListsInGame.length > 0 ? 'Manage in Lists' : 'Add to Lists'}
            </Button>

            <!-- User Lists Toggle Button -->
            {#if userListsInGame.length > 0}
                <Button onclick={() => (showUserLists = !showUserLists)} variant="soft" tone="neutral" size="sm">
                    <span>My Lists</span>
                    <Badge tone={badgeTone} size="sm">{userListsInGame.length}</Badge>
                    <svg
                        class="h-4 w-4 transition-transform {showUserLists ? 'rotate-180' : ''}"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </Button>
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
                                            {formatListType(list.type)}
                                            {#if list.is_public}
                                                <Badge tone="primary" size="sm" class="ml-1">Public</Badge>
                                            {/if}
                                        </div>
                                    </div>
                                    <Badge tone={listTypeTone(list.type)} size="sm" class="flex-shrink-0">{formatListType(list.type)}</Badge>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        {/if}

        <!-- Comprehensive List Management Dialog -->
        <Dialog open={showListDialog} onClose={closeListDialog} title={`Manage Lists for "${gameName}"`} size="sm">
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
                            <Button
                                onclick={() => handleDefaultListToggle(listType)}
                                disabled={isLoading}
                                variant={isInList ? 'solid' : 'soft'}
                                tone={isInList ? 'primary' : 'neutral'}
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-sm"
                            >
                                <div class="flex items-center gap-2">
                                    <span>{formatListType(listType)}</span>
                                    <span class="h-3 w-3 rounded-full {listTypeDotClass(listType)}"></span>
                                </div>
                                {#if isLoading}
                                    <span class="text-sm">Updating...</span>
                                {:else if isInList}
                                    <span class="text-sm font-medium">Remove</span>
                                {/if}
                            </Button>
                        {/each}
                    </div>
                </div>

                <div>
                    <h4 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Custom Lists</h4>
                    <div class="space-y-1">
                        {#each customLists as list (list.id)}
                            {@const isInList = listStates[list.id]}
                            {@const isLoading = loadingStates[list.id]}
                            <Button
                                onclick={() => handleCustomListToggle(list.id)}
                                disabled={isLoading}
                                variant={isInList ? 'solid' : 'soft'}
                                tone={isInList ? 'primary' : 'neutral'}
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-sm"
                            >
                                <span>{list.name}</span>
                                {#if isLoading}
                                    <span class="text-sm">Updating...</span>
                                {:else if isInList}
                                    <span class="text-sm font-medium">Remove</span>
                                {/if}
                            </Button>
                        {/each}
                    </div>

                    <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <form onsubmit={handleCreateList}>
                            <div class="flex gap-2">
                                <TextInput
                                    type="text"
                                    bind:value={newListName}
                                    placeholder="New list name"
                                    fieldClass="flex-1"
                                    class="border-0 bg-gray-100 py-1 dark:bg-gray-700"
                                    required
                                />
                                <Button type="submit" disabled={!newListName.trim() || isCreatingList} size="sm">
                                    {isCreatingList ? 'Creating...' : 'Create & Add'}
                                </Button>
                            </div>
                            <div class="mt-2 flex items-center">
                                <Checkbox
                                    id="is_public_{gameId}"
                                    bind:checked={newListIsPublic}
                                    label="Make this list public"
                                    class="dark:bg-gray-800"
                                />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
{/if}
