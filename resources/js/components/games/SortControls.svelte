<script lang="ts">
    import { Button, Select } from '@/components/ui';

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
    <Select id="sort-select" value={currentSort || defaultSort} onchange={(e) => onSortChange((e.target as HTMLSelectElement).value)} class="py-1">
        {#each Object.entries(sortOptions) as [value, label] (value)}
            <option {value}>{label}</option>
        {/each}
    </Select>
    <div class="inline-flex rounded-md shadow-sm" role="group" aria-label="Sort direction">
        <Button
            type="button"
            variant="outline"
            tone="neutral"
            size="xs"
            onclick={() => onDirectionChange('desc')}
            class="rounded-r-none dark:border-gray-600 {currentDirection === 'desc'
                ? 'bg-gray-100 text-gray-900 dark:bg-gray-600 dark:text-white'
                : 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-200'}"
        >
            Desc
        </Button>
        <Button
            type="button"
            variant="outline"
            tone="neutral"
            size="xs"
            onclick={() => onDirectionChange('asc')}
            class="-ml-px rounded-l-none dark:border-gray-600 {currentDirection === 'asc'
                ? 'bg-gray-100 text-gray-900 dark:bg-gray-600 dark:text-white'
                : 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-200'}"
        >
            Asc
        </Button>
    </div>
</div>
