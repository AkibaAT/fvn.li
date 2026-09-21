<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
    import { notify } from '@/components/Toast.svelte';
    import { getErrorMessage, useAsyncAction } from '@/utils/async-action.svelte';
    import { toast } from '@/utils/toast';
    import { page } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import {
        addGameToCustomList,
        addGameToDefaultList,
        fetchGameListMemberships,
        fetchUserLists,
        storeVnList,
        toggleUserProgressUpdates,
    } from '@/api/lists';
    import { Button, Dialog, TextInput, Checkbox } from '@/components/ui';
    import { formatListType, listTypeDotClass } from '@/components/ui/tones';

    let {
        gameId,
        gameName,
        isPaid = false,
        userProgress = null,
        listMemberships = [],
    }: {
        gameId: number;
        gameName: string;
        isPaid?: boolean;
        listMemberships?: Array<{ list_id: number; name: string; type: string; is_default: boolean }>;
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

    const auth = $derived((page as any).props?.auth);
    const isAuthenticated = $derived(Boolean(auth?.user));

    let allLists = $state<VnList[]>([]);
    let userLists = $state<VnList[]>(untrack(() => listMemberships.map((list) => ({ ...list, id: list.list_id }))));
    let showListDialog = $state(false);
    let showUserLists = $state(false);
    const toggleNotificationsAction = useAsyncAction();
    let notificationStatus = $state(untrack(() => userProgress?.receive_updates ?? false));
    let newListName = $state('');
    let newListIsPublic = $state(false);
    const createAction = useAsyncAction();
    let listStates = $state<Record<number, boolean>>(untrack(() => Object.fromEntries(listMemberships.map((list) => [list.list_id, true]))));
    let loadingStates = $state<Record<number, boolean>>({});

    let defaultListQueue = Promise.resolve();

    const refreshUserData = () => refreshPage(['game', 'games', 'vnLists', 'vnList', 'availableLists']);

    $effect(() => {
        userLists = listMemberships.map((list) => ({ ...list, id: list.list_id }));
        listStates = Object.fromEntries(listMemberships.map((list) => [list.list_id, true]));
    });

    $effect(() => {
        if (!isAuthenticated) return;
        notificationStatus = userProgress?.receive_updates ?? false;
    });

    const loadUserListsForDialog = async () => {
        if (!isAuthenticated) return;
        try {
            const [lists, currentListIds] = await Promise.all([fetchUserLists(), fetchGameListMemberships(gameId)]);

            allLists = lists;
            userLists = lists;

            const initialStates: Record<number, boolean> = {};
            lists.forEach((list: VnList) => {
                initialStates[list.id] = currentListIds.includes(list.id);
            });
            listStates = initialStates;
        } catch (error) {
            console.error('Failed to load user lists:', error);
            toast.error('Failed to load user lists');
        }
    };

    const handleDefaultListToggle = (listType: string) => {
        defaultListQueue = defaultListQueue.then(() => saveDefaultListToggle(listType));
        return defaultListQueue;
    };

    const saveDefaultListToggle = async (listType: string) => {
        const listId = allLists.find((list) => list.type === listType)?.id;
        if (!listId) return;

        loadingStates = { ...loadingStates, [listId]: true };
        try {
            const message = await addGameToDefaultList(gameId, listType);
            if (!(await refreshUserData())) return;

            const isRemoved = message.includes('removed');
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
            toast.success(message);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Failed to update list'));
        } finally {
            loadingStates = { ...loadingStates, [listId]: false };
        }
    };

    const handleCustomListToggle = async (listId: number) => {
        loadingStates = { ...loadingStates, [listId]: true };
        try {
            const message = await addGameToCustomList(listId, gameId);
            if (!(await refreshUserData())) return;
            const isRemoved = message.includes('removed');
            listStates = { ...listStates, [listId]: !isRemoved };
            toast.success(message);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Failed to update list'));
        } finally {
            loadingStates = { ...loadingStates, [listId]: false };
        }
    };

    const handleCreateList = async (e: Event) => {
        e.preventDefault();
        if (!newListName.trim() || createAction.isLoading) return;

        const data = await createAction.run(
            async () => {
                const data = await storeVnList({
                    name: newListName.trim(),
                    is_public: newListIsPublic,
                    game_id: gameId,
                });
                if (!(await refreshUserData())) return null;
                return data;
            },
            { fallbackError: 'Failed to create list' },
        );
        if (!data) return;
        const newList = data.list;
        allLists = [...allLists, newList];
        userLists = [...userLists.filter((list) => list.id !== newList.id), newList];
        listStates = { ...listStates, [newList.id]: true };
        newListName = '';
        newListIsPublic = false;
        toast.success(data.message);
    };

    const handleToggleNotifications = async (event: Event) => {
        (event.currentTarget as HTMLInputElement).checked = notificationStatus;
        if (toggleNotificationsAction.isLoading || isPaid) return;

        const data = await toggleNotificationsAction.run(
            async () => {
                const data = await toggleUserProgressUpdates(gameId, !notificationStatus);
                if (!(await refreshUserData())) return null;
                return data;
            },
            { fallbackError: 'Failed to toggle notifications' },
        );
        if (!data) return;
        notificationStatus = data.receive_updates;
        notify(`Notifications ${data.receive_updates ? 'enabled' : 'disabled'} for "${gameName}"`, 'success');
    };

    const userListsInGame = $derived(userLists.filter((list) => listStates[list.id]));

    const defaultListTypes = ['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped'];
    const customLists = $derived(allLists.filter((list) => list.type === 'custom' || !list.type).sort((a, b) => a.name.localeCompare(b.name)));

    const closeListDialog = () => {
        showListDialog = false;
    };
</script>

{#if isAuthenticated}
    <div class="mt-3">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-ui leading-none">
            <button
                type="button"
                onclick={async () => {
                    showListDialog = true;
                    await loadUserListsForDialog();
                }}
                class="cursor-pointer text-fg-muted transition-colors hover:text-fg"
            >
                {userListsInGame.length > 0 ? 'Manage in Lists' : 'Add to Lists'}
            </button>

            {#if userListsInGame.length > 0}
                <button
                    type="button"
                    onclick={() => (showUserLists = !showUserLists)}
                    aria-expanded={showUserLists}
                    class="inline-flex cursor-pointer items-center gap-1 text-fg-muted transition-colors hover:text-fg"
                >
                    <span>My Lists</span>
                    <span>{userListsInGame.length}</span>
                    <ChevronDownIcon class="h-3 w-3 transition-transform {showUserLists ? 'rotate-180' : ''}" />
                </button>
            {/if}

            {#if !isPaid}
                <label class="flex cursor-pointer items-center gap-1.5 text-fg-muted transition-colors hover:text-fg">
                    <input
                        type="checkbox"
                        checked={notificationStatus}
                        onchange={handleToggleNotifications}
                        disabled={toggleNotificationsAction.isLoading}
                        class="sr-only"
                    />
                    <span
                        class="relative inline-flex h-3.5 w-6 items-center rounded-full transition-colors {notificationStatus
                            ? 'bg-fg'
                            : 'bg-border'} {toggleNotificationsAction.isLoading ? 'opacity-50' : ''}"
                    >
                        <span
                            class="inline-block h-2.5 w-2.5 transform rounded-full bg-surface transition-transform {notificationStatus
                                ? 'translate-x-3'
                                : 'translate-x-0.5'}"
                        ></span>
                    </span>
                    <span>
                        {toggleNotificationsAction.isLoading ? 'Updating...' : notificationStatus ? 'Notifications on' : 'Notifications off'}
                    </span>
                </label>
            {/if}
        </div>

        {#if userListsInGame.length > 0 && showUserLists}
            <div class="mt-3">
                <div class="rounded-lg border border-border bg-surface-alt p-4">
                    <h3 class="mb-3 text-ui font-semibold text-fg">My Lists</h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {#each userListsInGame as list (list.id)}
                            <div class="flex flex-col rounded-md border border-border bg-surface p-3">
                                <div class="min-w-0">
                                    <a href={route('lists.show', list.id)} class="block truncate text-ui font-medium text-fg hover:underline">
                                        {list.name}
                                    </a>
                                    <div class="mt-1 text-xs text-fg-faint">
                                        {formatListType(list.type)}
                                        {#if list.is_public}<span class="ml-1">· Public</span>{/if}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        {/if}

        <Dialog open={showListDialog} onClose={closeListDialog} title={`Manage Lists for "${gameName}"`} size="sm">
            <div class="space-y-6">
                <div>
                    <h4 class="mb-2 text-ui font-medium text-fg-muted">Default Lists</h4>
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
                    <h4 class="mb-2 text-ui font-medium text-fg-muted">Custom Lists</h4>
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

                    <div class="mt-4 border-t border-border pt-4">
                        <form onsubmit={handleCreateList}>
                            <div class="flex gap-2">
                                <TextInput
                                    type="text"
                                    bind:value={newListName}
                                    placeholder="New list name"
                                    fieldClass="flex-1"
                                    class="border-0 bg-surface-alt py-1"
                                    required
                                />
                                <Button type="submit" disabled={!newListName.trim() || createAction.isLoading} size="sm">
                                    {createAction.isLoading ? 'Creating...' : 'Create & Add'}
                                </Button>
                            </div>
                            <div class="mt-2 flex items-center">
                                <Checkbox id="is_public_{gameId}" bind:checked={newListIsPublic} label="Make this list public" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
{/if}
