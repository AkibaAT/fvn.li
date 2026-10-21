<script lang="ts">
    import { cn } from '@/utils/cn';

    interface TagOption {
        id: number;
        name: string;
        count: number;
    }

    let {
        options,
        selected,
        onToggle,
        onClear,
        class: className = '',
    }: {
        options: TagOption[];
        selected: number[];
        onToggle: (id: number) => void;
        onClear: () => void;
        class?: string;
    } = $props();
</script>

<div class={cn('flex flex-wrap items-center gap-2', className)} role="group" aria-label="Filter by private tag">
    <span class="text-sm font-medium text-fg-muted">Private tags:</span>
    {#each options as option (option.id)}
        {@const isSelected = selected.includes(option.id)}
        <button
            type="button"
            aria-pressed={isSelected}
            onclick={() => onToggle(option.id)}
            class={cn(
                'inline-flex cursor-pointer items-center gap-1.5 rounded-sm border border-dashed px-2 py-0.5 text-ui font-medium transition-colors',
                isSelected ? 'border-solid border-accent-fg bg-accent text-on-accent' : 'border-border-strong text-fg-muted hover:text-fg',
            )}
        >
            {option.name}
            <span class={isSelected ? 'text-on-accent/80' : 'text-fg-faint'}>{option.count}</span>
        </button>
    {/each}
    {#if selected.length > 0}
        <button type="button" onclick={onClear} class="cursor-pointer text-ui text-fg-muted underline underline-offset-2 hover:text-fg">Clear</button>
    {/if}
</div>
