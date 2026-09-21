<script lang="ts">
    import { formatLocalDate, formatCalendarDate } from '@/utils/date-formatting';
    import ChevronRightIcon from '@/components/icons/ChevronRight.svelte';
    import StarIcon from '@/components/icons/Star.svelte';
    import type { Attachment } from 'svelte/attachments';
    import { Link } from '@inertiajs/svelte';
    import DragHandle from '@/components/drag-drop/DragHandle.svelte';
    import { Button, Rating, Select, Switch, TextInput, Textarea } from '@/components/ui';
    import ListRow from '@/components/lists/ListRow.svelte';
    import { formatAuthorsInline, formatWordCount } from '@/utils/game-card-display';

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
        authors?: string;
        rating_score?: number | null;
        rating_count?: number | null;
        english_word_count?: number | null;
        primary_word_count?: number | null;
        primary_language_label?: string | null;
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
    const authorsInline = $derived(formatAuthorsInline(game.authors));
    const wordCountLabel = $derived(formatWordCount(game, null));
    const hasCommunityRating = $derived(typeof game.rating_score === 'number' && game.rating_score > 0);

    const isEditing = $derived(editingEntryId === entry.id);
    const isMoving = $derived(movingEntryId === entry.id);
    const moveLists = $derived(availableListsForMove);
</script>

<!-- Below lg each entry is its own card; from lg up the rows share one `ListGroup` surface. -->
<div class="rounded-lg border border-border bg-surface lg:rounded-none lg:border-0 lg:bg-transparent">
    <ListRow class="hidden border-b-0 lg:flex">
        {#snippet leading()}
            {#if isOwner}
                <div class="flex w-8 shrink-0">
                    <DragHandle size="md" class="drag-handle" attachment={isDesktopViewport ? (handleAttachment ?? undefined) : undefined} />
                </div>
            {:else}
                <div class="w-8 shrink-0"></div>
            {/if}
        {/snippet}

        {#snippet media()}
            <Link href={route('games.show', game.slug)}>
                <img
                    src={getOptimizedThumbnail(game)}
                    alt={game.effective_name}
                    class="h-[52px] w-[72px] rounded-md border border-border object-cover"
                    loading="lazy"
                />
            </Link>
        {/snippet}

        {#snippet body()}
            <div class="flex items-center gap-2">
                <Link href={route('games.show', game.slug)} class="font-medium break-words text-fg hover:underline">{game.effective_name}</Link>
                {#if game.ratings && game.ratings.length > 0}
                    <Link
                        href={route('reviews.show', game.ratings[0].id)}
                        class="inline-flex items-center gap-0.5 rounded-[3px] border border-amber-600/50 px-1.5 py-0.5 text-xs font-medium text-amber-700 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300"
                        title={game.ratings[0].is_reviewed ? 'View review' : 'View rating'}
                    >
                        <StarIcon class="h-3 w-3 fill-current" />
                        {game.ratings[0].rating}
                    </Link>
                {/if}
            </div>

            {#if authorsInline}
                <div class="mt-0.5 truncate text-[12.5px] leading-snug text-fg-muted" data-entry-authors>
                    <span class="sr-only">Authors: </span>
                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                    {@html authorsInline}
                </div>
            {/if}

            {#if wordCountLabel || hasCommunityRating}
                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[12.5px] leading-snug">
                    {#if wordCountLabel}<span class="text-fg-muted">{wordCountLabel}</span>{/if}
                    {#if wordCountLabel && hasCommunityRating}<span class="text-fg-faint" aria-hidden="true">·</span>{/if}
                    {#if hasCommunityRating}
                        <Rating score={game.rating_score} count={game.rating_count} />
                    {/if}
                </div>
            {/if}

            {#if userProgress?.personal_notes || entry.personal_notes || entry.notes}
                <div class="max-w-md truncate text-xs italic">
                    <span class="text-fg-faint">Public:</span> "{userProgress?.personal_notes || entry.personal_notes || entry.notes}"
                </div>
            {/if}
            {#if isOwner && entry.private_notes}
                <div class="mt-1 max-w-md truncate text-xs italic">
                    <span class="font-medium text-fg-muted">Private:</span> "{entry.private_notes}"
                </div>
            {/if}
        {/snippet}

        {#snippet aside()}
            <div class="w-52">
                {#if currentVersion}
                    <div class="border-l-4 pl-3 {hasUpdate ? 'border-amber-500' : 'border-transparent'}">
                        v{currentVersion.version}
                        <span class="text-fg-faint">({formatLocalDate(currentVersion.published_at)})</span>
                        {#if hasUpdate}
                            <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                Latest: v{game.latest_version?.version}
                                <span class="text-fg-faint">({formatLocalDate(game.latest_version?.published_at)})</span>
                            </div>
                            {#if game.latest_version && currentVersion && versionHasCharacterStats[currentVersion.id]}
                                <Button
                                    type="button"
                                    variant="link"
                                    tone="primary"
                                    size="xs"
                                    class="mt-1"
                                    onclick={() => onCompareVersions(game.id, currentVersion.id, game.latest_version!.id)}
                                >
                                    <ChevronRightIcon class="mr-1 h-3 w-3" />
                                    Compare changes
                                </Button>
                            {/if}
                        {/if}
                    </div>
                {:else}
                    <span class="text-xs text-fg-faint">Not started</span>
                {/if}
            </div>

            <div class="w-30 text-sm text-fg-muted">
                {userProgress?.started_at || entry.started_at ? formatCalendarDate(userProgress?.started_at || entry.started_at!) : '-'}
            </div>

            {#if vnListType === 'custom' || vnListType === 'completed'}
                <div class="w-28 text-sm text-fg-muted">
                    {userProgress?.completed_at || entry.completed_at ? formatCalendarDate(userProgress?.completed_at || entry.completed_at!) : '-'}
                </div>
            {/if}

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
                        class="block w-full cursor-pointer text-left">{isEditing ? 'Cancel' : 'Edit'}</Button
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
                            class="block w-full cursor-pointer text-left">{isMoving ? 'Cancel' : 'Move'}</Button
                        >
                    {/if}
                    <Button
                        type="button"
                        variant="link"
                        tone="danger"
                        onclick={() => onRemove(entry.id)}
                        class="block w-full cursor-pointer text-left">Remove</Button
                    >
                </div>

                {#if !game.is_paid}
                    <div class="w-30 pr-1">
                        <Switch
                            checked={userProgress?.receive_updates || false}
                            onchange={() => onToggleNotification(game, !(userProgress?.receive_updates || false))}
                            ariaLabel={userProgress?.receive_updates ? 'Turn off notifications' : 'Turn on notifications'}
                        />
                    </div>
                {/if}
            {/if}
        {/snippet}
    </ListRow>

    <div class="relative flex p-4 lg:hidden">
        {#if isOwner}
            <div class="absolute top-1/2 -left-1 flex -translate-y-1/2 items-center">
                <DragHandle
                    size="sm"
                    class="drag-handle rounded-r-md border border-border bg-surface-alt text-fg-muted"
                    attachment={!isDesktopViewport ? (handleAttachment ?? undefined) : undefined}
                />
            </div>
        {/if}

        <div class="{isOwner ? 'pl-5' : ''} flex gap-4">
            <Link href={route('games.show', game.slug)}>
                <img
                    src={getOptimizedThumbnail(game)}
                    alt={game.effective_name}
                    class="{game.platform === 'steam' ? 'object-contain' : 'object-cover'} h-32 w-32 rounded-md border border-border"
                    loading="lazy"
                />
            </Link>

            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <Link href={route('games.show', game.slug)} class="text-lg font-medium text-fg hover:underline">{game.effective_name}</Link>
                    {#if game.ratings && game.ratings.length > 0}
                        <Link
                            href={route('reviews.show', game.ratings[0].id)}
                            class="inline-flex items-center gap-0.5 rounded-[3px] border border-amber-600/50 px-1.5 py-0.5 text-xs font-medium text-amber-700 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300"
                            title={game.ratings[0].is_reviewed ? 'View review' : 'View rating'}
                        >
                            <StarIcon class="h-3 w-3 fill-current" />
                            {game.ratings[0].rating}
                        </Link>
                    {/if}
                </div>

                {#if authorsInline}
                    <div class="mt-1 line-clamp-2 text-xs leading-snug text-fg-muted" data-entry-authors>
                        <span class="sr-only">Authors: </span>
                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                        {@html authorsInline}
                    </div>
                {/if}

                {#if wordCountLabel || hasCommunityRating}
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs leading-snug">
                        {#if wordCountLabel}<span class="text-fg-muted">{wordCountLabel}</span>{/if}
                        {#if wordCountLabel && hasCommunityRating}<span class="text-fg-faint" aria-hidden="true">·</span>{/if}
                        {#if hasCommunityRating}
                            <Rating score={game.rating_score} count={game.rating_count} />
                        {/if}
                    </div>
                {/if}

                <div class="mt-2 flex items-center gap-2">
                    {#if currentVersion}
                        <span
                            class="mb-1 rounded-md border px-2 py-1 text-xs {hasUpdate
                                ? 'border-amber-600/50 text-amber-700 dark:text-amber-400'
                                : 'border-border bg-surface-alt text-fg-muted'}"
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
                                ? formatCalendarDate(userProgress?.started_at || entry.started_at!)
                                : 'Not started'}</span
                        >
                    </div>
                    {#if vnListType === 'custom' || vnListType === 'completed'}
                        <div>
                            <span>Completed:</span>
                            <span class="ml-1"
                                >{userProgress?.completed_at || entry.completed_at
                                    ? formatCalendarDate(userProgress?.completed_at || entry.completed_at!)
                                    : '-'}</span
                            >
                        </div>
                    {/if}
                    {#if hasUpdate}
                        <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Latest: v{game.latest_version?.version}
                            <span class="ml-1 text-fg-faint">({formatLocalDate(game.latest_version?.published_at)})</span>
                            {#if game.latest_version && currentVersion && versionHasCharacterStats[currentVersion.id]}
                                <Button
                                    type="button"
                                    variant="link"
                                    tone="primary"
                                    size="xs"
                                    class="ml-2"
                                    onclick={() => onCompareVersions(game.id, currentVersion.id, game.latest_version!.id)}
                                >
                                    <ChevronRightIcon class="mr-1 h-3 w-3" />
                                    Compare changes
                                </Button>
                            {/if}
                        </div>
                    {/if}

                    {#if userProgress?.personal_notes || entry.personal_notes || entry.notes}
                        <div class="mt-1 truncate text-xs italic">
                            <span class="text-fg-faint">Public:</span> "{userProgress?.personal_notes || entry.personal_notes || entry.notes}"
                        </div>
                    {/if}
                    {#if isOwner && entry.private_notes}
                        <div class="mt-1 truncate text-xs italic">
                            <span class="font-medium text-fg-muted">Private:</span> "{entry.private_notes}"
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
                                class="cursor-pointer">{isEditing ? 'Cancel' : 'Edit'}</Button
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
                                    class="cursor-pointer">{isMoving ? 'Cancel' : 'Move'}</Button
                                >
                            {/if}
                            <Button type="button" variant="link" tone="danger" onclick={() => onRemove(entry.id)} class="cursor-pointer"
                                >Remove</Button
                            >
                        </div>

                        <div class="h-px bg-border"></div>

                        {#if !game.is_paid}
                            <div class="flex items-center justify-start gap-3">
                                <span class="text-sm font-medium text-fg">Notifications</span>
                                <Switch
                                    checked={userProgress?.receive_updates || false}
                                    onchange={() => onToggleNotification(game, !(userProgress?.receive_updates || false))}
                                    ariaLabel={userProgress?.receive_updates ? 'Turn off notifications' : 'Turn on notifications'}
                                />
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>
    </div>

    {#if isEditing && isOwner}
        <div class="border-t border-border bg-surface-alt p-4">
            <div class="space-y-4">
                <div
                    class="grid grid-cols-1 gap-4 {vnListType === 'custom' || vnListType === 'completed'
                        ? 'md:grid-cols-2 lg:grid-cols-3'
                        : 'md:grid-cols-2 lg:grid-cols-2'}"
                >
                    <Select id="entry-version" bind:value={entryFormData.game_version_id} label="Last Read Version">
                        <option value="">Not started</option>
                        {#each game.game_versions || [] as version (version.id)}
                            <option value={String(version.id)}>{version.version} ({formatLocalDate(version.published_at)})</option>
                        {/each}
                    </Select>
                    <TextInput id="entry-started-at" type="date" bind:value={entryFormData.started_at} label="Started At" />
                    {#if vnListType === 'custom' || vnListType === 'completed'}
                        <TextInput id="entry-completed-at" type="date" bind:value={entryFormData.completed_at} label="Completed At" />
                    {/if}
                </div>

                <div>
                    <Textarea id="entry-public-notes" bind:value={entryFormData.personal_notes} rows={4} label="Public Notes"></Textarea>
                    <p class="mt-1 text-xs text-fg-muted">These notes will be visible to anyone who can see this list.</p>
                </div>

                <div>
                    <Textarea id="entry-private-notes" bind:value={entryFormData.private_notes} rows={4} label="Private Notes"></Textarea>
                    <p class="mt-1 text-xs text-fg-muted">These notes will only be visible to you, even if the list is public.</p>
                </div>

                <div class="flex justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        tone="neutral"
                        size="sm"
                        onclick={() => {
                            onCancelEditing();
                        }}>Cancel</Button
                    >
                    <Button
                        type="button"
                        variant="solid"
                        tone="primary"
                        size="sm"
                        onclick={() => onSaveEntry(entry.id)}
                        disabled={entryFormLoading}
                        loading={entryFormLoading}>Save Changes</Button
                    >
                </div>
            </div>
        </div>
    {/if}

    {#if isMoving && isOwner && moveLists.length > 0}
        <div class="border-t border-border bg-surface-alt p-4">
            <div class="space-y-4">
                <div>
                    <Select id="entry-target-list" bind:value={entryFormData.target_list_id} label="Target List">
                        <option value="">Select a list...</option>
                        {#each moveLists as list (list.id)}
                            <option value={list.id}>{list.name} ({list.type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())})</option>
                        {/each}
                    </Select>
                </div>

                <div class="flex justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        tone="neutral"
                        size="sm"
                        onclick={() => {
                            onCancelMoving();
                        }}>Cancel</Button
                    >
                    <Button
                        type="button"
                        variant="solid"
                        tone="warning"
                        size="sm"
                        onclick={() => onMoveEntry(entry.id)}
                        disabled={entryFormLoading || !entryFormData.target_list_id}
                        loading={entryFormLoading}>Move to List</Button
                    >
                </div>
            </div>
        </div>
    {/if}
</div>
