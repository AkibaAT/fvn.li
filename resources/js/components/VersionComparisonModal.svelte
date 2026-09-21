<script lang="ts">
    import { formatLocalDate } from '@/utils/date-formatting';
    import ArrowRightIcon from '@/components/icons/ArrowRight.svelte';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { Button, Dialog } from '@/components/ui';
    import { formatBytes, formatCount, getDiffColor, formatDiff, formatBytesDiff } from '@/utils/version-comparison';
    import { fetchVersionComparison, type VersionComparisonData } from '@/api/game-data';

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
    let requestSequence = 0;

    $effect(() => {
        if (!isOpen || !fromVersionId || !toVersionId || !gameId) return;

        const requestId = ++requestSequence;
        activeTab = 'character';
        comparisonData = null;
        loading = true;
        error = null;

        fetchVersionComparison({ gameId, fromVersionId, toVersionId })
            .then((data) => {
                if (requestId === requestSequence) comparisonData = data;
            })
            .catch((caughtError) => {
                if (requestId === requestSequence) {
                    error = caughtError instanceof Error ? caughtError.message : 'Failed to load comparison data';
                }
            })
            .finally(() => {
                if (requestId === requestSequence) loading = false;
            });

        return () => {
            requestSequence++;
        };
    });

    function fileCategoriesWithTypes(data: VersionComparisonData) {
        return data.fileCategories.filter((category) => category.fileTypes && Object.keys(category.fileTypes).length > 0);
    }

    let characterTabEl = $state<HTMLElement | null>(null);
    let fileTabEl = $state<HTMLElement | null>(null);

    function handleTabKeydown(event: KeyboardEvent) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        activeTab = activeTab === 'character' ? 'file' : 'character';
        (activeTab === 'character' ? characterTabEl : fileTabEl)?.focus();
    }
</script>

<Dialog
    open={isOpen}
    {onClose}
    title="Version Comparison"
    size="full"
    describedBy="version-comparison-desc"
    class="bg-surface text-fg"
    bodyClass="max-h-[calc(90vh-8rem)] p-6"
>
    <p id="version-comparison-desc" class="sr-only">Compare character word counts and file statistics across two versions.</p>
    {#if loading}
        <div class="flex flex-col items-center justify-center gap-4 py-12">
            <LoadingSpinner size="lg" />
            <div class="text-center">
                <div class="mb-2 text-lg font-medium text-fg">Comparing Versions</div>
                <div class="text-sm text-fg-muted">Analyzing character and file differences...</div>
            </div>
        </div>
    {:else if error}
        <div class="p-4 text-center text-red-600 dark:text-red-400">
            <p>{error}</p>
            <p class="mt-1 text-sm text-fg-muted">Please try again.</p>
        </div>
    {:else if comparisonData}
        <div class="mb-6">
            <div class="flex flex-col items-center justify-between gap-4 rounded-lg border border-border bg-surface-alt p-4 md:flex-row">
                <div>
                    <h3 class="text-sm font-medium text-fg-muted">Comparing</h3>
                    <div class="mt-1 flex items-center gap-2">
                        <div class="font-medium text-fg">
                            Version {comparisonData.fromVersion.version}
                            <span class="text-sm text-fg-muted">({formatLocalDate(comparisonData.fromVersion.published_at)})</span>
                        </div>
                        <ArrowRightIcon class="h-4 w-4 text-fg-muted" />
                        <div class="font-medium text-fg">
                            Version {comparisonData.toVersion.version}
                            <span class="text-sm text-fg-muted">({formatLocalDate(comparisonData.toVersion.published_at)})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-8">
            <div class="flex gap-1 border-b border-border text-sm" role="tablist" aria-label="Comparison views">
                <Button
                    type="button"
                    variant="link"
                    id="version-comparison-character-tab"
                    bind:ref={characterTabEl}
                    class="border-b-2 px-4 py-2 focus:outline-none {activeTab === 'character'
                        ? 'border-fg text-fg'
                        : 'border-transparent text-fg-muted hover:border-border-strong hover:text-fg'}"
                    onclick={() => (activeTab = 'character')}
                    onkeydown={handleTabKeydown}
                    role="tab"
                    tabindex={activeTab === 'character' ? 0 : -1}
                    aria-selected={activeTab === 'character'}
                    aria-controls="version-comparison-character-panel"
                >
                    Character Stats
                </Button>
                <Button
                    type="button"
                    variant="link"
                    id="version-comparison-file-tab"
                    bind:ref={fileTabEl}
                    class="border-b-2 px-4 py-2 focus:outline-none {activeTab === 'file'
                        ? 'border-fg text-fg'
                        : 'border-transparent text-fg-muted hover:border-border-strong hover:text-fg'}"
                    onclick={() => (activeTab = 'file')}
                    onkeydown={handleTabKeydown}
                    role="tab"
                    tabindex={activeTab === 'file' ? 0 : -1}
                    aria-selected={activeTab === 'file'}
                    aria-controls="version-comparison-file-panel"
                >
                    File Stats
                </Button>
            </div>

            <div
                id="version-comparison-character-panel"
                role="tabpanel"
                aria-labelledby="version-comparison-character-tab"
                hidden={activeTab !== 'character'}
                class="pt-4"
            >
                <div class="overflow-x-auto rounded-lg border border-border bg-surface-alt">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Character</th>
                                {#each comparisonData.languages as lang, index (lang.id)}
                                    {#if index > 0}
                                        <th class="m-0 w-px bg-border p-0"><div class="h-full w-px">&nbsp;</div></th>
                                    {/if}
                                    <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-fg-muted uppercase" colspan="3">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="fi fi-{lang.flag} rounded-xs"></span>
                                            <span>{lang.name}</span>
                                        </div>
                                    </th>
                                {/each}
                            </tr>
                            <tr class="border-b border-border text-xs text-fg-muted">
                                <th class="px-4 py-1 text-left"></th>
                                {#each comparisonData.languages as lang, index (lang.id)}
                                    {#if index > 0}
                                        <th class="m-0 w-px bg-border p-0"><div class="h-full w-px">&nbsp;</div></th>
                                    {/if}
                                    <th class="px-2 py-1 text-right">Old</th>
                                    <th class="px-2 py-1 text-right">New</th>
                                    <th class="px-2 py-1 text-right">Diff</th>
                                {/each}
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            {#each comparisonData.characters as character (character)}
                                <tr class="hover:bg-surface-alt">
                                    <td class="px-4 py-2 text-sm text-fg">{character}</td>
                                    {#each comparisonData.languages as lang, index (lang.id)}
                                        {@const stats = comparisonData.characterDiffs[character]?.[lang.id]}
                                        {@const fromCount = stats?.from || 0}
                                        {@const toCount = stats?.to || 0}
                                        {@const diff = stats?.diff || 0}
                                        {#if index > 0}
                                            <td class="m-0 w-px bg-border p-0"><div class="h-full w-px">&nbsp;</div></td>
                                        {/if}
                                        <td class="px-2 py-2 text-right text-sm text-fg-muted tabular-nums">{formatCount(fromCount)}</td>
                                        <td class="px-2 py-2 text-right text-sm text-fg tabular-nums">{formatCount(toCount)}</td>
                                        <td class="px-2 py-2 text-right text-sm tabular-nums {getDiffColor(diff)}">{formatDiff(diff)}</td>
                                    {/each}
                                </tr>
                            {/each}
                        </tbody>
                        <tfoot class="border-t border-border font-medium">
                            <tr>
                                <td class="px-4 py-2 text-sm text-fg">Total</td>
                                {#each comparisonData.languages as lang, index (lang.id)}
                                    {@const fromTotal = comparisonData.languageTotals.from[lang.id] || 0}
                                    {@const toTotal = comparisonData.languageTotals.to[lang.id] || 0}
                                    {@const diffTotal = comparisonData.languageTotals.diff[lang.id] || 0}
                                    {#if index > 0}
                                        <td class="m-0 w-px bg-border p-0"><div class="h-full w-px">&nbsp;</div></td>
                                    {/if}
                                    <td class="px-2 py-2 text-right text-sm text-fg-muted tabular-nums">{formatCount(fromTotal)}</td>
                                    <td class="px-2 py-2 text-right text-sm text-fg tabular-nums">{formatCount(toTotal)}</td>
                                    <td class="px-2 py-2 text-right text-sm tabular-nums {getDiffColor(diffTotal)}">{formatDiff(diffTotal)}</td>
                                {/each}
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div
                id="version-comparison-file-panel"
                role="tabpanel"
                aria-labelledby="version-comparison-file-tab"
                hidden={activeTab !== 'file'}
                class="space-y-6 pt-4"
            >
                <div>
                    <h3 class="mb-4 text-lg font-medium text-fg">File Summary</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {#each comparisonData.fileCategories as category (category.category)}
                            <div class="rounded-lg border border-border bg-surface-alt p-4">
                                <div class="text-sm font-medium text-fg-muted">
                                    {category.category.charAt(0).toUpperCase() + category.category.slice(1)}
                                </div>
                                <div class="mt-1 flex items-baseline">
                                    <div class="text-sm text-fg-muted">{formatCount(category.from.count)}</div>
                                    <div class="mx-1 text-fg-faint">&rarr;</div>
                                    <div class="text-base font-semibold text-fg">{formatCount(category.to.count)}</div>
                                    {#if category.diff.count !== 0}
                                        <div class="ml-2 text-sm {getDiffColor(category.diff.count)}">
                                            {formatDiff(category.diff.count)}
                                        </div>
                                    {/if}
                                </div>
                                <div class="mt-1 flex items-baseline text-sm">
                                    <div class="text-fg-muted">{formatBytes(category.from.size)}</div>
                                    <div class="mx-1 text-fg-faint">&rarr;</div>
                                    <div class="text-fg">{formatBytes(category.to.size)}</div>
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
                    {#each fileCategoriesWithTypes(comparisonData) as category (category.category)}
                        <div>
                            <h4 class="mb-2 text-base font-medium text-fg">
                                {category.category.charAt(0).toUpperCase() + category.category.slice(1)} Files
                            </h4>
                            <div class="overflow-hidden rounded-lg border border-border bg-surface-alt">
                                <table class="min-w-full divide-y divide-border">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Type</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-fg-muted uppercase" colspan="3"
                                                >Count</th
                                            >
                                            <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-fg-muted uppercase" colspan="3"
                                                >Size</th
                                            >
                                        </tr>
                                        <tr class="border-b border-border text-xs text-fg-muted">
                                            <th class="px-4 py-1 text-left"></th>
                                            <th class="px-2 py-1 text-right">Old</th>
                                            <th class="px-2 py-1 text-right">New</th>
                                            <th class="px-2 py-1 text-right">Diff</th>
                                            <th class="px-2 py-1 text-right">Old</th>
                                            <th class="px-2 py-1 text-right">New</th>
                                            <th class="px-2 py-1 text-right">Diff</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        {#each Object.entries(category.fileTypes) as [extension, typeStats] (extension)}
                                            <tr>
                                                <td class="px-4 py-2 text-sm text-fg">{extension}</td>
                                                <td class="px-2 py-2 text-right text-sm text-fg-muted">{formatCount(typeStats.from.count)}</td>
                                                <td class="px-2 py-2 text-right text-sm text-fg">{formatCount(typeStats.to.count)}</td>
                                                <td class="px-2 py-2 text-right text-sm {getDiffColor(typeStats.diff.count)}"
                                                    >{formatDiff(typeStats.diff.count)}</td
                                                >
                                                <td class="px-2 py-2 text-right text-sm text-fg-muted">{formatBytes(typeStats.from.size)}</td>
                                                <td class="px-2 py-2 text-right text-sm text-fg">{formatBytes(typeStats.to.size)}</td>
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
        </div>
    {:else}
        <div class="p-4 text-center text-fg-muted">No comparison data available.</div>
    {/if}
</Dialog>
