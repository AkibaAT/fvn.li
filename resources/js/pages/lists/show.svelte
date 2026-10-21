<script lang="ts">
    import { toast } from '@/utils/toast';
    import { refreshPage } from '@/utils/refreshPage';
    import { useAsyncAction } from '@/utils/async-action.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { Attachment } from 'svelte/attachments';
    import { Link } from '@inertiajs/svelte';
    import {
        destroyListEntry,
        destroyVnList,
        moveListEntry,
        reorderListEntries,
        toggleAllListUpdates,
        toggleUserProgressUpdates,
        toggleVnListVisibility,
        updateListEntry,
        updateVnList,
    } from '@/api/lists';
    import VersionComparisonModal from '@/components/VersionComparisonModal.svelte';
    import { listTypeIcon, listTypeLabel, listTypeTone } from '@/components/ui/tones';
    import SortableList from '@/components/drag-drop/SortableList.svelte';
    import ListEntryCard from '@/components/lists/ListEntryCard.svelte';
    import ListGroup from '@/components/lists/ListGroup.svelte';
    import PrivateTagFilter from '@/components/lists/PrivateTagFilter.svelte';
    import { Badge, Button, Card, Switch, Textarea, TextInput } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import type { AvailableList, Game, VnList, VnListEntry } from '@/types/lists';
    import type { PrivateTagRef } from '@/types/game';

    interface ListShowProps {
        vnList: VnList;
        isOwner: boolean;
        availableLists?: AvailableList[];
        metaTags?: { title?: string; description?: string };
        versionHasCharacterStats?: Record<number, boolean>;
    }

    let { vnList, isOwner, availableLists = [], metaTags, versionHasCharacterStats = {} }: ListShowProps = $props();

    let entries = $derived(vnList.entries);
    const isPublic = $derived(vnList.is_public);
    const listData = $derived(vnList);
    const toggleVisibilityAction = useAsyncAction();
    let isEditingList = $state(false);
    const listSaveAction = useAsyncAction();
    const listDeleteAction = useAsyncAction();
    let listFormData = $state({ name: '', description: '' });
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
    const entryFormAction = useAsyncAction();

    let tagOverrides = $state<Record<number, PrivateTagRef[]>>({});
    let selectedTagIds = $state<number[]>([]);

    const tagsFor = (game: Game): PrivateTagRef[] => tagOverrides[game.id] ?? game.user_private_tags ?? [];

    const handlePrivateTagsChange = (gameId: number, tags: PrivateTagRef[]) => {
        tagOverrides = { ...tagOverrides, [gameId]: tags };
    };

    const tagOptions = $derived.by(() => {
        if (!isOwner) return [];
        const byId: Record<number, { id: number; name: string; count: number }> = {};
        for (const entry of entries) {
            for (const tag of tagsFor(entry.game)) {
                byId[tag.id] ??= { id: tag.id, name: tag.name, count: 0 };
                byId[tag.id].count++;
            }
        }
        return Object.values(byId).sort((a, b) => a.name.localeCompare(b.name));
    });
    const activeTagIds = $derived(selectedTagIds.filter((id) => tagOptions.some((option) => option.id === id)));
    const isFiltering = $derived(activeTagIds.length > 0);
    const visibleEntries = $derived(
        isFiltering
            ? entries.filter((entry) => {
                  const ids = tagsFor(entry.game).map((tag) => tag.id);
                  return activeTagIds.every((id) => ids.includes(id));
              })
            : entries,
    );

    const toggleTagFilter = (id: number) => {
        selectedTagIds = activeTagIds.includes(id) ? activeTagIds.filter((tagId) => tagId !== id) : [...activeTagIds, id];
    };

    const freeGames = $derived(entries.filter((e) => !e.game.is_paid));
    const allFreeGamesReceiveUpdates = $derived(
        freeGames.length > 0 && freeGames.every((e) => e.game.user_progress?.[0]?.is_receiving_updates ?? false),
    );

    const refreshList = () => refreshPage(['vnList', 'availableLists', 'metaTags']);

    const handleSaveList = async () => {
        const data = await listSaveAction.run(
            async () => {
                const result = await updateVnList(vnList.id, { ...listFormData, is_public: isPublic });
                if (!(await refreshList())) return null;
                return result;
            },
            { fallbackError: 'Failed to update list' },
        );
        if (!data) return;
        isEditingList = false;
        toast.success('List updated successfully');
    };

    const handleCancelListEdit = () => {
        listFormData = { name: listData.name, description: listData.description || '' };
        isEditingList = false;
    };

    const handleDeleteList = async () => {
        if (!confirm('Are you sure you want to delete this list? This action cannot be undone.')) return;
        const deleted = await listDeleteAction.run(
            async () => {
                await destroyVnList(vnList.id);
                return true;
            },
            { fallbackError: 'Failed to delete list' },
        );
        if (!deleted) return;
        toast.success('List deleted successfully');
        window.location.href = route('lists.index');
    };

    const handleToggleVisibility = async () => {
        const data = await toggleVisibilityAction.run(
            async () => {
                const result = await toggleVnListVisibility(vnList.id);
                if (!(await refreshList())) return null;
                return result;
            },
            { fallbackError: 'Failed to update visibility' },
        );
        if (!data) return;
        toast.success(data.message || 'List visibility updated');
    };

    const handleCompareVersions = (gameId: number, fromVersionId: number, toVersionId: number) => {
        comparisonVersions = { gameId, fromVersionId, toVersionId };
        showVersionComparison = true;
    };
    const handleCloseVersionComparison = () => {
        showVersionComparison = false;
        comparisonVersions = null;
    };

    const entryRemoveAction = useAsyncAction();

    const handleEntryRemove = async (entryId: number) => {
        if (!confirm('Are you sure you want to remove this game from the list?')) return;
        const removed = await entryRemoveAction.run(
            async () => {
                await destroyListEntry(entryId);
                if (!(await refreshList())) return null;
                return true;
            },
            { fallbackError: 'Failed to remove entry' },
        );
        if (!removed) return;
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
        const data = await entryFormAction.run(
            async () => {
                const result = await updateListEntry(entryId, entryFormData);
                if (!(await refreshList())) return null;
                return result;
            },
            { fallbackError: 'Failed to update entry' },
        );
        if (!data) return;
        editingEntryId = null;
        toast.success('Entry updated');
    };

    const startMoving = (entry: VnListEntry) => {
        entryFormData = { ...entryFormData, target_list_id: '' };
        movingEntryId = entry.id;
        editingEntryId = null;
    };

    const handleMoveEntry = async (entryId: number) => {
        if (!entryFormData.target_list_id) return;
        const moved = await entryFormAction.run(
            async () => {
                await moveListEntry(entryId, entryFormData.target_list_id);
                if (!(await refreshList())) return null;
                return true;
            },
            { fallbackError: 'Failed to move entry' },
        );
        if (!moved) return;
        movingEntryId = null;
        toast.success('Entry moved successfully');
    };

    const toggleNotificationAction = useAsyncAction();

    const handleToggleNotification = async (game: Game, newStatus: boolean) => {
        const data = await toggleNotificationAction.run(
            async () => {
                const result = await toggleUserProgressUpdates(game.id, newStatus);
                if (!(await refreshList())) return null;
                return result;
            },
            {
                fallbackError: 'Failed to update notifications',
            },
        );
        if (!data) return;
        toast.success(data.message || `Notifications ${data.is_receiving_updates ? 'enabled' : 'disabled'} for "${game.effective_name}"`);
    };

    const toggleAllNotificationsAction = useAsyncAction();

    const handleToggleAllNotifications = async () => {
        const newStatus = !allFreeGamesReceiveUpdates;
        const data = await toggleAllNotificationsAction.run(
            async () => {
                const result = await toggleAllListUpdates(vnList.id, newStatus);
                if (!(await refreshList())) return null;
                return result;
            },
            {
                fallbackError: 'Failed to update notifications',
            },
        );
        if (!data) return;
        toast.success(data.message || `Notifications ${newStatus ? 'enabled' : 'disabled'} for all games`);
    };

    const reorderAction = useAsyncAction();

    const handleReorder = async (newEntries: VnListEntry[]) => {
        const originalEntries = [...entries];
        entries = newEntries;
        const data = await reorderAction.run(
            async () => {
                const result = await reorderListEntries(
                    vnList.id,
                    newEntries.map((e) => e.id),
                );
                if (!(await refreshList())) return null;
                return result;
            },
            {
                fallbackError: 'Failed to update order',
            },
        );
        if (!data) {
            entries = originalEntries;
            return;
        }
        toast.success(data.message || 'List order updated');
    };

    const availableListsForMove = (currentListId: number) => availableLists.filter((list) => list.id !== currentListId);

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

<SeoHead {metaTags} title={isEditingList ? listFormData.name : listData.name} />

<div class="space-y-6">
    <Card padding="lg" class="mb-6 p-4 md:p-6">
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
                                By <Link href={route('lists.user-public', vnList.user.id)} class="text-fg-muted hover:text-fg hover:underline"
                                    >{vnList.user.name}</Link
                                >
                            </span>
                        {/if}
                    {/snippet}
                </PageHeader>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {#if !vnList.is_default}
                    {@const ListTypeIcon = listTypeIcon(vnList.type)}
                    <Badge tone={listTypeTone(vnList.type)} size="lg" class="gap-1.5 py-1">
                        <ListTypeIcon class="h-4 w-4" />
                        {listTypeLabel(vnList.type)}
                    </Badge>
                {/if}
                {#if isPublic}
                    <Badge tone="neutral" size="sm">Public</Badge>
                {/if}
                {#if isOwner}
                    <div class="flex gap-2">
                        <Button href={route('lists.index')} variant="outline" tone="neutral" size="xs">Back to Lists</Button>
                        <Button
                            type="button"
                            variant="outline"
                            tone="neutral"
                            size="xs"
                            onclick={handleToggleVisibility}
                            disabled={toggleVisibilityAction.isLoading}
                            loading={toggleVisibilityAction.isLoading}
                        >
                            {isPublic ? 'Make Private' : 'Make Public'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            tone="neutral"
                            size="xs"
                            onclick={() => {
                                if (isEditingList) handleCancelListEdit();
                                else {
                                    listFormData = { name: listData.name, description: listData.description || '' };
                                    isEditingList = true;
                                }
                            }}
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
                                disabled={listDeleteAction.isLoading}
                                loading={listDeleteAction.isLoading}
                            >
                                {listDeleteAction.isLoading ? 'Deleting...' : 'Delete List'}
                            </Button>
                        {/if}
                    </div>
                {:else}
                    <Button href={route('lists.public')} variant="outline" tone="neutral" size="xs">Back to Public Lists</Button>
                {/if}
            </div>
        </div>
    </Card>

    {#if isEditingList && isOwner}
        <Card padding="sm" class="mb-4 border-l-4 border-l-amber-500">
            <h3 class="mb-4 text-title font-semibold text-fg">Edit List</h3>
            <div class="space-y-4">
                <TextInput id="list-name" type="text" bind:value={listFormData.name} label="List Name" placeholder="Enter list name" />
                <Textarea
                    id="list-description"
                    bind:value={listFormData.description}
                    rows={3}
                    label="Description (Optional)"
                    placeholder="Enter list description"
                />
                <div class="flex justify-end space-x-2">
                    <Button type="button" variant="outline" tone="neutral" onclick={handleCancelListEdit}>Cancel</Button>
                    <Button
                        type="button"
                        variant="solid"
                        tone="primary"
                        onclick={handleSaveList}
                        disabled={listSaveAction.isLoading || !listFormData.name.trim()}
                        loading={listSaveAction.isLoading}
                    >
                        {listSaveAction.isLoading ? 'Saving...' : 'Save Changes'}
                    </Button>
                </div>
            </div>
        </Card>
    {/if}

    <Card padding="none">
        <div class="px-4 py-3 md:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-title font-semibold text-fg">
                    List Entries ({isFiltering ? `${visibleEntries.length} of ${entries.length}` : entries.length})
                </h2>
                {#if isOwner && freeGames.length > 0}
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-fg-muted">Notifications for all free entries:</span>
                        <Switch
                            checked={allFreeGamesReceiveUpdates}
                            onchange={handleToggleAllNotifications}
                            ariaLabel={allFreeGamesReceiveUpdates
                                ? 'Turn off notifications for all free entries'
                                : 'Turn on notifications for all free entries'}
                        />
                    </div>
                {/if}
            </div>
            {#if tagOptions.length > 0}
                <PrivateTagFilter
                    class="mt-3"
                    options={tagOptions}
                    selected={activeTagIds}
                    onToggle={toggleTagFilter}
                    onClear={() => (selectedTagIds = [])}
                />
            {/if}
        </div>

        {#if entries.length === 0}
            <div class="border-t border-border p-8 text-center">
                <p class="text-fg-muted">No visual novels in this list yet.</p>
                {#if isOwner}
                    <p class="mt-2 text-fg-muted">Browse games and add them to your list!</p>
                {/if}
            </div>
        {:else if visibleEntries.length === 0}
            <div class="border-t border-border p-8 text-center">
                <p class="text-fg-muted">No games in this list have all of the selected private tags.</p>
            </div>
        {:else}
            <div
                class="hidden items-center gap-3.5 border-t border-border bg-surface-alt px-6 py-2 text-xs font-medium tracking-wide text-fg-muted uppercase lg:flex"
            >
                {#if isOwner}
                    <div class="w-8 shrink-0"></div>
                {/if}
                <div class="min-w-0 flex-1">Title</div>
                <div class="flex shrink-0 items-center gap-3">
                    <div class="w-52">Version</div>
                    <div class="w-30">Started</div>
                    {#if vnList.type === 'custom' || vnList.type === 'completed'}
                        <div class="w-28">Completed</div>
                    {/if}
                    {#if isOwner}
                        <div class="w-20">Actions</div>
                        <div class="w-30 pr-1 text-right">Notifications</div>
                    {/if}
                </div>
            </div>

            <ListGroup class="rounded-none border-0 border-t border-border bg-transparent lg:px-6">
                {#if isOwner && !isFiltering}
                    <SortableList items={entries} onReorder={(newItems) => handleReorder(newItems)} class="divide-y divide-border text-fg-muted">
                        {#snippet children(entry, _index, _canDrag, handleAttachment)}
                            {@render gameEntry(entry, handleAttachment)}
                        {/snippet}
                    </SortableList>
                {:else}
                    <div class="divide-y divide-border text-fg-muted">
                        {#each visibleEntries as entry (entry.id)}
                            {@render gameEntry(entry)}
                        {/each}
                    </div>
                {/if}
            </ListGroup>
        {/if}
    </Card>
</div>

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
        canReorder={!isFiltering}
        privateTags={tagsFor(entry.game)}
        onPrivateTagsChange={handlePrivateTagsChange}
        {isDesktopViewport}
        vnListType={vnList.type}
        availableListsForMove={availableListsForMove(vnList.id)}
        {versionHasCharacterStats}
        {editingEntryId}
        {movingEntryId}
        {entryFormData}
        entryFormLoading={entryFormAction.isLoading}
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
