<script lang="ts">
    import type { Attachment } from 'svelte/attachments';
    import { Link } from '@inertiajs/svelte';
    import DragHandle from '@/components/drag-drop/DragHandle.svelte';
    import { Button, Card } from '@/components/ui';

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
    interface AvailableList {
        id: number;
        name: string;
        type: string;
    }
    interface EntryFormData {
        game_version_id: string;
        personal_notes: string;
        private_notes: string;
        started_at: string;
        completed_at: string;
        target_list_id: string;
    }
    interface ListEntryCardProps {
        entry: VnListEntry;
        handleAttachment?: Attachment<HTMLElement> | null;
        isOwner: boolean;
        isDesktopViewport: boolean;
        vnListType: string;
        availableListsForMove: AvailableList[];
        versionHasCharacterStats: Record<number, boolean>;
        editingEntryId: number | null;
        movingEntryId: number | null;
        entryFormData: EntryFormData;
        entryFormLoading: boolean;
        getOptimizedThumbnail: (game: Game) => string;
        onCompareVersions: (gameId: number, fromVersionId: number, toVersionId: number) => void;
        onRemove: (entryId: number) => void;
        onStartEditing: (entry: VnListEntry) => void;
        onCancelEditing: () => void;
        onSaveEntry: (entryId: number) => void;
        onStartMoving: (entry: VnListEntry) => void;
        onCancelMoving: () => void;
        onMoveEntry: (entryId: number) => void;
        onToggleNotification: (game: Game, newStatus: boolean) => void;
    }

    let {
        entry,
        handleAttachment = null,
        isOwner,
        isDesktopViewport,
        vnListType,
        availableListsForMove,
        versionHasCharacterStats,
        editingEntryId,
        movingEntryId,
        entryFormData,
        entryFormLoading,
        getOptimizedThumbnail,
        onCompareVersions,
        onRemove,
        onStartEditing,
        onCancelEditing,
        onSaveEntry,
        onStartMoving,
        onCancelMoving,
        onMoveEntry,
        onToggleNotification,
    }: ListEntryCardProps = $props();

    const game = $derived(entry.game);
    const userProgress = $derived(game.user_progress?.[0] || entry.user_progress);
    const currentVersion = $derived(userProgress?.game_version || entry.game_version || null);
    const hasUpdate = $derived(game.latest_version && currentVersion && game.latest_version.id !== currentVersion.id);
    const isEditing = $derived(editingEntryId === entry.id);
    const isMoving = $derived(movingEntryId === entry.id);
    const moveLists = $derived(availableListsForMove);
</script>

<Card padding="none" class="md:rounded-lg lg:rounded-none">
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
                            <Button
                                type="button"
                                variant="link"
                                tone="primary"
                                size="xs"
                                class="mt-1 inline-flex cursor-pointer items-center text-xs text-blue-600 hover:underline dark:text-blue-400"
                                onclick={() => onCompareVersions(game.id, currentVersion.id, game.latest_version!.id)}
                            >
                                <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Compare changes
                            </Button>
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
        {#if vnListType === 'custom' || vnListType === 'completed'}
            <div class="w-28 text-sm">
                {userProgress?.completed_at || entry.completed_at
                    ? new Date(userProgress?.completed_at || entry.completed_at!).toLocaleDateString()
                    : '-'}
            </div>
        {/if}

        <!-- Actions -->
        {#if isOwner}
            <div class="w-20 space-y-2 text-sm">
                <Button
                    type="button"
                    variant="link"
                    tone="primary"
                    onclick={() => {
                        if (isEditing) {
                            onCancelEditing();
                        } else {
                            onStartEditing(entry);
                        }
                    }}
                    class="block w-full cursor-pointer text-left text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                    >{isEditing ? 'Cancel' : 'Edit'}</Button
                >
                {#if moveLists.length > 0}
                    <Button
                        type="button"
                        variant="link"
                        tone="warning"
                        onclick={() => {
                            if (isMoving) {
                                onCancelMoving();
                            } else {
                                onStartMoving(entry);
                            }
                        }}
                        class="block w-full cursor-pointer text-left text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                        >{isMoving ? 'Cancel' : 'Move'}</Button
                    >
                {/if}
                <Button
                    type="button"
                    variant="link"
                    tone="danger"
                    onclick={() => onRemove(entry.id)}
                    class="block w-full cursor-pointer text-left text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                    >Remove</Button
                >
            </div>

            <!-- Notification Toggle -->
            {#if !game.is_paid}
                <div class="w-30 pr-1">
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            checked={userProgress?.receive_updates || false}
                            onchange={() => onToggleNotification(game, !(userProgress?.receive_updates || false))}
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
                    {#if vnListType === 'custom' || vnListType === 'completed'}
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
                                <Button
                                    type="button"
                                    variant="link"
                                    tone="primary"
                                    size="xs"
                                    class="ml-2 inline-flex cursor-pointer items-center text-xs text-blue-600 hover:underline dark:text-blue-400"
                                    onclick={() => onCompareVersions(game.id, currentVersion.id, game.latest_version!.id)}
                                >
                                    <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    Compare changes
                                </Button>
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
                            <Button
                                type="button"
                                variant="link"
                                tone="primary"
                                onclick={() => {
                                    if (isEditing) {
                                        onCancelEditing();
                                    } else {
                                        onStartEditing(entry);
                                    }
                                }}
                                class="cursor-pointer text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                >{isEditing ? 'Cancel' : 'Edit'}</Button
                            >
                            {#if moveLists.length > 0}
                                <Button
                                    type="button"
                                    variant="link"
                                    tone="warning"
                                    onclick={() => {
                                        if (isMoving) {
                                            onCancelMoving();
                                        } else {
                                            onStartMoving(entry);
                                        }
                                    }}
                                    class="cursor-pointer text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                                    >{isMoving ? 'Cancel' : 'Move'}</Button
                                >
                            {/if}
                            <Button
                                type="button"
                                variant="link"
                                tone="danger"
                                onclick={() => onRemove(entry.id)}
                                class="cursor-pointer text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">Remove</Button
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
                                        onchange={() => onToggleNotification(game, !(userProgress?.receive_updates || false))}
                                        class="peer sr-only"
                                    />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:-translate-x-full dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"
                                    ></div>
                                    <span class="sr-only">{userProgress?.receive_updates ? 'Turn off notifications' : 'Turn on notifications'}</span>
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
                    class="grid grid-cols-1 gap-4 {vnListType === 'custom' || vnListType === 'completed'
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
                                <option value={String(version.id)}>{version.version} ({new Date(version.published_at).toLocaleDateString()})</option>
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
                    {#if vnListType === 'custom' || vnListType === 'completed'}
                        <div>
                            <label for="entry-completed-at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Completed At</label>
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
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">These notes will only be visible to you, even if the list is public.</p>
                </div>

                <div class="flex justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        tone="neutral"
                        onclick={() => {
                            onCancelEditing();
                        }}
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        variant="solid"
                        tone="primary"
                        onclick={() => onSaveEntry(entry.id)}
                        disabled={entryFormLoading}
                        loading={entryFormLoading}
                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-50"
                        >Save Changes</Button
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
                    <Button
                        type="button"
                        variant="outline"
                        tone="neutral"
                        onclick={() => {
                            onCancelMoving();
                        }}
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        variant="solid"
                        tone="warning"
                        onclick={() => onMoveEntry(entry.id)}
                        disabled={entryFormLoading || !entryFormData.target_list_id}
                        loading={entryFormLoading}
                        class="inline-flex items-center rounded-md border border-transparent bg-yellow-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-yellow-700 disabled:opacity-50"
                        >Move to List</Button
                    >
                </div>
            </div>
        </div>
    {/if}
</Card>
