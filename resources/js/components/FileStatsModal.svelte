<script lang="ts">
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { Button, Dialog } from '@/components/ui';

    interface Props {
        versionId: number;
        showFileStats: number | null;
        fileStatsData: {
            version?: { version?: string };
            file_categories?: Array<{
                category: string;
                total_count: number;
                total_size: number;
                file_types: Array<{
                    extension: string;
                    count: number;
                    size: number;
                }>;
            }>;
        } | null;
        statsLoading: boolean;
        closeFileStatsDialog: (versionId: number) => void;
    }

    let { versionId, showFileStats, fileStatsData, statsLoading, closeFileStatsDialog }: Props = $props();

    function formatBytes(bytes: number, precision: number = 2): string {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        bytes = Math.max(bytes, 0);
        const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
        const powClamped = Math.min(pow, units.length - 1);
        const value = bytes / Math.pow(1024, powClamped);
        return `${value.toFixed(precision)} ${units[powClamped]}`;
    }

    const hasData = $derived(showFileStats === versionId && fileStatsData?.file_categories && fileStatsData.file_categories.length > 0);

    const nonEmptyCategories = $derived(fileStatsData?.file_categories?.filter((c) => c.total_count > 0) || []);
</script>

<Dialog
    id={`file-stats-${versionId}`}
    open={showFileStats === versionId}
    onClose={() => closeFileStatsDialog(versionId)}
    title="File Statistics"
    size="lg"
    describedBy={`file-stats-desc-${versionId}`}
    bodyClass="max-h-[60vh]"
>
    <p id={`file-stats-desc-${versionId}`} class="sr-only">Per-category and per-type file counts and sizes for this version.</p>

    {#if hasData}
        <div class="space-y-6">
            <div>
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                    Version {fileStatsData?.version?.version}
                </h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {#each fileStatsData?.file_categories || [] as category, index (index)}
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {category.category.charAt(0).toUpperCase() + category.category.slice(1)}
                            </div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {category.total_count.toLocaleString()}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {formatBytes(category.total_size)}
                            </div>
                        </div>
                    {/each}
                </div>
            </div>

            <div class="space-y-6">
                {#each nonEmptyCategories as category, index (index)}
                    <div>
                        <h4 class="mb-2 text-base font-medium text-gray-900 dark:text-gray-100">
                            {category.category.charAt(0).toUpperCase() + category.category.slice(1)} Files
                        </h4>
                        <div class="overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                            >Type</th
                                        >
                                        <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                            >Count</th
                                        >
                                        <th class="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                            >Size</th
                                        >
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                    {#each category.file_types as fileType, typeIndex (typeIndex)}
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{fileType.extension}</td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100"
                                                >{fileType.count.toLocaleString()}</td
                                            >
                                            <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{formatBytes(fileType.size)}</td
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
    {:else}
        <div class="py-8 text-center text-gray-500 dark:text-gray-400">
            {#if statsLoading}
                <div class="flex flex-col items-center gap-3">
                    <LoadingSpinner size="lg" />
                    <span>Loading file statistics...</span>
                </div>
            {:else}
                No file statistics available for this version.
            {/if}
        </div>
    {/if}

    <div class="mt-6 flex justify-end">
        <Button onclick={() => closeFileStatsDialog(versionId)} type="button" variant="outline" tone="neutral">Close</Button>
    </div>
</Dialog>
