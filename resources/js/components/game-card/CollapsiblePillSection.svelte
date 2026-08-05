<script lang="ts">
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
    import type { Snippet } from 'svelte';

    let {
        children,
        expanded,
        hiddenCount,
        onToggle,
        itemName,
        collapsedClass,
    }: {
        children: Snippet;
        expanded: boolean;
        hiddenCount: number;
        onToggle: () => void;
        itemName: string;
        collapsedClass: string;
    } = $props();

    const toggleLabel = $derived(expanded ? 'Show less' : `Show ${hiddenCount} more ${itemName}`);
</script>

<div class="border-t border-gray-100 pt-2 dark:border-gray-700/50">
    <div class="flex items-center gap-1.5">
        <div class="relative flex flex-1 flex-wrap items-start gap-1.5 transition-all duration-300 {expanded ? 'max-h-none' : collapsedClass}">
            {@render children()}
        </div>
        {#if hiddenCount > 0 || expanded}
            <button
                type="button"
                onclick={onToggle}
                class="group flex h-6 w-6 flex-shrink-0 cursor-pointer items-center justify-center rounded-full transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                title={toggleLabel}
                aria-label={toggleLabel}
            >
                <ChevronDownIcon
                    class="h-4 w-4 text-gray-400 transition-all duration-200 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300 {expanded
                        ? 'rotate-180'
                        : 'rotate-0'}"
                />
            </button>
        {/if}
    </div>
</div>
