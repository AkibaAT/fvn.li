<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';
    import axios from 'axios';

    interface Game {
        id: number;
        title: string;
        slug: string;
        versions: Array<{ id: number; version: string; published_at: string }>;
    }

    interface VersionComparisonData {
        fromVersion: { id: number; version: string; published_at: string };
        toVersion: { id: number; version: string; published_at: string };
        characters: string[];
        languages: Array<{ id: string; name: string; flag: string }>;
        characterDiffs: Record<string, Record<string, { from: number; to: number; diff: number }>>;
        languageTotals: { from: Record<string, number>; to: Record<string, number>; diff: Record<string, number> };
        fileCategories: Array<{
            category: string;
            from: { count: number; size: number };
            to: { count: number; size: number };
            diff: { count: number; size: number };
            fileTypes: Record<
                string,
                { from: { count: number; size: number }; to: { count: number; size: number }; diff: { count: number; size: number } }
            >;
        }>;
    }

    let games = $state<Game[]>([]);
    let selectedGame = $state<number | null>(null);
    let fromVersionId = $state<number | null>(null);
    let toVersionId = $state<number | null>(null);
    let comparisonData = $state<VersionComparisonData | null>(null);
    let loading = $state(false);
    let loadingGames = $state(true);
    let error = $state<string | null>(null);
    let activeTab = $state<'character' | 'file'>('character');

    $effect(() => {
        (async () => {
            loadingGames = true;
            try {
                const response = await axios.get(route('dashboard.version-comparison'));
                games = response.data.games;
            } catch (err) {
                console.error('Error fetching games:', err);
                error = 'Failed to load games. Please try again.';
            } finally {
                loadingGames = false;
            }
        })();
    });

    async function fetchComparisonData() {
        if (!selectedGame || !fromVersionId || !toVersionId) return;
        loading = true;
        error = null;
        try {
            const response = await axios.post(route('dashboard.version-comparison'), {
                gameId: selectedGame,
                fromVersionId,
                toVersionId,
            });
            comparisonData = response.data;
        } catch (err) {
            console.error('Error fetching comparison data:', err);
            error = 'Failed to load comparison data. Please try again.';
        } finally {
            loading = false;
        }
    }

    function handleGameChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        selectedGame = parseInt(target.value);
        fromVersionId = null;
        toVersionId = null;
        comparisonData = null;
    }

    function handleSubmit(e: Event) {
        e.preventDefault();
        fetchComparisonData();
    }

    function formatBytes(bytes: number): string {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    function formatNumber(num: number): string {
        return num === 0 ? '-' : num.toLocaleString();
    }

    function getDiffColor(diff: number): string {
        if (diff > 0) return 'text-green-400';
        if (diff < 0) return 'text-red-400';
        return 'text-gray-400';
    }

    function formatDiff(diff: number): string {
        if (diff === 0) return '-';
        return (diff > 0 ? '+' : '') + formatNumber(diff);
    }

    const selectedGameData = $derived(games.find((game) => game.id === selectedGame));
</script>

<svelte:head>
    <title>Version Comparison</title>
</svelte:head>

<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <Card padding="none" class="overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Version Comparison Tool</h1>
                    <Link
                        href={route('dashboard')}
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >Back to Dashboard</Link
                    >
                </div>

                <div class="mb-8">
                    <form onsubmit={handleSubmit} class="space-y-4">
                        <div>
                            <label for="game" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Game</label>
                            <select
                                id="game"
                                value={selectedGame || ''}
                                onchange={handleGameChange}
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                disabled={loadingGames}
                            >
                                <option value="">Choose a game...</option>
                                {#each games as game (game.id)}
                                    <option value={game.id}>{game.title}</option>
                                {/each}
                            </select>
                        </div>

                        {#if selectedGameData}
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="fromVersion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">From Version</label>
                                    <select
                                        id="fromVersion"
                                        value={fromVersionId || ''}
                                        onchange={(e) => (fromVersionId = parseInt((e.target as HTMLSelectElement).value))}
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Select version...</option>
                                        {#each selectedGameData.versions as version (version.id)}
                                            <option value={version.id}
                                                >{version.version} ({new Date(version.published_at).toLocaleDateString()})</option
                                            >
                                        {/each}
                                    </select>
                                </div>
                                <div>
                                    <label for="toVersion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">To Version</label>
                                    <select
                                        id="toVersion"
                                        value={toVersionId || ''}
                                        onchange={(e) => (toVersionId = parseInt((e.target as HTMLSelectElement).value))}
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Select version...</option>
                                        {#each selectedGameData.versions as version (version.id)}
                                            <option value={version.id}
                                                >{version.version} ({new Date(version.published_at).toLocaleDateString()})</option
                                            >
                                        {/each}
                                    </select>
                                </div>
                            </div>
                        {/if}

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                variant="solid"
                                tone="info"
                                disabled={!selectedGame || !fromVersionId || !toVersionId || loading}
                                {loading}
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {loading ? 'Comparing...' : 'Compare Versions'}
                            </Button>
                        </div>
                    </form>
                </div>

                {#if error}
                    <div class="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">{error}</h3>
                    </div>
                {/if}

                {#if comparisonData}
                    <div class="mt-8">
                        <div class="mb-6">
                            <div class="flex flex-col items-center justify-between gap-4 rounded-lg bg-gray-100 p-4 md:flex-row dark:bg-gray-700/50">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Comparing</h3>
                                    <div class="mt-1 flex items-center gap-2">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            Version {comparisonData.fromVersion.version}
                                            <span class="text-sm text-gray-500 dark:text-gray-400"
                                                >({new Date(comparisonData.fromVersion.published_at).toLocaleDateString()})</span
                                            >
                                        </div>
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            Version {comparisonData.toVersion.version}
                                            <span class="text-sm text-gray-500 dark:text-gray-400"
                                                >({new Date(comparisonData.toVersion.published_at).toLocaleDateString()})</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="mb-8">
                            <ul class="flex border-b border-gray-200 text-sm dark:border-gray-700" role="tablist">
                                <li class="mr-1">
                                    <Button
                                        type="button"
                                        variant="link"
                                        tone="info"
                                        class="border-b-2 px-4 py-2 focus:outline-none {activeTab === 'character'
                                            ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'}"
                                        onclick={() => (activeTab = 'character')}>Character Stats</Button
                                    >
                                </li>
                                <li class="mr-1">
                                    <Button
                                        type="button"
                                        variant="link"
                                        tone="info"
                                        class="border-b-2 px-4 py-2 focus:outline-none {activeTab === 'file'
                                            ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'}"
                                        onclick={() => (activeTab = 'file')}>File Stats</Button
                                    >
                                </li>
                            </ul>

                            {#if activeTab === 'character'}
                                <div class="overflow-x-auto pt-4">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                                <th class="px-2 py-2 text-left font-medium text-gray-900 dark:text-white">Character</th>
                                                {#each comparisonData.languages as lang, index (lang.id)}
                                                    {#if index > 0}
                                                        <th class="m-0 w-px bg-gray-200 p-0 dark:bg-gray-600"
                                                            ><div class="h-full w-px">&nbsp;</div></th
                                                        >
                                                    {/if}
                                                    <th class="px-2 py-2 text-right font-medium text-gray-900 dark:text-white" colspan="3">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <span class="fi fi-{lang.flag} rounded-xs"></span>
                                                            <span>{lang.name}</span>
                                                        </div>
                                                    </th>
                                                {/each}
                                            </tr>
                                            <tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                                <th class="px-2 py-2 text-left"></th>
                                                {#each comparisonData.languages as _lang, index (_lang.id)}
                                                    {#if index > 0}
                                                        <th class="m-0 w-px bg-gray-200 p-0 dark:bg-gray-600"
                                                            ><div class="h-full w-px">&nbsp;</div></th
                                                        >
                                                    {/if}
                                                    <th class="px-2 py-2 text-right">Old</th>
                                                    <th class="px-2 py-2 text-right">New</th>
                                                    <th class="px-2 py-2 text-right">Diff</th>
                                                {/each}
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            {#each comparisonData.characters as character (character)}
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                    <td class="px-2 py-2 text-gray-900 dark:text-white">{character}</td>
                                                    {#each comparisonData.languages as _lang, index (_lang.id)}
                                                        {@const stats = comparisonData.characterDiffs[character]?.[_lang.id]}
                                                        {@const fromCount = stats?.from || 0}
                                                        {@const toCount = stats?.to || 0}
                                                        {@const diff = stats?.diff || 0}
                                                        {#if index > 0}
                                                            <td class="m-0 w-px bg-gray-200 p-0 dark:bg-gray-600"
                                                                ><div class="h-full w-px">&nbsp;</div></td
                                                            >
                                                        {/if}
                                                        <td class="px-2 py-2 text-right text-gray-500 tabular-nums dark:text-gray-400"
                                                            >{formatNumber(fromCount)}</td
                                                        >
                                                        <td class="px-2 py-2 text-right text-gray-900 tabular-nums dark:text-white"
                                                            >{formatNumber(toCount)}</td
                                                        >
                                                        <td class="px-2 py-2 text-right tabular-nums {getDiffColor(diff)}">{formatDiff(diff)}</td>
                                                    {/each}
                                                </tr>
                                            {/each}
                                        </tbody>
                                        <tfoot class="border-t border-gray-200 font-medium dark:border-gray-700">
                                            <tr>
                                                <td class="px-2 py-2 text-gray-900 dark:text-white">Total</td>
                                                {#each comparisonData.languages as lang, index (lang.id)}
                                                    {@const fromTotal = comparisonData.languageTotals.from[lang.id] || 0}
                                                    {@const toTotal = comparisonData.languageTotals.to[lang.id] || 0}
                                                    {@const diffTotal = comparisonData.languageTotals.diff[lang.id] || 0}
                                                    {#if index > 0}
                                                        <td class="m-0 w-px bg-gray-200 p-0 dark:bg-gray-600"
                                                            ><div class="h-full w-px">&nbsp;</div></td
                                                        >
                                                    {/if}
                                                    <td class="px-2 py-2 text-right text-gray-500 tabular-nums dark:text-gray-400"
                                                        >{formatNumber(fromTotal)}</td
                                                    >
                                                    <td class="px-2 py-2 text-right text-gray-900 tabular-nums dark:text-white"
                                                        >{formatNumber(toTotal)}</td
                                                    >
                                                    <td class="px-2 py-2 text-right tabular-nums {getDiffColor(diffTotal)}"
                                                        >{formatDiff(diffTotal)}</td
                                                    >
                                                {/each}
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            {/if}

                            {#if activeTab === 'file'}
                                <div class="space-y-6 pt-4">
                                    <div>
                                        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">File Summary</h3>
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                            {#each comparisonData.fileCategories as category (category.category)}
                                                <div class="rounded-lg bg-gray-100 p-4 dark:bg-gray-700/50">
                                                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                        {category.category.charAt(0).toUpperCase() + category.category.slice(1)}
                                                    </div>
                                                    <div class="mt-1 flex items-baseline">
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            {formatNumber(category.from.count)}
                                                        </div>
                                                        <div class="mx-1 text-gray-400 dark:text-gray-500">&rarr;</div>
                                                        <div class="text-base font-semibold text-gray-900 dark:text-white">
                                                            {formatNumber(category.to.count)}
                                                        </div>
                                                        {#if category.diff.count !== 0}
                                                            <div class="ml-2 text-sm {getDiffColor(category.diff.count)}">
                                                                {formatDiff(category.diff.count)}
                                                            </div>
                                                        {/if}
                                                    </div>
                                                    <div class="mt-1 flex items-baseline text-sm">
                                                        <div class="text-gray-500 dark:text-gray-400">{formatBytes(category.from.size)}</div>
                                                        <div class="mx-1 text-gray-400 dark:text-gray-500">&rarr;</div>
                                                        <div class="text-gray-900 dark:text-white">{formatBytes(category.to.size)}</div>
                                                        {#if category.diff.size !== 0}
                                                            <div class="ml-2 {getDiffColor(category.diff.size)}">
                                                                {category.diff.size > 0 ? '+' : ''}{formatBytes(Math.abs(category.diff.size))}
                                                            </div>
                                                        {/if}
                                                    </div>
                                                </div>
                                            {/each}
                                        </div>
                                    </div>

                                    <div class="space-y-6">
                                        {#each comparisonData.fileCategories as category (category.category)}
                                            {#if Object.keys(category.fileTypes).length > 0}
                                                <div>
                                                    <h4 class="mb-2 text-base font-medium text-gray-900 dark:text-white">
                                                        {category.category.charAt(0).toUpperCase() + category.category.slice(1)} Files
                                                    </h4>
                                                    <div class="overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700/50">
                                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                                            <thead>
                                                                <tr>
                                                                    <th
                                                                        class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                                                        >Type</th
                                                                    >
                                                                    <th
                                                                        class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                                                        colspan="3">Count</th
                                                                    >
                                                                    <th
                                                                        class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                                                        colspan="3">Size</th
                                                                    >
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                                                {#each Object.entries(category.fileTypes) as [extension, typeStats] (extension)}
                                                                    <tr>
                                                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{extension}</td>
                                                                        <td class="px-2 py-2 text-right text-sm text-gray-500 dark:text-gray-400"
                                                                            >{formatNumber(typeStats.from.count)}</td
                                                                        >
                                                                        <td class="px-2 py-2 text-right text-sm text-gray-900 dark:text-white"
                                                                            >{formatNumber(typeStats.to.count)}</td
                                                                        >
                                                                        <td class="px-2 py-2 text-right text-sm {getDiffColor(typeStats.diff.count)}"
                                                                            >{formatDiff(typeStats.diff.count)}</td
                                                                        >
                                                                        <td class="px-2 py-2 text-right text-sm text-gray-500 dark:text-gray-400"
                                                                            >{formatBytes(typeStats.from.size)}</td
                                                                        >
                                                                        <td class="px-2 py-2 text-right text-sm text-gray-900 dark:text-white"
                                                                            >{formatBytes(typeStats.to.size)}</td
                                                                        >
                                                                        <td class="px-2 py-2 text-right text-sm {getDiffColor(typeStats.diff.size)}"
                                                                            >{typeStats.diff.size !== 0
                                                                                ? (typeStats.diff.size > 0 ? '+' : '') +
                                                                                  formatBytes(Math.abs(typeStats.diff.size))
                                                                                : '-'}</td
                                                                        >
                                                                    </tr>
                                                                {/each}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            {/if}
                                        {/each}
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>
        </Card>
    </div>
</div>
