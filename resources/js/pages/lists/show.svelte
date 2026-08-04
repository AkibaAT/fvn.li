<script lang="ts">
    import type { Attachment } from 'svelte/attachments';
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import { notify } from '@/components/Toast.svelte';
    import { authenticatedFetch } from '@/utils/csrf';
    import VersionComparisonModal from '@/components/VersionComparisonModal.svelte';
    import { getListTypeConfig, listStatusConfig, getStatusBadgeConfig } from '@/utils/status-indicators';
    import SortableList from '@/components/drag-drop/SortableList.svelte';
    import ListEntryCard from '@/components/lists/ListEntryCard.svelte';
    import { Button, Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';

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
            ? 'border-blue-500 dark:border-blue-500'
            : color === 'green'
              ? 'border-green-500 dark:border-green-500'
              : color === 'yellow'
                ? 'border-yellow-500 dark:border-yellow-500'
                : color === 'orange'
                  ? 'border-orange-500 dark:border-orange-500'
                  : color === 'red'
                    ? 'border-red-500 dark:border-red-500'
                    : 'border-gray-500 dark:border-gray-500',
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
    <Card padding="lg" class="mb-6 border-l-4 p-4 md:p-6 {borderColorClass}">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <PageHeader
                    title={isEditingList ? listFormData.name : listData.name}
                    description={isEditingList ? listFormData.description : listData.description}
                    class="mb-0"
                >
                    {#snippet metadata()}
                        {#if !isOwner && vnList?.user?.name}
                            <span>
                                By <Link href={route('lists.user-public', vnList.user.id)} class="text-blue-600 hover:underline dark:text-blue-400"
                                    >{vnList.user.name}</Link
                                >
                            </span>
                        {/if}
                    {/snippet}
                </PageHeader>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {#if !vnList.is_default}
                    {@const badge = getStatusBadgeConfig(vnList.type, listStatusConfig)}
                    {#if badge}
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium {badge.classes}"
                            aria-label={badge.ariaLabel}
                        >
                            {#if badge.icon}<i class={badge.icon} aria-hidden="true"></i>{/if}
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
                        <Button
                            type="button"
                            variant="solid"
                            tone={isPublic ? 'primary' : 'neutral'}
                            size="xs"
                            onclick={handleToggleVisibility}
                            disabled={isToggleVisibilityLoading}
                            loading={isToggleVisibilityLoading}
                            class="inline-flex items-center rounded-md border border-transparent px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase disabled:opacity-50 {isPublic
                                ? 'bg-blue-500 hover:bg-blue-400'
                                : 'bg-gray-500 hover:bg-gray-400'}"
                        >
                            {isPublic ? 'Make Private' : 'Make Public'}
                        </Button>
                        <Button
                            type="button"
                            variant="solid"
                            tone="warning"
                            size="xs"
                            onclick={() => {
                                isEditingList = !isEditingList;
                            }}
                            class="inline-flex items-center rounded-md border border-transparent bg-yellow-500 px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase hover:bg-yellow-400"
                        >
                            {isEditingList ? 'Cancel Edit' : 'Edit List'}
                        </Button>
                        {#if vnList.type === 'custom' && !vnList.is_default}
                            <Button
                                type="button"
                                variant="solid"
                                tone="danger"
                                size="xs"
                                onclick={handleDeleteList}
                                disabled={isListDeleteLoading}
                                loading={isListDeleteLoading}
                                class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase hover:bg-red-700 disabled:opacity-50"
                            >
                                {isListDeleteLoading ? 'Deleting...' : 'Delete List'}
                            </Button>
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
    </Card>

    <!-- List Edit Form -->
    {#if isEditingList && isOwner}
        <Card padding="sm" class="mb-4 border-l-4 border-yellow-500 dark:border-yellow-500">
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
                        placeholder="Enter list description"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        tone="neutral"
                        onclick={handleCancelListEdit}
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        variant="solid"
                        tone="warning"
                        onclick={handleSaveList}
                        disabled={isListSaveLoading || !listFormData.name.trim()}
                        loading={isListSaveLoading}
                        class="inline-flex items-center rounded-md border border-transparent bg-yellow-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-yellow-700 disabled:opacity-50"
                    >
                        {isListSaveLoading ? 'Saving...' : 'Save Changes'}
                    </Button>
                </div>
            </div>
        </Card>
    {/if}

    <!-- List Stats Card -->
    <Card padding="sm" class="mb-4 md:pr-6 md:pl-7">
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
    </Card>

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
    <ListEntryCard
        {entry}
        {handleAttachment}
        {isOwner}
        {isDesktopViewport}
        vnListType={vnList.type}
        availableListsForMove={availableListsForMove(vnList.id)}
        {versionHasCharacterStats}
        {editingEntryId}
        {movingEntryId}
        {entryFormData}
        {entryFormLoading}
        {getOptimizedThumbnail}
        onCompareVersions={handleCompareVersions}
        onRemove={handleEntryRemove}
        onStartEditing={startEditing}
        onCancelEditing={() => (editingEntryId = null)}
        onSaveEntry={handleSaveEntry}
        onStartMoving={startMoving}
        onCancelMoving={() => (movingEntryId = null)}
        onMoveEntry={handleMoveEntry}
        onToggleNotification={handleToggleNotification}
    />
{/snippet}
