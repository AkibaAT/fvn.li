<script lang="ts">
    interface Props {
        currentSort: string;
        currentDirection: 'asc' | 'desc';
        sortOptions: Record<string, string>;
        onSortChange: (sort: string) => void;
        onDirectionChange: (direction: 'asc' | 'desc') => void;
        hasSearch: boolean;
    }

    let { currentSort, currentDirection, sortOptions, onSortChange, onDirectionChange, hasSearch }: Props = $props();

    const defaultSort = $derived(hasSearch ? 'relevance' : 'first_visible_at');
</script>

<div class="flex items-center gap-3">
    <label for="sort-select" class="text-sm text-gray-700 dark:text-gray-300">Sort by</label>
    <select
        id="sort-select"
        value={currentSort || defaultSort}
        onchange={(e) => onSortChange((e.target as HTMLSelectElement).value)}
        class="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
    >
        {#each Object.entries(sortOptions) as [value, label] (value)}
            <option {value}>{label}</option>
        {/each}
    </select>
    <div class="inline-flex rounded-md shadow-sm" role="group" aria-label="Sort direction">
        <button
            type="button"
            onclick={() => onDirectionChange('desc')}
            class="cursor-pointer rounded-l-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-600 {currentDirection === 'desc' ? 'bg-gray-100 text-gray-900 dark:bg-gray-600 dark:text-white' : 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-200'}"
        >
            Desc
        </button>
        <button
            type="button"
            onclick={() => onDirectionChange('asc')}
            class="cursor-pointer -ml-px rounded-r-md border-t border-r border-b border-gray-300 px-3 py-1 text-sm dark:border-gray-600 {currentDirection === 'asc' ? 'bg-gray-100 text-gray-900 dark:bg-gray-600 dark:text-white' : 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-200'}"
        >
            Asc
        </button>
    </div>
</div>
