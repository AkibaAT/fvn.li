<script lang="ts">
    import type { RatingDistribution } from './types';

    type Props = {
        distribution: RatingDistribution;
        total: number;
    };

    let { distribution, total }: Props = $props();
</script>

<div class="space-y-2">
    {#each Object.entries(distribution).sort(([left], [right]) => Number(right) - Number(left)) as [ratingKey, count] (ratingKey)}
        {@const percentage = total > 0 ? (Number(count) / total) * 100 : 0}
        <div class="flex items-center">
            <span class="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">{Number(ratingKey)} Stars</span>
            <div class="mx-2 flex-1">
                <div class="h-4 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full bg-yellow-400 dark:bg-yellow-500" style="width: {percentage}%"></div>
                </div>
            </div>
            <div class="flex w-[11rem] items-center justify-end gap-1 text-sm text-gray-500 dark:text-gray-400">
                <span class="w-[6.5rem] text-right whitespace-nowrap tabular-nums">{Number(count).toLocaleString()}</span>
                <span class="w-[4.5rem] text-right whitespace-nowrap tabular-nums">({percentage.toFixed(1)}%)</span>
            </div>
        </div>
    {/each}
</div>
