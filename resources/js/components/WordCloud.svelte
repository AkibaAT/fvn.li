<script lang="ts">
    type WordFrequencyData = {
        text: string;
        value: number;
    };

    let {
        data,
        width = 900,
        height = 450,
        onWordClick,
    }: {
        data: WordFrequencyData[];
        width?: number;
        height?: number;
        onWordClick?: (word: string) => void;
    } = $props();

    const sortedData = $derived([...data].sort((a, b) => b.value - a.value));
    const maxValue = $derived(sortedData[0]?.value || 1);
    const minValue = $derived(sortedData[sortedData.length - 1]?.value || 1);

    const getFontSize = (value: number) => {
        const minSize = 12;
        const maxSize = 48;
        if (maxValue === minValue) return maxSize;
        return minSize + ((value - minValue) / (maxValue - minValue)) * (maxSize - minSize);
    };

    const getColor = (index: number) => {
        const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#84cc16', '#6366f1'];
        return colors[index % colors.length];
    };
</script>

{#if sortedData.length === 0}
    <div
        class="flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-700/30"
        style="width: {width}px; height: {height}px"
    >
        <p class="text-gray-500 dark:text-gray-400">No word frequency data available</p>
    </div>
{:else}
    <div
        class="relative overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
        style="width: {width}px; height: {height}px"
    >
        <div class="flex flex-wrap items-center justify-center gap-3 p-6" style="width: 100%; height: 100%">
            {#each sortedData as item, index (item.text)}
                {@const fontSize = getFontSize(item.value)}
                <span
                    class="inline-block cursor-pointer transition-transform hover:scale-110"
                    style="font-size: {fontSize}px; color: {getColor(index)}; font-weight: {fontSize > 30 ? 'bold' : 'normal'}; line-height: 1.2"
                    title="{item.text}: {item.value} occurrences - Click to search"
                    onclick={() => onWordClick?.(item.text)}
                    onkeydown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            onWordClick?.(item.text);
                        }
                    }}
                    role="button"
                    tabindex="0"
                >
                    {item.text}
                </span>
            {/each}
        </div>
    </div>
{/if}
