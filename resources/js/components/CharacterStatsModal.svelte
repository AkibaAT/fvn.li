<script lang="ts">
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { Button, Dialog } from '@/components/ui';

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

    const hasData = $derived(showCharacterStats === versionId && characterStatsData?.characters && characterStatsData.characters.length > 0);
</script>

<Dialog
    id={`character-stats-${versionId}`}
    open={showCharacterStats === versionId}
    onClose={() => closeCharacterStatsDialog(versionId)}
    title="Character Statistics"
    size="full"
    class="min-w-80"
    bodyClass="max-h-[60vh]"
    describedBy={`character-stats-desc-${versionId}`}
>
    <p id={`character-stats-desc-${versionId}`} class="sr-only">Per-character word counts by language with totals.</p>

    {#if hasData}
        <div class="overflow-x-auto rounded-lg border border-border bg-surface-alt">
            <table class="min-w-full divide-y divide-border">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 bg-surface-alt px-4 py-2 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">
                            Character
                        </th>
                        {#each characterStatsData?.languages || [] as lang (lang.id)}
                            <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-fg-muted uppercase">
                                <div class="flex items-center justify-end gap-2">
                                    <img src={getLanguageFlag(lang.flag)} alt={lang.name} class="h-4 w-4 rounded-sm" />
                                    <span>{lang.name}</span>
                                </div>
                            </th>
                        {/each}
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each characterStatsData?.characters || [] as character (character)}
                        <tr>
                            <td class="sticky left-0 z-10 bg-surface px-4 py-2 text-sm text-fg">
                                {character}
                            </td>
                            {#each characterStatsData?.languages || [] as lang (lang.id)}
                                <td class="px-4 py-2 text-right text-sm text-fg">
                                    {characterStatsData?.wordCounts?.[character]?.[lang.id]
                                        ? characterStatsData.wordCounts[character][lang.id].toLocaleString()
                                        : '-'}
                                </td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
                <tfoot>
                    <tr class="bg-surface-alt">
                        <td class="sticky left-0 z-10 bg-surface-alt px-4 py-2 text-sm font-medium text-fg"> Total </td>
                        {#each characterStatsData?.languages || [] as lang (lang.id)}
                            <td class="px-4 py-2 text-right text-sm font-medium text-fg">
                                {characterStatsData?.languageTotals?.[lang.id]?.toLocaleString() || '0'}
                            </td>
                        {/each}
                    </tr>
                </tfoot>
            </table>
        </div>
    {:else}
        <div class="py-8 text-center text-fg-muted">
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

    <div class="mt-6 flex justify-end">
        <Button onclick={() => closeCharacterStatsDialog(versionId)} type="button" variant="outline" tone="neutral">Close</Button>
    </div>
</Dialog>
