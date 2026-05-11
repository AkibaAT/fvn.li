<script lang="ts">
    import type { Attachment } from 'svelte/attachments';
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import { notify } from '@/components/Toast.svelte';
    import { authenticatedFetch } from '@/utils/csrf';
    import VersionComparisonModal from '@/components/VersionComparisonModal.svelte';
    import { getListTypeConfig, listStatusConfig, getStatusBadgeConfig } from '@/utils/status-indicators';
    import SortableList from '@/components/drag-drop/SortableList.svelte';
    import DragHandle from '@/components/drag-drop/DragHandle.svelte';

    interface GameVersion {
        id: number;
        version: string;
        published_at: string;
    }
    interface GameRating {
        id: number;
        game_id: number;
        user_id: number;
        rating: number;
        is_reviewed: boolean;
    }
    interface Game {
        id: number;
        name: string;
        effective_name: string;
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
        ratings?: GameRating[];
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
        metaTags?: { title?: string; description?: string };
        versionHasCharacterStats?: Record<number, boolean>;
    }

    let { vnList, isOwner, availableLists = [], metaTags, versionHasCharacterStats = {} }: ListShowProps = $props();

    const statusConfig = $derived(getListTypeConfig(vnList?.type || 'custom'));
    const color = $derived(statusConfig.color);

    let entries = $state<VnListEntry[]>(untrack(() => vnList.entries));
    let isPublic = $state<boolean>(untrack(() => vnList.is_public));
    let listData = $state(untrack(() => ({ name: vnList.name, description: vnList.description })));
    let isToggleVisibilityLoading = $state(false);
    let isEditingList = $state(false);
    let isListSaveLoading = $state(false);
    let isListDeleteLoading = $state(false);
    let listFormData = $state(untrack(() => ({ name: vnList.name, description: vnList.description || '' })));
    let showVersionComparison = $state(false);
    let comparisonVersions = $state<{ gameId: number; fromVersionId: number; toVersionId: number } | null>(null);
    let editingEntryId = $state<number | null>(null);
    let movingEntryId = $state<number | null>(null);
    let isDesktopViewport = $state(false);
    let entryFormData = $state({
        game_version_id: '',
        personal_notes: '',
        private_notes: '',
        started_at: '',
        completed_at: '',
        target_list_id: '',
    });
    let entryFormLoading = $state(false);

    const getOptimizedThumbnail = (game: Game) => {
        if (game.optimized_thumbnails?.default?.path) return `/storage/${game.optimized_thumbnails.default.path}`;
        return game.thumb_url || '';
    };

    const freeGames = $derived(entries.filter((e) => !e.game.is_paid));
    const allFreeGamesReceiveUpdates = $derived(freeGames.length > 0 && freeGames.every((e) => e.game.user_progress?.[0]?.receive_updates ?? false));

    const handleSaveList = async () => {
        isListSaveLoading = true;
        try {
            const response = await authenticatedFetch(route('api.vn-lists.update', vnList.id), {
                method: 'PUT',
                body: JSON.stringify({ ...listFormData, is_public: isPublic }),
            });
            if (response.ok) {
                const data = await response.json();
                isEditingList = false;
                if (data.vnList) {
                    listData = { name: data.vnList.name, description: data.vnList.description };
                    isPublic = data.vnList.is_public;
                }
                document.title = `${listFormData.name} - ${document.title.split(' - ').slice(1).join(' - ')}`;
                notify('List updated successfully', 'success');
            } else {
                throw new Error('Failed to update list');
            }
        } catch (error) {
            console.error('Error updating list:', error);
            notify('Failed to update list', 'error');
        } finally {
            isListSaveLoading = false;
        }
    };

    const handleCancelListEdit = () => {
        listFormData = { name: listData.name, description: listData.description || '' };
        isEditingList = false;
    };

    const handleDeleteList = async () => {
        if (!confirm('Are you sure you want to delete this list? This action cannot be undone.')) return;
        isListDeleteLoading = true;
        try {
            const response = await authenticatedFetch(route('api.vn-lists.destroy', vnList.id), { method: 'DELETE' });
            if (response.ok) {
                notify('List deleted successfully', 'success');
                window.location.href = route('lists.index');
            } else {
                throw new Error('Failed to delete list');
            }
        } catch (error) {
            console.error('Error deleting list:', error);
            notify('Failed to delete list', 'error');
        } finally {
            isListDeleteLoading = false;
        }
    };

    const handleToggleVisibility = async () => {
        isToggleVisibilityLoading = true;
        try {
            const response = await authenticatedFetch(route('api.vn-lists.toggle-visibility', vnList.id), { method: 'POST' });
            if (response.ok) {
                const data = await response.json();
                isPublic = data.is_public;
                notify(data.message || 'List visibility updated', 'success');
            } else {
                throw new Error('Failed to toggle visibility');
            }
        } catch (error) {
            console.error('Error toggling visibility:', error);
            notify('Failed to update visibility', 'error');
        } finally {
            isToggleVisibilityLoading = false;
        }
    };

    const handleCompareVersions = (gameId: number, fromVersionId: number, toVersionId: number) => {
        comparisonVersions = { gameId, fromVersionId, toVersionId };
        showVersionComparison = true;
    };
    const handleCloseVersionComparison = () => {
        showVersionComparison = false;
        comparisonVersions = null;
    };

    const handleEntryRemove = async (entryId: number) => {
        if (!confirm('Are you sure you want to remove this game from the list?')) return;
        try {
            const response = await authenticatedFetch(route('api.list-entries.destroy', entryId), { method: 'DELETE' });
            if (response.ok) {
                entries = entries.filter((e) => e.id !== entryId);
            }
        } catch (error) {
            console.error('Error removing entry:', error);
        }
    };

    const handleEntryUpdate = (entryId: number, updatedData?: { entry?: Partial<VnListEntry>; progress?: Partial<UserGameProgress> }) => {
        if (updatedData) {
            entries = entries.map((entry) => {
                if (entry.id === entryId) {
                    const updatedEntry = { ...entry };
                    if (updatedData.entry) {
                        Object.assign(updatedEntry, updatedData.entry);
                    }
                    if (updatedData.progress && updatedEntry.game.user_progress) {
                        updatedEntry.game.user_progress = [{ ...updatedEntry.game.user_progress[0], ...updatedData.progress }];
                    } else if (updatedData.progress) {
                        updatedEntry.game.user_progress = [{ id: 0, user_id: 0, game_id: updatedEntry.game.id, ...updatedData.progress }];
                    }
                    return updatedEntry;
                }
                return entry;
            });
        }
        notify('Entry updated', 'success');
    };

    const getEntryVersionValue = (entry: VnListEntry) => {
        const userProgress = entry.game.user_progress?.[0] || entry.user_progress;
        const versionId = userProgress?.game_version_id ?? entry.game_version_id;

        return versionId === null || versionId === undefined ? '' : String(versionId);
    };

    const startEditing = (entry: VnListEntry) => {
        const userProgress = entry.game.user_progress?.[0] || entry.user_progress;
        entryFormData = {
            game_version_id: getEntryVersionValue(entry),
            personal_notes: userProgress?.personal_notes || entry.personal_notes || entry.notes || '',
            private_notes: entry.private_notes || '',
            started_at: userProgress?.started_at ? userProgress.started_at.split('T')[0] : entry.started_at ? entry.started_at.split('T')[0] : '',
            completed_at: userProgress?.completed_at
                ? userProgress.completed_at.split('T')[0]
                : entry.completed_at
                  ? entry.completed_at.split('T')[0]
                  : '',
            target_list_id: '',
        };
        editingEntryId = entry.id;
        movingEntryId = null;
    };

    const handleSaveEntry = async (entryId: number) => {
        entryFormLoading = true;
        try {
            const response = await authenticatedFetch(route('api.list-entries.update', entryId), {
                method: 'PUT',
                body: JSON.stringify(entryFormData),
            });
            if (response.ok) {
                const data = await response.json();
                editingEntryId = null;
                handleEntryUpdate(entryId, data);
            }
        } catch (error) {
            console.error('Error updating entry:', error);
        } finally {
            entryFormLoading = false;
        }
    };

    const startMoving = (entry: VnListEntry) => {
        entryFormData = { ...entryFormData, target_list_id: '' };
        movingEntryId = entry.id;
        editingEntryId = null;
    };

    const handleMoveEntry = async (entryId: number) => {
        if (!entryFormData.target_list_id) return;
        entryFormLoading = true;
        try {
            const response = await authenticatedFetch(route('api.list-entries.move', entryId), {
                method: 'POST',
                body: JSON.stringify({ target_list_id: entryFormData.target_list_id }),
            });
            if (response.ok) {
                entries = entries.filter((e) => e.id !== entryId);
                movingEntryId = null;
                notify('Entry moved successfully', 'success');
            }
        } catch (error) {
            console.error('Error moving entry:', error);
        } finally {
            entryFormLoading = false;
        }
    };

    const handleToggleNotification = async (game: Game, newStatus: boolean) => {
        try {
            const response = await authenticatedFetch(route('api.user-progress.toggle-updates', game.id), {
                method: 'PATCH',
                body: JSON.stringify({ receive_updates: newStatus }),
            });
            if (response.ok) {
                const data = await response.json();
                entries = entries.map((entry) => {
                    if (entry.game.id === game.id) {
                        return {
                            ...entry,
                            game: {
                                ...entry.game,
                                user_progress:
                                    entry.game.user_progress && entry.game.user_progress.length > 0
                                        ? entry.game.user_progress.map((p) => ({ ...p, receive_updates: data.receive_updates }))
                                        : [{ id: 0, user_id: 0, game_id: game.id, receive_updates: data.receive_updates }],
                            },
                        };
                    }
                    return entry;
                });
                notify(data.message || `Notifications ${data.receive_updates ? 'enabled' : 'disabled'} for "${game.effective_name}"`, 'success');
            } else {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to toggle notifications');
            }
        } catch (error) {
            console.error('Error toggling notifications:', error);
            notify('Failed to update notifications', 'error');
        }
    };

    const handleToggleAllNotifications = async () => {
        const newStatus = !allFreeGamesReceiveUpdates;
        try {
            const response = await authenticatedFetch(route('api.vn-lists.toggle-all-updates', vnList.id), {
                method: 'PATCH',
                body: JSON.stringify({ receive_updates: newStatus }),
            });
            if (response.ok) {
                const data = await response.json();
                notify(data.message || `Notifications ${newStatus ? 'enabled' : 'disabled'} for all games`, 'success');
                if (data.updated_game_ids && Array.isArray(data.updated_game_ids)) {
                    entries = entries.map((entry) => {
                        if (data.updated_game_ids.includes(entry.game.id)) {
                            return {
                                ...entry,
                                game: {
                                    ...entry.game,
                                    user_progress:
                                        entry.game.user_progress && entry.game.user_progress.length > 0
                                            ? entry.game.user_progress.map((p) => ({ ...p, receive_updates: data.receive_updates }))
                                            : [{ id: 0, user_id: 0, game_id: entry.game.id, receive_updates: data.receive_updates }],
                                },
                            };
                        }
                        return entry;
                    });
                }
            }
        } catch (error) {
            console.error('Error toggling all notifications:', error);
            notify('Failed to update notifications', 'error');
        }
    };

    const handleReorder = async (newEntries: VnListEntry[]) => {
        const originalEntries = [...entries];
        entries = newEntries;
        try {
            const response = await authenticatedFetch(route('api.lists.reorder', vnList.id), {
                method: 'POST',
                body: JSON.stringify({ entry_ids: newEntries.map((e) => e.id) }),
            });
            if (response.ok) {
                const data = await response.json();
                notify(data.message || 'List order updated', 'success');
            } else {
                entries = originalEntries;
                throw new Error('Failed to update order');
            }
        } catch (error) {
            console.error('Error updating order:', error);
            entries = originalEntries;
            notify('Failed to update order', 'error');
        }
    };

    const availableListsForMove = (currentListId: number) => availableLists.filter((list) => list.id !== currentListId);

    const borderColorClass = $derived(
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
                    : 'border-gray-500',
    );

    $effect(() => {
        if (typeof window === 'undefined') return;

        const mediaQuery = window.matchMedia('(min-width: 1024px)');
        const updateViewport = (event?: MediaQueryList | MediaQueryListEvent) => {
            isDesktopViewport = event ? event.matches : mediaQuery.matches;
        };

        updateViewport();
        mediaQuery.addEventListener('change', updateViewport);

        return () => mediaQuery.removeEventListener('change', updateViewport);
    });
</script>

<svelte:head>
    <title>{metaTags?.title || (isEditingList ? listFormData.name : listData.name)}</title>
</svelte:head>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="mb-6 rounded-lg border-l-4 bg-white p-4 shadow-sm md:p-6 dark:bg-gray-800 {borderColorClass}">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="text-xl font-bold text-gray-900 md:text-2xl dark:text-white">
                    {isEditingList ? listFormData.name : listData.name}
                </h1>
                {#if isEditingList ? listFormData.description : listData.description}
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {#each (isEditingList ? listFormData.description : listData.description)!.split('\n') as line, i (i)}
                            {line}{#if i < (isEditingList ? listFormData.description : listData.description)!.split('\n').length - 1}<br />{/if}
                        {/each}
                    </p>
                {/if}
                {#if !isOwner && vnList?.user?.name}
                    <div class="mt-2 text-sm text-gray-500">
                        By <Link href={route('lists.user-public', vnList.user.id)} class="text-blue-600 hover:underline dark:text-blue-400"
                            >{vnList.user.name}</Link
                        >
                    </div>
                {/if}
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {#if !vnList.is_default}
                    {@const badge = getStatusBadgeConfig(vnList.type, listStatusConfig)}
                    {#if badge}
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium {badge.classes}"
                            aria-label={badge.ariaLabel}
                        >
                            {#if badge.icon}<span>{badge.icon}</span>{/if}
                            {badge.label}
                        </span>
                    {/if}
                {/if}
                {#if isPublic}
                    <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                        >Public</span
                    >
                {/if}
                {#if isOwner}
                    <div class="flex gap-2">
                        <Link
                            href={route('lists.index')}
                            class="inline-flex items-center rounded-md border border-transparent bg-gray-200 px-3 py-1 text-xs font-semibold tracking-widest text-gray-800 uppercase hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                            >Back to Lists</Link
                        >
                        <button
                            onclick={handleToggleVisibility}
                            disabled={isToggleVisibilityLoading}
                            class="inline-flex items-center rounded-md border border-transparent px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase disabled:opacity-50 {isPublic
                                ? 'bg-blue-500 hover:bg-blue-400'
                                : 'bg-gray-500 hover:bg-gray-400'}"
                        >
                            {isPublic ? 'Make Private' : 'Make Public'}
                        </button>
                        <button
                            onclick={() => {
                                isEditingList = !isEditingList;
                            }}
                            class="inline-flex items-center rounded-md border border-transparent bg-yellow-500 px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase hover:bg-yellow-400"
                        >
                            {isEditingList ? 'Cancel Edit' : 'Edit List'}
                        </button>
                        {#if vnList.type === 'custom' && !vnList.is_default}
                            <button
                                onclick={handleDeleteList}
                                disabled={isListDeleteLoading}
                                class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase hover:bg-red-700 disabled:opacity-50"
                            >
                                {isListDeleteLoading ? 'Deleting...' : 'Delete List'}
                            </button>
                        {/if}
                    </div>
                {:else}
                    <Link
                        href={route('lists.public')}
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-200 px-3 py-1 text-xs font-semibold tracking-widest text-gray-800 uppercase hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                        >Back to Public Lists</Link
                    >
                {/if}
            </div>
        </div>
    </div>

    <!-- List Edit Form -->
    {#if isEditingList && isOwner}
        <div class="mb-4 rounded-lg border-l-4 border-yellow-500 bg-white p-4 shadow-sm dark:bg-gray-800">
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Edit List</h3>
            <div class="space-y-4">
                <div>
                    <label for="list-name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">List Name</label>
                    <input
                        id="list-name"
                        type="text"
                        bind:value={listFormData.name}
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Enter list name"
                    />
                </div>
                <div>
                    <label for="list-description" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >Description (Optional)</label
                    >
                    <textarea
                        id="list-description"
                        bind:value={listFormData.description}
                        rows={3}
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Enter list description"
                    ></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button
                        onclick={handleCancelListEdit}
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >Cancel</button
                    >
                    <button
                        onclick={handleSaveList}
                        disabled={isListSaveLoading || !listFormData.name.trim()}
                        class="inline-flex items-center rounded-md border border-transparent bg-yellow-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-yellow-700 disabled:opacity-50"
                    >
                        {isListSaveLoading ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- List Stats Card -->
    <div class="mb-4 rounded-lg bg-white p-4 shadow-sm md:pr-6 md:pl-7 dark:bg-gray-800">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">List Entries ({entries.length})</h2>
            {#if isOwner && freeGames.length > 0}
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Notifications for all free entries:</span>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" checked={allFreeGamesReceiveUpdates} onchange={handleToggleAllNotifications} class="peer sr-only" />
                        <div
                            class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:-translate-x-full dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"
                        ></div>
                        <span class="sr-only">
                            {allFreeGamesReceiveUpdates
                                ? 'Turn off notifications for all free entries'
                                : 'Turn on notifications for all free entries'}
                        </span>
                    </label>
                </div>
            {/if}
        </div>
    </div>

    {#if entries.length === 0}
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-700">
            <p class="text-gray-500 dark:text-gray-400">No visual novels in this list yet.</p>
            {#if isOwner}
                <p class="mt-2 text-gray-500 dark:text-gray-400">Browse games and add them to your list!</p>
            {/if}
        </div>
    {:else}
        <!-- Desktop Table Header -->
        <div class="hidden rounded-t-lg bg-gray-100 p-3 pr-5 text-sm font-medium text-gray-500 uppercase lg:flex dark:bg-gray-700 dark:text-gray-300">
            {#if isOwner}<div class="w-8"></div>{/if}
            <div class="mr-2 w-20"></div>
            <div class="flex-grow">Title</div>
            <div class="w-52">Version</div>
            <div class="w-30">Started</div>
            {#if vnList.type === 'custom' || vnList.type === 'completed'}
                <div class="w-28">Completed</div>
            {/if}
            {#if isOwner}
                <div class="w-20">Actions</div>
                <div class="w-30 pr-1.5 text-right">Notifications</div>
            {/if}
        </div>

        <!-- Entry List -->
        {#if isOwner}
            <SortableList
                items={entries}
                onReorder={(newItems) => handleReorder(newItems)}
                class="space-y-3 text-gray-700 md:grid md:grid-cols-2 md:gap-3 md:space-y-0 lg:block lg:space-y-3 dark:text-gray-300"
            >
                {#snippet children(entry, _index, _canDrag, handleAttachment)}
                    {@render gameEntry(entry, handleAttachment)}
                {/snippet}
            </SortableList>
        {:else}
            <div class="space-y-3 text-gray-700 md:grid md:grid-cols-2 md:gap-3 md:space-y-0 lg:block lg:space-y-3 dark:text-gray-300">
                {#each entries as entry (entry.id)}
                    {@render gameEntry(entry)}
                {/each}
            </div>
        {/if}
    {/if}
</div>

<!-- Version Comparison Modal -->
<VersionComparisonModal
    isOpen={showVersionComparison}
    onClose={handleCloseVersionComparison}
    gameId={comparisonVersions?.gameId || 0}
    fromVersionId={comparisonVersions?.fromVersionId}
    toVersionId={comparisonVersions?.toVersionId}
/>

{#snippet gameEntry(entry: VnListEntry, handleAttachment: Attachment<HTMLElement> | null = null)}
    {@const game = entry.game}
    {@const userProgress = game.user_progress?.[0] || entry.user_progress}
    {@const currentVersion = userProgress?.game_version || entry.game_version || null}
    {@const hasUpdate = game.latest_version && currentVersion && game.latest_version.id !== currentVersion.id}
    {@const isEditing = editingEntryId === entry.id}
    {@const isMoving = movingEntryId === entry.id}
    {@const moveLists = availableListsForMove(vnList.id)}

    <div class="rounded-lg bg-white shadow-sm md:rounded-lg lg:rounded-none dark:bg-gray-800">
        <!-- Desktop View -->
        <div class="hidden items-center p-3 pr-5 lg:flex">
            {#if isOwner}
                <div class="mr-3 flex w-8 shrink-0">
                    <DragHandle size="md" class="drag-handle" attachment={isDesktopViewport ? (handleAttachment ?? undefined) : undefined} />
                </div>
            {:else}
                <div class="w-8 shrink-0"></div>
            {/if}

            <!-- Thumbnail -->
            <div class="mr-3 w-20 shrink-0">
                <Link href={route('games.show', game.slug)}>
                    <img src={getOptimizedThumbnail(game)} alt={game.effective_name} class="h-16 w-16 rounded object-cover" loading="lazy" />
                </Link>
            </div>

            <!-- Title -->
            <div class="flex-grow">
                <div class="flex items-center gap-2">
                    <Link href={route('games.show', game.slug)} class="font-medium break-words text-blue-600 hover:underline dark:text-blue-400"
                        >{game.effective_name}</Link
                    >
                    {#if game.ratings && game.ratings.length > 0}
                        <Link
                            href={route('reviews.show', game.ratings[0].id)}
                            class="inline-flex items-center gap-0.5 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:hover:bg-yellow-800"
                            title={game.ratings[0].is_reviewed ? 'View review' : 'View rating'}
                        >
                            <svg class="h-3 w-3 fill-current" viewBox="0 0 20 20">
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                />
                            </svg>
                            {game.ratings[0].rating}
                        </Link>
                    {/if}
                </div>
                {#if userProgress?.personal_notes || entry.personal_notes || entry.notes}
                    <div class="max-w-md truncate text-xs italic">
                        <span class="text-gray-500 dark:text-gray-400">Public:</span> "{userProgress?.personal_notes ||
                            entry.personal_notes ||
                            entry.notes}"
                    </div>
                {/if}
                {#if isOwner && entry.private_notes}
                    <div class="mt-1 max-w-md truncate text-xs italic">
                        <span class="text-blue-500 dark:text-blue-400">Private:</span> "{entry.private_notes}"
                    </div>
                {/if}
            </div>

            <!-- Version -->
            <div class="w-52">
                {#if currentVersion}
                    <div class="border-l-4 pl-3 {hasUpdate ? 'border-yellow-500' : 'border-transparent'}">
                        v{currentVersion.version}
                        <span class="text-gray-400">({new Date(currentVersion.published_at).toLocaleDateString()})</span>
                        {#if hasUpdate}
                            <div class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
                                Latest: v{game.latest_version?.version}
                                <span class="text-gray-400">({new Date(game.latest_version?.published_at || '').toLocaleDateString()})</span>
                            </div>
                            {#if game.latest_version && currentVersion && versionHasCharacterStats[currentVersion.id]}
                                <button
                                    type="button"
                                    class="mt-1 inline-flex cursor-pointer items-center text-xs text-blue-600 hover:underline dark:text-blue-400"
                                    onclick={() => handleCompareVersions(game.id, currentVersion.id, game.latest_version!.id)}
                                >
                                    <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    Compare changes
                                </button>
                            {/if}
                        {/if}
                    </div>
                {:else}
                    <span class="text-xs text-gray-500 dark:text-gray-400">Not started</span>
                {/if}
            </div>

            <!-- Started Date -->
            <div class="w-30 text-sm">
                {userProgress?.started_at || entry.started_at ? new Date(userProgress?.started_at || entry.started_at!).toLocaleDateString() : '-'}
            </div>

            <!-- Completed Date -->
            {#if vnList.type === 'custom' || vnList.type === 'completed'}
                <div class="w-28 text-sm">
                    {userProgress?.completed_at || entry.completed_at
                        ? new Date(userProgress?.completed_at || entry.completed_at!).toLocaleDateString()
                        : '-'}
                </div>
            {/if}

            <!-- Actions -->
            {#if isOwner}
                <div class="w-20 space-y-2 text-sm">
                    <button
                        onclick={() => {
                            if (isEditing) {
                                editingEntryId = null;
                            } else {
                                startEditing(entry);
                            }
                        }}
                        class="block w-full cursor-pointer text-left text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        >{isEditing ? 'Cancel' : 'Edit'}</button
                    >
                    {#if moveLists.length > 0}
                        <button
                            onclick={() => {
                                if (isMoving) {
                                    movingEntryId = null;
                                } else {
                                    startMoving(entry);
                                }
                            }}
                            class="block w-full cursor-pointer text-left text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                            >{isMoving ? 'Cancel' : 'Move'}</button
                        >
                    {/if}
                    <button
                        onclick={() => handleEntryRemove(entry.id)}
                        class="block w-full cursor-pointer text-left text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                        >Remove</button
                    >
                </div>

                <!-- Notification Toggle -->
                {#if !game.is_paid}
                    <div class="w-30 pr-1">
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={userProgress?.receive_updates || false}
                                onchange={() => handleToggleNotification(game, !(userProgress?.receive_updates || false))}
                                class="peer sr-only"
                            />
                            <div
                                class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:-translate-x-full dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"
                            ></div>
                            <span class="sr-only">{userProgress?.receive_updates ? 'Turn off notifications' : 'Turn on notifications'}</span>
                        </label>
                    </div>
                {/if}
            {/if}
        </div>

        <!-- Mobile/Tablet View -->
        <div class="relative flex p-4 lg:hidden">
            {#if isOwner}
                <div class="absolute top-1/2 -left-1 flex -translate-y-1/2 items-center">
                    <DragHandle
                        size="sm"
                        class="drag-handle rounded-r-md bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600"
                        attachment={!isDesktopViewport ? (handleAttachment ?? undefined) : undefined}
                    />
                </div>
            {/if}

            <div class="{isOwner ? 'pl-5' : ''} flex gap-4">
                <!-- Thumbnail -->
                <Link href={route('games.show', game.slug)}>
                    <img
                        src={getOptimizedThumbnail(game)}
                        alt={game.effective_name}
                        class="{game.platform === 'steam' ? 'object-contain' : 'object-cover'} h-32 w-32 rounded"
                        loading="lazy"
                    />
                </Link>

                <!-- Game Info -->
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <Link href={route('games.show', game.slug)} class="text-lg font-medium text-blue-600 hover:underline dark:text-blue-400"
                            >{game.effective_name}</Link
                        >
                        {#if game.ratings && game.ratings.length > 0}
                            <Link
                                href={route('reviews.show', game.ratings[0].id)}
                                class="inline-flex items-center gap-0.5 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:hover:bg-yellow-800"
                                title={game.ratings[0].is_reviewed ? 'View review' : 'View rating'}
                            >
                                <svg class="h-3 w-3 fill-current" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                    />
                                </svg>
                                {game.ratings[0].rating}
                            </Link>
                        {/if}
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        {#if currentVersion}
                            <span
                                class="mb-1 rounded-full px-2 py-1 text-xs {hasUpdate
                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300'
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}"
                            >
                                v{currentVersion.version}
                            </span>
                        {/if}
                    </div>

                    <div class="text-sm">
                        <div>
                            <span>Started:</span>
                            <span class="ml-1"
                                >{userProgress?.started_at || entry.started_at
                                    ? new Date(userProgress?.started_at || entry.started_at!).toLocaleDateString()
                                    : 'Not started'}</span
                            >
                        </div>
                        {#if vnList.type === 'custom' || vnList.type === 'completed'}
                            <div>
                                <span>Completed:</span>
                                <span class="ml-1"
                                    >{userProgress?.completed_at || entry.completed_at
                                        ? new Date(userProgress?.completed_at || entry.completed_at!).toLocaleDateString()
                                        : '-'}</span
                                >
                            </div>
                        {/if}
                        {#if hasUpdate}
                            <div class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
                                Latest: v{game.latest_version?.version}
                                <span class="ml-1 text-gray-400">({new Date(game.latest_version?.published_at || '').toLocaleDateString()})</span>
                                {#if game.latest_version && currentVersion && versionHasCharacterStats[currentVersion.id]}
                                    <button
                                        type="button"
                                        class="ml-2 inline-flex cursor-pointer items-center text-xs text-blue-600 hover:underline dark:text-blue-400"
                                        onclick={() => handleCompareVersions(game.id, currentVersion.id, game.latest_version!.id)}
                                    >
                                        <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                        Compare changes
                                    </button>
                                {/if}
                            </div>
                        {/if}

                        {#if userProgress?.personal_notes || entry.personal_notes || entry.notes}
                            <div class="mt-1 truncate text-xs italic">
                                <span class="text-gray-500 dark:text-gray-400">Public:</span> "{userProgress?.personal_notes ||
                                    entry.personal_notes ||
                                    entry.notes}"
                            </div>
                        {/if}
                        {#if isOwner && entry.private_notes}
                            <div class="mt-1 truncate text-xs italic">
                                <span class="text-blue-500 dark:text-blue-400">Private:</span> "{entry.private_notes}"
                            </div>
                        {/if}
                    </div>

                    {#if isOwner}
                        <div class="mt-3 flex flex-col gap-3">
                            <div class="flex space-x-2 text-sm">
                                <button
                                    onclick={() => {
                                        if (isEditing) {
                                            editingEntryId = null;
                                        } else {
                                            startEditing(entry);
                                        }
                                    }}
                                    class="cursor-pointer text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >{isEditing ? 'Cancel' : 'Edit'}</button
                                >
                                {#if moveLists.length > 0}
                                    <button
                                        onclick={() => {
                                            if (isMoving) {
                                                movingEntryId = null;
                                            } else {
                                                startMoving(entry);
                                            }
                                        }}
                                        class="cursor-pointer text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                                        >{isMoving ? 'Cancel' : 'Move'}</button
                                    >
                                {/if}
                                <button
                                    onclick={() => handleEntryRemove(entry.id)}
                                    class="cursor-pointer text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">Remove</button
                                >
                            </div>

                            <div class="h-px bg-gray-200 dark:bg-gray-700"></div>

                            {#if !game.is_paid}
                                <div class="flex items-center justify-start gap-3">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Notifications</span>
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            checked={userProgress?.receive_updates || false}
                                            onchange={() => handleToggleNotification(game, !(userProgress?.receive_updates || false))}
                                            class="peer sr-only"
                                        />
                                        <div
                                            class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:-translate-x-full dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"
                                        ></div>
                                        <span class="sr-only"
                                            >{userProgress?.receive_updates ? 'Turn off notifications' : 'Turn on notifications'}</span
                                        >
                                    </label>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        {#if isEditing && isOwner}
            <div class="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700">
                <div class="space-y-4">
                    <div
                        class="grid grid-cols-1 gap-4 {vnList.type === 'custom' || vnList.type === 'completed'
                            ? 'md:grid-cols-2 lg:grid-cols-3'
                            : 'md:grid-cols-2 lg:grid-cols-2'}"
                    >
                        <div>
                            <label for="entry-version" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Read Version</label>
                            <select
                                id="entry-version"
                                bind:value={entryFormData.game_version_id}
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >
                                <option value="">Not started</option>
                                {#each game.game_versions || [] as version (version.id)}
                                    <option value={String(version.id)}
                                        >{version.version} ({new Date(version.published_at).toLocaleDateString()})</option
                                    >
                                {/each}
                            </select>
                        </div>
                        <div>
                            <label for="entry-started-at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Started At</label>
                            <input
                                id="entry-started-at"
                                type="date"
                                bind:value={entryFormData.started_at}
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                        </div>
                        {#if vnList.type === 'custom' || vnList.type === 'completed'}
                            <div>
                                <label for="entry-completed-at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Completed At</label
                                >
                                <input
                                    id="entry-completed-at"
                                    type="date"
                                    bind:value={entryFormData.completed_at}
                                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                        {/if}
                    </div>

                    <div>
                        <label for="entry-public-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Public Notes</label>
                        <textarea
                            id="entry-public-notes"
                            bind:value={entryFormData.personal_notes}
                            rows={4}
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">These notes will be visible to anyone who can see this list.</p>
                    </div>

                    <div>
                        <label for="entry-private-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Private Notes</label>
                        <textarea
                            id="entry-private-notes"
                            bind:value={entryFormData.private_notes}
                            rows={4}
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            These notes will only be visible to you, even if the list is public.
                        </p>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button
                            onclick={() => {
                                editingEntryId = null;
                            }}
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >Cancel</button
                        >
                        <button
                            onclick={() => handleSaveEntry(entry.id)}
                            disabled={entryFormLoading}
                            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-50"
                            >Save Changes</button
                        >
                    </div>
                </div>
            </div>
        {/if}

        <!-- Move Form -->
        {#if isMoving && isOwner && moveLists.length > 0}
            <div class="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700">
                <div class="space-y-4">
                    <div>
                        <label for="entry-target-list" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target List</label>
                        <select
                            id="entry-target-list"
                            bind:value={entryFormData.target_list_id}
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">Select a list...</option>
                            {#each moveLists as list (list.id)}
                                <option value={list.id}>{list.name} ({list.type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())})</option>
                            {/each}
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button
                            onclick={() => {
                                movingEntryId = null;
                            }}
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >Cancel</button
                        >
                        <button
                            onclick={() => handleMoveEntry(entry.id)}
                            disabled={entryFormLoading || !entryFormData.target_list_id}
                            class="inline-flex items-center rounded-md border border-transparent bg-yellow-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-yellow-700 disabled:opacity-50"
                            >Move to List</button
                        >
                    </div>
                </div>
            </div>
        {/if}
    </div>
{/snippet}
