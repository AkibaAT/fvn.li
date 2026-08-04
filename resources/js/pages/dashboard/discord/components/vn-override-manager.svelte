<script lang="ts">
    import { apiFetch } from '@/utils/http';
    import { toast } from '@/utils/toast';
    import { Card, Switch } from '@/components/ui';
    import ChannelPicker from './ChannelPicker.svelte';

    interface GameOverride {
        id: number;
        game_id: number;
        is_ignored: boolean;
        channel_id: string | null;
        new_game_embed: Record<string, unknown> | null;
        update_embed: Record<string, unknown> | null;
        game: { id: number; name: string; slug: string; thumb_url?: string };
    }

    interface DiscordChannel {
        id: string;
        name: string;
        nsfw?: boolean;
    }

    interface SearchResult {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string;
        cover_image?: string;
    }

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
    let searchResults = $state<SearchResult[]>([]);
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
            const data = await apiFetch<SearchResult[] | { games?: SearchResult[] }>(
                `${route('api.games.search')}?q=${encodeURIComponent(query)}&limit=10`,
            );
            const results = Array.isArray(data) ? data : (data.games ?? []);

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

    async function addOverride(game: SearchResult, isIgnored = false) {
        try {
            const data = await apiFetch<{ override: GameOverride }>(route('browser-api.discord.servers.overrides.store', { server: serverId }), {
                method: 'POST',
                body: JSON.stringify({
                    game_id: game.id,
                    is_ignored: isIgnored,
                    channel_id: null,
                }),
            });
            onchange([...overrides, data.override]);
            searchQuery = '';
            searchResults = [];
            toast.success(`Added override for ${game.name}`);
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to add override');
        }
    }

    async function toggleIgnored(override: GameOverride) {
        try {
            const data = await apiFetch<{ override: GameOverride }>(
                route('browser-api.discord.servers.overrides.update', { server: serverId, override: override.id }),
                {
                    method: 'PUT',
                    body: JSON.stringify({ is_ignored: !override.is_ignored }),
                },
            );
            onchange(overrides.map((o) => (o.id === override.id ? data.override : o)));
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to update override');
        }
    }

    async function updateChannel(override: GameOverride, channelId: string | null) {
        try {
            const data = await apiFetch<{ override: GameOverride }>(
                route('browser-api.discord.servers.overrides.update', { server: serverId, override: override.id }),
                {
                    method: 'PUT',
                    body: JSON.stringify({ channel_id: channelId }),
                },
            );
            onchange(overrides.map((o) => (o.id === override.id ? data.override : o)));
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to update channel');
        }
    }

    async function deleteOverride(overrideId: number) {
        try {
            await apiFetch(route('browser-api.discord.servers.overrides.delete', { server: serverId, override: overrideId }), {
                method: 'DELETE',
            });
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
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
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
                    <svg class="absolute top-2.5 right-3 h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
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
                                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            ><path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            /></svg
                                        >
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
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                ><path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                /></svg
                                            >
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
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
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
