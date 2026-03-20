<script lang="ts">
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';

    interface Props {
        showVersionComparison: boolean;
        versionComparisonData: {
            fromVersion: { version: string; published_at: string };
            toVersion: { version: string; published_at: string };
            languages: Array<{ id: string; flag: string; name: string }>;
            characters: string[];
            characterDiffs: Record<string, Record<string, { from: number; to: number; diff: number }>>;
            languageTotals: {
                from: Record<string, number>;
                to: Record<string, number>;
                diff: Record<string, number>;
            };
            fileCategories?: Array<{
                category: string;
                from: { count: number; size: number };
                to: { count: number; size: number };
                diff: { count: number; size: number };
                fileTypes?: Record<
                    string,
                    {
                        from: { count: number; size: number };
                        to: { count: number; size: number };
                        diff: { count: number; size: number };
                    }
                >;
            }>;
        } | null;
        isLoadingComparison: boolean;
        activeComparisonTab: 'character' | 'file';
        setActiveComparisonTab: (tab: 'character' | 'file') => void;
        closeVersionComparisonDialog: () => void;
        formatBytes: (bytes: number) => string;
    }

    let {
        showVersionComparison,
        versionComparisonData,
        isLoadingComparison,
        activeComparisonTab,
        setActiveComparisonTab,
        closeVersionComparisonDialog,
        formatBytes,
    }: Props = $props();

    let closeBtnEl = $state<HTMLButtonElement | undefined>(undefined);
    let openerEl: HTMLElement | null = null;

    $effect(() => {
        const dialogEl = document.getElementById('version-comparison-dialog') as HTMLDialogElement | null;
        if (!dialogEl) return;

        const handleClose = () => {
            openerEl?.focus?.();
            openerEl = null;
        };

        dialogEl.addEventListener('close', handleClose);

        if (showVersionComparison && dialogEl.open) {
            openerEl = (document.activeElement as HTMLElement) || null;
            requestAnimationFrame(() => {
                closeBtnEl?.focus();
            });
        }

        return () => {
            dialogEl.removeEventListener('close', handleClose);
        };
    });

    function handleBackdropClick(e: MouseEvent) {
        const rect = (e.target as HTMLElement).getBoundingClientRect();
        if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
            closeVersionComparisonDialog();
        }
    }

    function fileCategoriesWithTypes(data: typeof versionComparisonData) {
        return data?.fileCategories?.filter((c) => c.fileTypes && Object.keys(c.fileTypes).length > 0) || [];
    }
</script>

{#if showVersionComparison}
    <dialog
        id="version-comparison-dialog"
        aria-modal="true"
        aria-labelledby="version-comparison-title"
        aria-describedby="version-comparison-desc"
        class="m-auto max-w-6xl min-w-80 rounded-lg bg-gray-800 p-6 text-gray-100 shadow-xl backdrop:backdrop-blur-md"
        onclick={handleBackdropClick}
    >
        <h1 id="version-comparison-title" class="sr-only">Version Comparison</h1>
        <p id="version-comparison-desc" class="sr-only">Compare character word counts and file statistics across two versions.</p>

        <div class="mb-4 flex items-center justify-between border-b border-gray-700 pb-4">
            <h2 class="text-2xl font-bold text-gray-100">Version Comparison</h2>
            <button bind:this={closeBtnEl} onclick={closeVersionComparisonDialog} class="text-gray-400 hover:text-gray-500" aria-label="Close dialog">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {#if isLoadingComparison}
            <div class="flex flex-col items-center justify-center gap-4 py-12">
                <LoadingSpinner size="lg" />
                <div class="text-center">
                    <div class="mb-2 text-lg font-medium text-gray-100">Comparing Versions</div>
                    <div class="text-sm text-gray-400">Analyzing character and file differences...</div>
                </div>
            </div>
        {:else if versionComparisonData}
            <div>
                <div class="mb-4">
                    <div class="flex flex-col items-center justify-between gap-4 rounded-lg bg-gray-700/50 p-4 md:flex-row">
                        <div>
                            <h3 class="text-sm font-medium text-gray-400">Comparing</h3>
                            <div class="mt-1 flex items-center gap-2">
                                <div class="font-medium text-gray-100">
                                    Version {versionComparisonData.fromVersion.version}
                                    <span class="text-sm text-gray-400">
                                        ({new Date(versionComparisonData.fromVersion.published_at).toLocaleDateString('en-US', {
                                            month: 'short',
                                            day: 'numeric',
                                            year: 'numeric',
                                        })})
                                    </span>
                                </div>
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                                <div class="font-medium text-gray-100">
                                    Version {versionComparisonData.toVersion.version}
                                    <span class="text-sm text-gray-400">
                                        ({new Date(versionComparisonData.toVersion.published_at).toLocaleDateString('en-US', {
                                            month: 'short',
                                            day: 'numeric',
                                            year: 'numeric',
                                        })})
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="mb-8">
                    <ul class="flex border-b border-gray-700 text-sm" role="tablist">
                        <li class="mr-1">
                            <button
                                class="border-b-2 px-4 py-2 focus:outline-none {activeComparisonTab === 'character'
                                    ? 'border-blue-400 text-blue-400'
                                    : 'border-transparent text-gray-400 hover:border-gray-600 hover:text-gray-100'}"
                                role="tab"
                                onclick={() => setActiveComparisonTab('character')}
                            >
                                Character Stats
                            </button>
                        </li>
                        <li class="mr-1">
                            <button
                                class="border-b-2 px-4 py-2 focus:outline-none {activeComparisonTab === 'file'
                                    ? 'border-blue-400 text-blue-400'
                                    : 'border-transparent text-gray-400 hover:border-gray-600 hover:text-gray-100'}"
                                role="tab"
                                onclick={() => setActiveComparisonTab('file')}
                            >
                                File Stats
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Character Stats Tab -->
                {#if activeComparisonTab === 'character'}
                    <div class="pt-4">
                        <div class="-mx-6 max-w-[calc(100vw-3rem)] overflow-x-auto px-6">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-700">
                                        <th class="px-2 py-2 text-left font-medium">Character</th>
                                        {#each versionComparisonData?.languages || [] as lang, index (lang.id)}
                                            {#if index > 0}
                                                <th class="m-0 w-px bg-gray-600 p-0"><div class="h-full w-px">&nbsp;</div></th>
                                            {/if}
                                            <th class="px-2 py-2 text-right font-medium" colspan="3">
                                                <div class="flex items-center justify-end gap-2">
                                                    <span class="fi fi-{lang.flag} rounded-xs"></span>
                                                    <span>{lang.name}</span>
                                                </div>
                                            </th>
                                        {/each}
                                    </tr>
                                    <tr class="border-b border-gray-700 text-xs text-gray-400">
                                        <th class="px-2 py-2 text-left"></th>
                                        {#each versionComparisonData?.languages || [] as lang, index (lang.id)}
                                            {#if index > 0}
                                                <th class="m-0 w-px bg-gray-600 p-0"><div class="h-full w-px">&nbsp;</div></th>
                                            {/if}
                                            <th class="px-2 py-2 text-right">Old</th>
                                            <th class="px-2 py-2 text-right">New</th>
                                            <th class="px-2 py-2 text-right">Diff</th>
                                        {/each}
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    {#each versionComparisonData?.characters || [] as character (character)}
                                        <tr class="hover:bg-gray-700/50">
                                            <td class="px-2 py-2">{character}</td>
                                            {#each versionComparisonData?.languages || [] as lang, index (lang.id)}
                                                {@const stats = versionComparisonData?.characterDiffs?.[character]?.[lang.id] || null}
                                                {@const fromCount = stats ? stats.from : 0}
                                                {@const toCount = stats ? stats.to : 0}
                                                {@const diff = stats ? stats.diff : 0}
                                                {#if index > 0}
                                                    <td class="m-0 w-px bg-gray-600 p-0"><div class="h-full w-px">&nbsp;</div></td>
                                                {/if}
                                                <td class="px-2 py-2 text-right text-gray-400 tabular-nums"
                                                    >{fromCount ? fromCount.toLocaleString() : '-'}</td
                                                >
                                                <td class="px-2 py-2 text-right tabular-nums">{toCount ? toCount.toLocaleString() : '-'}</td>
                                                <td
                                                    class="px-2 py-2 text-right tabular-nums {diff > 0
                                                        ? 'text-green-400'
                                                        : diff < 0
                                                          ? 'text-red-400'
                                                          : 'text-gray-400'}"
                                                >
                                                    {diff !== 0 ? `${diff > 0 ? '+' : ''}${diff.toLocaleString()}` : '-'}
                                                </td>
                                            {/each}
                                        </tr>
                                    {/each}
                                </tbody>
                                <tfoot class="border-t border-gray-700 font-medium">
                                    <tr>
                                        <td class="px-2 py-2">Total</td>
                                        {#each versionComparisonData?.languages || [] as lang, index (lang.id)}
                                            {@const fromTotal = versionComparisonData?.languageTotals?.from?.[lang.id] || 0}
                                            {@const toTotal = versionComparisonData?.languageTotals?.to?.[lang.id] || 0}
                                            {@const diffTotal = versionComparisonData?.languageTotals?.diff?.[lang.id] || 0}
                                            {#if index > 0}
                                                <td class="m-0 w-px bg-gray-600 p-0"><div class="h-full w-px">&nbsp;</div></td>
                                            {/if}
                                            <td class="px-2 py-2 text-right text-gray-400 tabular-nums"
                                                >{fromTotal ? fromTotal.toLocaleString() : '-'}</td
                                            >
                                            <td class="px-2 py-2 text-right tabular-nums">{toTotal ? toTotal.toLocaleString() : '-'}</td>
                                            <td
                                                class="px-2 py-2 text-right tabular-nums {diffTotal > 0
                                                    ? 'text-green-400'
                                                    : diffTotal < 0
                                                      ? 'text-red-400'
                                                      : 'text-gray-400'}"
                                            >
                                                {diffTotal !== 0 ? `${diffTotal > 0 ? '+' : ''}${diffTotal.toLocaleString()}` : '-'}
                                            </td>
                                        {/each}
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                {/if}

                <!-- File Stats Tab -->
                {#if activeComparisonTab === 'file'}
                    <div class="space-y-6 pt-4">
                        <div>
                            <h3 class="mb-4 text-lg font-medium text-gray-100">File Summary</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                {#each versionComparisonData.fileCategories || [] as category (category.category)}
                                    <div class="rounded-lg bg-gray-700/50 p-4">
                                        <div class="text-sm font-medium text-gray-400">
                                            {category.category.charAt(0).toUpperCase() + category.category.slice(1)}
                                        </div>
                                        <div class="mt-1 flex items-baseline">
                                            <div class="text-sm text-gray-400">
                                                {(category.from?.count ?? 0) ? (category.from?.count ?? 0).toLocaleString() : '-'}
                                            </div>
                                            <div class="mx-1 text-gray-500">&rarr;</div>
                                            <div class="text-base font-semibold text-gray-100">
                                                {(category.to?.count ?? 0) ? (category.to?.count ?? 0).toLocaleString() : '-'}
                                            </div>
                                            <div
                                                class="ml-2 text-sm {(category.diff?.count ?? 0) > 0
                                                    ? 'text-green-400'
                                                    : (category.diff?.count ?? 0) < 0
                                                      ? 'text-red-400'
                                                      : 'text-gray-400'}"
                                            >
                                                {(category.diff?.count ?? 0) !== 0
                                                    ? `${(category.diff?.count ?? 0) > 0 ? '+' : ''}${(category.diff?.count ?? 0).toLocaleString()}`
                                                    : ''}
                                            </div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {formatBytes(category.from?.size ?? 0)} &rarr; {formatBytes(category.to?.size ?? 0)}
                                            {#if (category.diff?.size ?? 0) !== 0}
                                                <span class="ml-1 {(category.diff?.size ?? 0) > 0 ? 'text-green-400' : 'text-red-400'}">
                                                    ({(category.diff?.size ?? 0) > 0 ? '+' : ''}{formatBytes(category.diff?.size ?? 0)})
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </div>

                        <div class="space-y-6">
                            {#each fileCategoriesWithTypes(versionComparisonData) as category (category.category)}
                                <div>
                                    <h4 class="mb-2 text-base font-medium text-gray-100">
                                        {category.category.charAt(0).toUpperCase() + category.category.slice(1)} Files
                                    </h4>
                                    <div class="overflow-hidden rounded-lg bg-gray-700/50">
                                        <table class="min-w-full divide-y divide-gray-600">
                                            <thead>
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-400 uppercase"
                                                        >Type</th
                                                    >
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
                                                {#each Object.entries(category.fileTypes || {}) as [extension, typeStats] (extension)}
                                                    <tr>
                                                        <td class="px-4 py-2 text-sm text-gray-100">{extension}</td>
                                                        <td class="px-2 py-2 text-right text-sm text-gray-400"
                                                            >{(typeStats?.from?.count ?? 0).toLocaleString()}</td
                                                        >
                                                        <td class="px-2 py-2 text-right text-sm text-gray-100"
                                                            >{(typeStats?.to?.count ?? 0).toLocaleString()}</td
                                                        >
                                                        <td
                                                            class="px-2 py-2 text-right text-sm {(typeStats?.diff?.count ?? 0) > 0
                                                                ? 'text-green-400'
                                                                : (typeStats?.diff?.count ?? 0) < 0
                                                                  ? 'text-red-400'
                                                                  : 'text-gray-400'}"
                                                        >
                                                            {(typeStats?.diff?.count ?? 0) !== 0
                                                                ? `${(typeStats?.diff?.count ?? 0) > 0 ? '+' : ''}${(typeStats?.diff?.count ?? 0).toLocaleString()}`
                                                                : '-'}
                                                        </td>
                                                        <td class="px-2 py-2 text-right text-sm text-gray-400"
                                                            >{formatBytes(typeStats?.from?.size ?? 0)}</td
                                                        >
                                                        <td class="px-2 py-2 text-right text-sm text-gray-100"
                                                            >{formatBytes(typeStats?.to?.size ?? 0)}</td
                                                        >
                                                        <td
                                                            class="px-2 py-2 text-right text-sm {(typeStats?.diff?.size ?? 0) > 0
                                                                ? 'text-green-400'
                                                                : (typeStats?.diff?.size ?? 0) < 0
                                                                  ? 'text-red-400'
                                                                  : 'text-gray-400'}"
                                                        >
                                                            {(typeStats?.diff?.size ?? 0) !== 0
                                                                ? `${(typeStats?.diff?.size ?? 0) > 0 ? '+' : ''}${formatBytes(typeStats?.diff?.size ?? 0)}`
                                                                : '-'}
                                                        </td>
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
        {:else}
            <div class="py-6 text-center text-gray-500 dark:text-gray-400">No comparison data available.</div>
        {/if}
    </dialog>
{/if}
