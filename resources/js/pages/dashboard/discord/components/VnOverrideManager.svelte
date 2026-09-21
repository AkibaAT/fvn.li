<script lang="ts">
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
    import PhotoPlaceholderIcon from '@/components/icons/PhotoPlaceholder.svelte';
    import TrashIcon from '@/components/icons/Trash.svelte';
    import {
        createGameOverride,
        deleteGameOverride,
        searchGames as searchGamesRequest,
        updateGameOverride,
        type DiscordChannel,
        type GameOverride,
        type GameSearchResult,
    } from '@/api/discord';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { toast } from '@/utils/toast';
    import { Card, Switch } from '@/components/ui';
    import ChannelPicker from './ChannelPicker.svelte';
    import { SvelteMap } from 'svelte/reactivity';

    interface Props {
        overrides: GameOverride[];
        serverId: number;
        channels: DiscordChannel[];
        onchange: (overrides: GameOverride[]) => void;
        filter?: 'ignored' | 'all';
    }

    let { overrides, serverId, channels, onchange, filter = 'all' }: Props = $props();

    const uid = $props.id();

    let searchQuery = $state('');
    let searchResults = $state<GameSearchResult[]>([]);
    let searching = $state(false);
    let showSearch = $state(false);
    let adding = $state(false);
    let deleteConfirmId = $state<number | null>(null);
    const savedOverrides = new SvelteMap<number, GameOverride>();
    const pendingSaves: { id: number; changes: { is_ignored?: boolean; channel_id?: string | null } }[] = [];

    const filteredOverrides = $derived(filter === 'ignored' ? overrides.filter((o) => o.is_ignored) : overrides);

    $effect(() => {
        const query = searchQuery.trim();
        searchResults = [];
        searching = showSearch && query.length >= 2;
        if (!showSearch || query.length < 2) return;

        let active = true;
        const timeout = setTimeout(async () => {
            try {
                const results = await searchGamesRequest(query, 10);
                if (!active) return;
                searchResults = results
                    .map((game) => ({ ...game, thumb_url: game.thumb_url || game.cover_image }))
                    .filter((game) => !overrides.some((override) => override.game_id === game.id));
            } catch {
                if (active) searchResults = [];
            } finally {
                if (active) searching = false;
            }
        }, 300);

        return () => {
            active = false;
            clearTimeout(timeout);
        };
    });

    async function addOverride(game: GameSearchResult, isIgnored = false) {
        if (adding) return;
        adding = true;
        try {
            const override = await createGameOverride(serverId, {
                game_id: game.id,
                is_ignored: isIgnored,
                channel_id: null,
            });
            onchange([...overrides.filter((o) => o.id !== override.id && o.game_id !== override.game_id), override]);
            searchQuery = '';
            searchResults = [];
            toast.success(`Added override for ${game.name}`);
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to add override');
        } finally {
            adding = false;
        }
    }

    async function saveOverride(override: GameOverride, changes: { is_ignored?: boolean; channel_id?: string | null }) {
        const id = override.id;
        if (!savedOverrides.has(id)) savedOverrides.set(id, $state.snapshot(override));
        const save = { id, changes: { ...changes } };
        pendingSaves.push(save);
        onchange(overrides.map((o) => (o.id === id ? { ...o, ...save.changes } : o)));
        try {
            savedOverrides.set(id, await updateGameOverride(serverId, id, save.changes));
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to update override');
        } finally {
            pendingSaves.splice(pendingSaves.indexOf(save), 1);
            const remaining = pendingSaves.filter((pending) => pending.id === id);
            const current = Object.assign({}, savedOverrides.get(id), ...remaining.map((pending) => pending.changes));
            onchange(overrides.map((o) => (o.id === id ? current : o)));
            if (remaining.length === 0) savedOverrides.delete(id);
        }
    }

    async function deleteOverride(overrideId: number) {
        try {
            await deleteGameOverride(serverId, overrideId);
            onchange(overrides.filter((o) => o.id !== overrideId));
            deleteConfirmId = null;
            toast.success('Override deleted');
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to delete override');
        }
    }
</script>

<Card variant="flat" padding="lg">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-fg">
                {filter === 'ignored' ? 'Ignored Visual Novels' : 'VN Overrides'}
            </h2>
            <p class="text-sm text-fg-muted">
                {filter === 'ignored' ? 'Games in this list will never trigger notifications' : 'Per-game routing and embed overrides'}
            </p>
        </div>
        <button
            onclick={() => (showSearch = !showSearch)}
            class="inline-flex items-center gap-2 rounded-md bg-accent px-3 py-2 text-sm font-medium text-on-accent transition-colors hover:opacity-90"
        >
            <MagnifyingGlassIcon class="h-4 w-4" />
            {showSearch ? 'Close Search' : 'Add VN'}
        </button>
    </div>

    {#if showSearch}
        <div class="mb-6 rounded-lg border border-border bg-surface-alt p-4">
            <label for="{uid}-vn-search" class="block text-sm font-medium text-fg-muted">Search for a visual novel</label>
            <div class="relative mt-2">
                <input
                    id="{uid}-vn-search"
                    type="text"
                    bind:value={searchQuery}
                    placeholder="Type to search..."
                    class="w-full rounded-md border border-border bg-surface-alt px-3 py-2 pr-10 text-sm text-fg placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
                />
                {#if searching}
                    <LoadingSpinner size="sm" class="absolute top-2.5 right-3 text-fg-faint" currentColor label="Searching games" />
                {/if}
            </div>
            {#if searchResults.length > 0}
                <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-border bg-surface">
                    {#each searchResults as game (game.id)}
                        <div class="flex items-center justify-between border-b border-border px-3 py-2 last:border-0">
                            <div class="flex items-center gap-2">
                                {#if game.thumb_url}
                                    <img src={game.thumb_url} alt="" class="h-8 w-8 rounded-[4px] object-cover" />
                                {:else}
                                    <div class="flex h-8 w-8 items-center justify-center rounded-[4px] bg-surface-alt">
                                        <PhotoPlaceholderIcon class="h-4 w-4 text-fg-faint" />
                                    </div>
                                {/if}
                                <span class="text-sm font-medium text-fg">{game.name}</span>
                            </div>
                            <div class="flex gap-1">
                                <button
                                    onclick={() => addOverride(game, filter === 'ignored')}
                                    disabled={adding}
                                    class="rounded-md bg-accent px-2 py-1 text-xs font-medium text-on-accent hover:opacity-90"
                                >
                                    {filter === 'ignored' ? 'Ignore' : 'Add Override'}
                                </button>
                            </div>
                        </div>
                    {/each}
                </div>
            {:else if searchQuery.trim().length >= 2 && !searching}
                <div class="mt-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-fg-muted">No matching visual novels found.</div>
            {/if}
        </div>
    {/if}

    {#if filteredOverrides.length === 0}
        <div class="py-8 text-center">
            <p class="text-sm font-medium text-fg-muted">
                {filter === 'ignored' ? 'No ignored visual novels' : 'No overrides configured'}
            </p>
            <p class="mt-1 text-xs text-fg-faint">Click "Add VN" to search and add games</p>
        </div>
    {:else}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Visual Novel</th>
                        <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Ignored</th>
                        {#if filter !== 'ignored'}
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Channel Override</th>
                        {/if}
                        <th class="px-4 py-3 text-right text-xs font-medium tracking-wider text-fg-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each filteredOverrides as override (override.id)}
                        <tr class="hover:bg-surface-alt">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    {#if override.game?.thumb_url}
                                        <img src={override.game.thumb_url} alt="" class="h-8 w-8 shrink-0 rounded-[4px] object-cover" />
                                    {:else}
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[4px] bg-surface-alt">
                                            <PhotoPlaceholderIcon class="h-4 w-4 text-fg-faint" />
                                        </div>
                                    {/if}
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-fg">
                                            {override.game?.name || `Game #${override.game_id}`}
                                        </div>
                                        <div class="text-xs text-fg-faint">ID: {override.game_id}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Switch
                                    checked={override.is_ignored}
                                    onchange={() => saveOverride(override, { is_ignored: !override.is_ignored })}
                                    ariaLabel={`Ignore ${override.game?.name || `game ${override.game_id}`}`}
                                    size="sm"
                                    tone="danger"
                                />
                            </td>
                            {#if filter !== 'ignored'}
                                <td class="px-4 py-3">
                                    {#if channels.length > 0}
                                        <ChannelPicker
                                            items={channels}
                                            value={override.channel_id}
                                            placeholder="Default channel"
                                            searchPlaceholder="Type to filter channels..."
                                            allowNone
                                            noneLabel="Default channel"
                                            onselect={(channelId) => saveOverride(override, { channel_id: channelId })}
                                        />
                                    {:else}
                                        <input
                                            type="text"
                                            value={override.channel_id || ''}
                                            placeholder="Enter channel ID"
                                            onchange={(event) =>
                                                saveOverride(override, { channel_id: (event.target as HTMLInputElement).value.trim() || null })}
                                            class="w-full rounded-md border border-border bg-surface-alt px-2 py-1 text-sm text-fg placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
                                        />
                                    {/if}
                                </td>
                            {/if}
                            <td class="px-4 py-3 text-right">
                                {#if deleteConfirmId === override.id}
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="text-xs text-red-600 dark:text-red-400">Delete?</span>
                                        <button
                                            onclick={() => deleteOverride(override.id)}
                                            class="rounded-md bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700"
                                        >
                                            Confirm
                                        </button>
                                        <button
                                            onclick={() => (deleteConfirmId = null)}
                                            class="rounded-md bg-surface-alt px-2 py-1 text-xs text-fg-muted hover:text-fg"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                {:else}
                                    <button
                                        onclick={() => (deleteConfirmId = override.id)}
                                        class="rounded-md p-1 text-fg-faint transition-colors hover:text-red-600 dark:hover:text-red-400"
                                        title="Delete override"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                {/if}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}
</Card>
