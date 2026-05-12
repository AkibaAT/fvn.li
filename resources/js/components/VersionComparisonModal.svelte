<script lang="ts">
    import { Button, Dialog } from '@/components/ui';
    import { fetchVersionComparison, type VersionComparisonData } from '@/hooks/api/useGameData';

    interface Props {
        isOpen: boolean;
        onClose: () => void;
        gameId: number;
        fromVersionId?: number;
        toVersionId?: number;
    }

    let { isOpen, onClose, gameId, fromVersionId, toVersionId }: Props = $props();

    let activeTab = $state<'character' | 'file'>('character');
    let comparisonData = $state<VersionComparisonData | null>(null);
    let loading = $state(false);
    let error = $state<string | null>(null);

    const formatBytesUtil = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    const formatNumber = (num: number): string => {
        return num === 0 ? '-' : num.toLocaleString();
    };

    const getDiffColor = (diff: number) => {
        if (diff > 0) return 'text-green-400';
        if (diff < 0) return 'text-red-400';
        return 'text-gray-400';
    };

    const formatDiff = (diff: number) => {
        if (diff === 0) return '-';
        return (diff > 0 ? '+' : '') + formatNumber(diff);
    };

    const formatBytesDiff = (diff: number): string => {
        if (diff === 0) return '-';
        return (diff > 0 ? '+' : '') + formatBytesUtil(Math.abs(diff));
    };

    $effect(() => {
        if (isOpen && fromVersionId && toVersionId && gameId) {
            loading = true;
            error = null;
            fetchVersionComparison({ gameId, fromVersionId, toVersionId })
                .then((data) => {
                    comparisonData = data;
                })
                .catch((err) => {
                    error = err?.message || 'Failed to load comparison data';
                })
                .finally(() => {
                    loading = false;
                });
        }
    });
</script>

<Dialog
    open={isOpen}
    {onClose}
    title="Version Comparison"
    size="full"
    describedBy="version-comparison-desc"
    class="bg-gray-800 text-gray-100 dark:bg-gray-800"
    bodyClass="max-h-[calc(90vh-8rem)] p-6"
>
    <p id="version-comparison-desc" class="sr-only">Compare character word counts and file statistics across two versions.</p>
    {#if loading}
        <div class="flex items-center justify-center p-8">
            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-gray-100"></div>
        </div>
    {/if}

    {#if error}
        <div class="p-4 text-center text-red-400">Failed to load comparison data. Please try again.</div>
    {/if}

    {#if comparisonData}
        <!-- Version Info -->
        <div class="mb-6">
            <div class="flex flex-col items-center justify-between gap-4 rounded-lg bg-gray-700/50 p-4 md:flex-row">
                <div>
                    <h3 class="text-sm font-medium text-gray-400">Comparing</h3>
                    <div class="mt-1 flex items-center gap-2">
                        <div class="font-medium text-gray-100">
                            Version {comparisonData.fromVersion.version}
                            <span class="text-sm text-gray-400">({new Date(comparisonData.fromVersion.published_at).toLocaleDateString()})</span>
                        </div>
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        <div class="font-medium text-gray-100">
                            Version {comparisonData.toVersion.version}
                            <span class="text-sm text-gray-400">({new Date(comparisonData.toVersion.published_at).toLocaleDateString()})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-8">
            <ul class="flex border-b border-gray-700 text-sm" role="tablist">
                <li class="mr-1">
                    <Button
                        type="button"
                        variant="link"
                        class="border-b-2 px-4 py-2 focus:outline-none {activeTab === 'character'
                            ? 'border-blue-400 text-blue-400'
                            : 'border-transparent text-gray-400 hover:border-gray-600 hover:text-gray-100'}"
                        onclick={() => (activeTab = 'character')}
                    >
                        Character Stats
                    </Button>
                </li>
                <li class="mr-1">
                    <Button
                        type="button"
                        variant="link"
                        class="border-b-2 px-4 py-2 focus:outline-none {activeTab === 'file'
                            ? 'border-blue-400 text-blue-400'
                            : 'border-transparent text-gray-400 hover:border-gray-600 hover:text-gray-100'}"
                        onclick={() => (activeTab = 'file')}
                    >
                        File Stats
                    </Button>
                </li>
            </ul>

            <!-- Character Stats Tab -->
            {#if activeTab === 'character'}
                <div class="pt-4">
                    <div class="overflow-hidden rounded-lg bg-gray-700/50">
                        <table class="min-w-full divide-y divide-gray-600 text-sm">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-400 uppercase">Character</th>
                                    {#each comparisonData.languages as lang, index (lang.id)}
                                        {#if index > 0}
                                            <th class="m-0 w-px bg-gray-600 p-0"><div class="h-full w-px">&nbsp;</div></th>
                                        {/if}
                                        <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-400 uppercase" colspan="3">
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="fi fi-{lang.flag} rounded-xs"></span>
                                                <span>{lang.name}</span>
                                            </div>
                                        </th>
                                    {/each}
                                </tr>
                                <tr class="border-b border-gray-600 text-xs text-gray-400">
                                    <th class="px-4 py-1 text-left"></th>
                                    {#each comparisonData.languages as lang, index (lang.id)}
                                        {#if index > 0}
                                            <th class="m-0 w-px bg-gray-600 p-0"><div class="h-full w-px">&nbsp;</div></th>
                                        {/if}
                                        <th class="px-2 py-1 text-right">Old</th>
                                        <th class="px-2 py-1 text-right">New</th>
                                        <th class="px-2 py-1 text-right">Diff</th>
                                    {/each}
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-600">
                                {#each comparisonData.characters as character (character)}
                                    <tr class="hover:bg-gray-700/50">
                                        <td class="px-4 py-2 text-sm text-gray-100">{character}</td>
                                        {#each comparisonData.languages as lang, index (lang.id)}
                                            {@const stats = comparisonData.characterDiffs[character]?.[lang.id]}
                                            {@const fromCount = stats?.from || 0}
                                            {@const toCount = stats?.to || 0}
                                            {@const diff = stats?.diff || 0}
                                            {#if index > 0}
                                                <td class="m-0 w-px bg-gray-600 p-0"><div class="h-full w-px">&nbsp;</div></td>
                                            {/if}
                                            <td class="px-2 py-2 text-right text-sm text-gray-400 tabular-nums">{formatNumber(fromCount)}</td>
                                            <td class="px-2 py-2 text-right text-sm text-gray-100 tabular-nums">{formatNumber(toCount)}</td>
                                            <td class="px-2 py-2 text-right text-sm tabular-nums {getDiffColor(diff)}">{formatDiff(diff)}</td>
                                        {/each}
                                    </tr>
                                {/each}
                            </tbody>
                            <tfoot class="border-t border-gray-600 font-medium">
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-100">Total</td>
                                    {#each comparisonData.languages as lang, index (lang.id)}
                                        {@const fromTotal = comparisonData.languageTotals.from[lang.id] || 0}
                                        {@const toTotal = comparisonData.languageTotals.to[lang.id] || 0}
                                        {@const diffTotal = comparisonData.languageTotals.diff[lang.id] || 0}
                                        {#if index > 0}
                                            <td class="m-0 w-px bg-gray-600 p-0"><div class="h-full w-px">&nbsp;</div></td>
                                        {/if}
                                        <td class="px-2 py-2 text-right text-sm text-gray-400 tabular-nums">{formatNumber(fromTotal)}</td>
                                        <td class="px-2 py-2 text-right text-sm text-gray-100 tabular-nums">{formatNumber(toTotal)}</td>
                                        <td class="px-2 py-2 text-right text-sm tabular-nums {getDiffColor(diffTotal)}">{formatDiff(diffTotal)}</td>
                                    {/each}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            {/if}

            <!-- File Stats Tab -->
            {#if activeTab === 'file'}
                <div class="space-y-6 pt-4">
                    <div>
                        <h3 class="mb-4 text-lg font-medium text-gray-100">File Summary</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            {#each comparisonData.fileCategories as category (category.category)}
                                <div class="rounded-lg bg-gray-700/50 p-4">
                                    <div class="text-sm font-medium text-gray-400">
                                        {category.category.charAt(0).toUpperCase() + category.category.slice(1)}
                                    </div>
                                    <div class="mt-1 flex items-baseline">
                                        <div class="text-sm text-gray-400">{formatNumber(category.from.count)}</div>
                                        <div class="mx-1 text-gray-500">&rarr;</div>
                                        <div class="text-base font-semibold text-gray-100">{formatNumber(category.to.count)}</div>
                                        {#if category.diff.count !== 0}
                                            <div class="ml-2 text-sm {getDiffColor(category.diff.count)}">
                                                {formatDiff(category.diff.count)}
                                            </div>
                                        {/if}
                                    </div>
                                    <div class="mt-1 flex items-baseline text-sm">
                                        <div class="text-gray-400">{formatBytesUtil(category.from.size)}</div>
                                        <div class="mx-1 text-gray-500">&rarr;</div>
                                        <div class="text-gray-100">{formatBytesUtil(category.to.size)}</div>
                                        {#if category.diff.size !== 0}
                                            <div class="ml-2 {getDiffColor(category.diff.size)}">
                                                {formatBytesDiff(category.diff.size)}
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>

                    <div class="space-y-6">
                        {#each comparisonData.fileCategories.filter((c) => Object.keys(c.fileTypes).length > 0) as category (category.category)}
                            <div>
                                <h4 class="mb-2 text-base font-medium text-gray-100">
                                    {category.category.charAt(0).toUpperCase() + category.category.slice(1)} Files
                                </h4>
                                <div class="overflow-hidden rounded-lg bg-gray-700/50">
                                    <table class="min-w-full divide-y divide-gray-600">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-400 uppercase">Type</th>
                                                <th
                                                    class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-400 uppercase"
                                                    colspan="3">Count</th
                                                >
                                                <th
                                                    class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-400 uppercase"
                                                    colspan="3">Size</th
                                                >
                                            </tr>
                                            <tr class="border-b border-gray-700 text-xs text-gray-400">
                                                <th class="px-4 py-1 text-left"></th>
                                                <th class="px-2 py-1 text-right">Old</th>
                                                <th class="px-2 py-1 text-right">New</th>
                                                <th class="px-2 py-1 text-right">Diff</th>
                                                <th class="px-2 py-1 text-right">Old</th>
                                                <th class="px-2 py-1 text-right">New</th>
                                                <th class="px-2 py-1 text-right">Diff</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-600">
                                            {#each Object.entries(category.fileTypes) as [extension, typeStats] (extension)}
                                                <tr>
                                                    <td class="px-4 py-2 text-sm text-gray-100">{extension}</td>
                                                    <td class="px-2 py-2 text-right text-sm text-gray-400">{formatNumber(typeStats.from.count)}</td>
                                                    <td class="px-2 py-2 text-right text-sm text-gray-100">{formatNumber(typeStats.to.count)}</td>
                                                    <td class="px-2 py-2 text-right text-sm {getDiffColor(typeStats.diff.count)}"
                                                        >{formatDiff(typeStats.diff.count)}</td
                                                    >
                                                    <td class="px-2 py-2 text-right text-sm text-gray-400">{formatBytesUtil(typeStats.from.size)}</td>
                                                    <td class="px-2 py-2 text-right text-sm text-gray-100">{formatBytesUtil(typeStats.to.size)}</td>
                                                    <td class="px-2 py-2 text-right text-sm {getDiffColor(typeStats.diff.size)}"
                                                        >{formatBytesDiff(typeStats.diff.size)}</td
                                                    >
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            {/if}
        </div>
    {/if}
</Dialog>
