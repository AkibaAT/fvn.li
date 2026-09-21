<script lang="ts">
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';

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
    const isDescending = $derived(currentDirection === 'desc');
</script>

<div class="flex items-center gap-2">
    <span class="text-[13px] text-fg-faint">Sort</span>

    <span class="relative flex items-center">
        <select
            id="sort-select"
            aria-label="Sort by"
            value={currentSort || defaultSort}
            onchange={(e) => onSortChange((e.target as HTMLSelectElement).value)}
            class="h-7 cursor-pointer appearance-none rounded-md bg-transparent pr-5 pl-1 text-[13px] font-medium text-fg hover:text-fg focus:outline-none"
        >
            {#each Object.entries(sortOptions) as [value, label] (value)}
                <option {value}>{label}</option>
            {/each}
        </select>
        <ChevronDownIcon class="pointer-events-none absolute right-1 h-3.5 w-3.5 text-fg-muted" />
    </span>

    <button
        type="button"
        onclick={() => onDirectionChange(isDescending ? 'asc' : 'desc')}
        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-[13px] text-fg-muted transition-colors hover:text-fg"
        title={isDescending ? 'Sort descending' : 'Sort ascending'}
        aria-label={isDescending ? 'Sort descending' : 'Sort ascending'}
    >
        {isDescending ? '↓' : '↑'}
    </button>
</div>
