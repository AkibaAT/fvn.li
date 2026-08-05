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
    let deleteConfirmId = $state<number | null>(null);

    const filteredOverrides = $derived(filter === 'ignored' ? overrides.filter((o) => o.is_ignored) : overrides);

    async function searchGames(query: string) {
        if (!query || query.length < 2) {
            searchResults = [];
            return;
        }
        searching = true;
        try {
            const results = await searchGamesRequest(query, 10);

            searchResults = results
                .map((game) => ({
                    ...game,
                    thumb_url: game.thumb_url || game.cover_image,
                }))
                .filter((game) => !overrides.some((override) => override.game_id === game.id));
        } catch {
            searchResults = [];
        } finally {
            searching = false;
        }
    }

    let searchTimeout: ReturnType<typeof setTimeout>;
    function onSearchInput(e: Event) {
        const query = (e.target as HTMLInputElement).value;
        searchQuery = query;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchGames(query), 300);
    }

    async function addOverride(game: GameSearchResult, isIgnored = false) {
        try {
            const override = await createGameOverride(serverId, {
                game_id: game.id,
                is_ignored: isIgnored,
                channel_id: null,
            });
            onchange([...overrides, override]);
            searchQuery = '';
            searchResults = [];
            toast.success(`Added override for ${game.name}`);
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to add override');
        }
    }

    async function toggleIgnored(override: GameOverride) {
        try {
            const updated = await updateGameOverride(serverId, override.id, { is_ignored: !override.is_ignored });
            onchange(overrides.map((o) => (o.id === override.id ? updated : o)));
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to update override');
        }
    }

    async function updateChannel(override: GameOverride, channelId: string | null) {
        try {
            const updated = await updateGameOverride(serverId, override.id, { channel_id: channelId });
            onchange(overrides.map((o) => (o.id === override.id ? updated : o)));
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to update channel');
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

<Card variant="glass" padding="lg">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {filter === 'ignored' ? 'Ignored Visual Novels' : 'VN Overrides'}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {filter === 'ignored' ? 'Games in this list will never trigger notifications' : 'Per-game routing and embed overrides'}
            </p>
        </div>
        <button
            onclick={() => (showSearch = !showSearch)}
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
        >
            <MagnifyingGlassIcon class="h-4 w-4" />
            {showSearch ? 'Close Search' : 'Add VN'}
        </button>
    </div>

    {#if showSearch}
        <div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800/50 dark:bg-indigo-900/20">
            <label for="{uid}-vn-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search for a visual novel</label>
            <div class="relative mt-2">
                <input
                    id="{uid}-vn-search"
                    type="text"
                    value={searchQuery}
                    oninput={onSearchInput}
                    placeholder="Type to search..."
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-10 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                />
                {#if searching}
                    <LoadingSpinner size="sm" class="absolute top-2.5 right-3 text-gray-400" currentColor label="Searching games" />
                {/if}
            </div>
            {#if searchResults.length > 0}
                <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    {#each searchResults as game (game.id)}
                        <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2 last:border-0 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                {#if game.thumb_url}
                                    <img src={game.thumb_url} alt="" class="h-8 w-8 rounded object-cover" />
                                {:else}
                                    <div class="flex h-8 w-8 items-center justify-center rounded bg-gray-200 dark:bg-gray-700">
                                        <PhotoPlaceholderIcon class="h-4 w-4 text-gray-400" />
                                    </div>
                                {/if}
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{game.name}</span>
                            </div>
                            <div class="flex gap-1">
                                <button
                                    onclick={() => addOverride(game, filter === 'ignored')}
                                    class="rounded bg-indigo-600 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-700"
                                >
                                    {filter === 'ignored' ? 'Ignore' : 'Add Override'}
                                </button>
                            </div>
                        </div>
                    {/each}
                </div>
            {:else if searchQuery.trim().length >= 2 && !searching}
                <div
                    class="mt-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                >
                    No matching visual novels found.
                </div>
            {/if}
        </div>
    {/if}

    {#if filteredOverrides.length === 0}
        <div class="py-8 text-center">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                {filter === 'ignored' ? 'No ignored visual novels' : 'No overrides configured'}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">Click "Add VN" to search and add games</p>
        </div>
    {:else}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Visual Novel</th
                        >
                        <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Ignored</th>
                        {#if filter !== 'ignored'}
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >Channel Override</th
                            >
                        {/if}
                        <th class="px-4 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    {#each filteredOverrides as override (override.id)}
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    {#if override.game?.thumb_url}
                                        <img src={override.game.thumb_url} alt="" class="h-8 w-8 shrink-0 rounded object-cover" />
                                    {:else}
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-gray-200 dark:bg-gray-700">
                                            <PhotoPlaceholderIcon class="h-4 w-4 text-gray-400" />
                                        </div>
                                    {/if}
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                            {override.game?.name || `Game #${override.game_id}`}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">ID: {override.game_id}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Switch
                                    checked={override.is_ignored}
                                    onchange={() => toggleIgnored(override)}
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
                                            onselect={(channelId) => updateChannel(override, channelId)}
                                        />
                                    {:else}
                                        <input
                                            type="text"
                                            value={override.channel_id || ''}
                                            placeholder="Enter channel ID"
                                            onchange={(event) => updateChannel(override, (event.target as HTMLInputElement).value.trim() || null)}
                                            class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
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
                                            class="rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700"
                                        >
                                            Confirm
                                        </button>
                                        <button
                                            onclick={() => (deleteConfirmId = null)}
                                            class="rounded bg-gray-200 px-2 py-1 text-xs text-gray-700 hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                {:else}
                                    <button
                                        onclick={() => (deleteConfirmId = override.id)}
                                        class="rounded p-1 text-gray-400 transition-colors hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
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
