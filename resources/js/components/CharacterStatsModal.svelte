<script lang="ts">
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { isDialogBackdropClick } from '@/utils/dialog';

    interface Props {
        versionId: number;
        showCharacterStats: number | null;
        characterStatsData: {
            characters?: string[];
            languages?: Array<{ id: string; flag: string; name: string }>;
            wordCounts?: Record<string, Record<string, number>>;
            languageTotals?: Record<string, number>;
        } | null;
        statsLoading: boolean;
        closeCharacterStatsDialog: (versionId: number) => void;
        getLanguageFlag: (flag: string) => string;
    }

    let { versionId, showCharacterStats, characterStatsData, statsLoading, closeCharacterStatsDialog, getLanguageFlag }: Props = $props();

    let dialogEl: HTMLDialogElement | null = null;
    let closeBtnEl: HTMLButtonElement;
    let openerEl: HTMLElement | null = null;

    function handleCancel(event: Event) {
        event.preventDefault();
        closeCharacterStatsDialog(versionId);
    }

    function handleBackdropClick(event: MouseEvent) {
        if (isDialogBackdropClick(dialogEl, event)) {
            closeCharacterStatsDialog(versionId);
        }
    }

    $effect(() => {
        if (!dialogEl) return;
        const currentDialogEl = dialogEl;

        const handleClose = () => {
            openerEl?.focus?.();
            openerEl = null;
        };

        currentDialogEl.addEventListener('close', handleClose);

        if (showCharacterStats === versionId && currentDialogEl.open) {
            openerEl = (document.activeElement as HTMLElement) || null;
            requestAnimationFrame(() => {
                closeBtnEl?.focus();
            });
        }

        return () => {
            currentDialogEl.removeEventListener('close', handleClose);
        };
    });

    const hasData = $derived(showCharacterStats === versionId && characterStatsData?.characters && characterStatsData.characters.length > 0);
</script>

<dialog
    bind:this={dialogEl}
    id={`character-stats-${versionId}`}
    aria-modal="true"
    aria-labelledby={`character-stats-title-${versionId}`}
    aria-describedby={`character-stats-desc-${versionId}`}
    onclick={handleBackdropClick}
    oncancel={handleCancel}
    class="m-auto max-w-6xl min-w-80 rounded-lg bg-white p-6 shadow-xl backdrop:backdrop-blur-md dark:bg-gray-800 dark:text-gray-100"
>
    <h1 id={`character-stats-title-${versionId}`} class="sr-only">Character Statistics</h1>
    <p id={`character-stats-desc-${versionId}`} class="sr-only">Per-character word counts by language with totals.</p>

    <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-700">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Character Statistics</h2>
        <button
            bind:this={closeBtnEl}
            onclick={() => closeCharacterStatsDialog(versionId)}
            class="text-gray-400 hover:text-gray-500"
            aria-label="Close dialog"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="max-h-[60vh] overflow-y-auto">
        {#if hasData}
            <div class="overflow-x-auto rounded-lg bg-gray-50 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead>
                        <tr>
                            <th
                                class="sticky left-0 z-10 bg-gray-50 px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase shadow-[2px_0_4px_-2px_rgba(0,0,0,0.1)] dark:bg-gray-800 dark:text-gray-400 dark:shadow-[2px_0_4px_-2px_rgba(0,0,0,0.3)]"
                            >
                                Character
                            </th>
                            {#each characterStatsData?.languages || [] as lang (lang.id)}
                                <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                    <div class="flex items-center justify-end gap-2">
                                        <img src={getLanguageFlag(lang.flag)} alt={lang.name} class="h-4 w-4 rounded-sm" />
                                        <span>{lang.name}</span>
                                    </div>
                                </th>
                            {/each}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                        {#each characterStatsData?.characters || [] as character (character)}
                            <tr>
                                <td
                                    class="sticky left-0 z-10 bg-white px-4 py-2 text-sm text-gray-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.1)] dark:bg-gray-800 dark:text-gray-100 dark:shadow-[2px_0_4px_-2px_rgba(0,0,0,0.3)]"
                                >
                                    {character}
                                </td>
                                {#each characterStatsData?.languages || [] as lang (lang.id)}
                                    <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {characterStatsData?.wordCounts?.[character]?.[lang.id]
                                            ? characterStatsData.wordCounts[character][lang.id].toLocaleString()
                                            : '-'}
                                    </td>
                                {/each}
                            </tr>
                        {/each}
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 dark:bg-gray-700/50">
                            <td
                                class="sticky left-0 z-10 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.1)] dark:bg-gray-700/50 dark:text-gray-100 dark:shadow-[2px_0_4px_-2px_rgba(0,0,0,0.3)]"
                            >
                                Total
                            </td>
                            {#each characterStatsData?.languages || [] as lang (lang.id)}
                                <td class="px-4 py-2 text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {characterStatsData?.languageTotals?.[lang.id]?.toLocaleString() || '0'}
                                </td>
                            {/each}
                        </tr>
                    </tfoot>
                </table>
            </div>
        {:else}
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                {#if statsLoading}
                    <div class="flex flex-col items-center gap-3">
                        <LoadingSpinner size="lg" />
                        <span>Loading character statistics...</span>
                    </div>
                {:else}
                    No character statistics available for this version.
                {/if}
            </div>
        {/if}
    </div>

    <div class="mt-6 flex justify-end">
        <button
            onclick={() => closeCharacterStatsDialog(versionId)}
            type="button"
            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700"
        >
            Close
        </button>
    </div>
</dialog>
