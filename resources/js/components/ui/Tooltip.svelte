<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        content: Snippet;
        children?: Snippet;
        position?: 'top' | 'bottom' | 'left' | 'right';
        class?: string;
    }

    let { content, children, position = 'top', class: className = '' }: Props = $props();

    let isVisible = $state(false);
    let tooltipEl = $state<HTMLDivElement | undefined>(undefined);

    const positionClasses: Record<string, string> = {
        top: 'bottom-full left-1/2 mb-2 -translate-x-1/2',
        bottom: 'top-full left-1/2 mt-2 -translate-x-1/2',
        left: 'right-full top-1/2 mr-2 -translate-y-1/2',
        right: 'left-full top-1/2 ml-2 -translate-y-1/2',
    };

    const arrowClasses: Record<string, string> = {
        top: 'top-full left-1/2 -translate-x-1/2 border-l-4 border-r-4 border-t-4 border-l-transparent border-r-transparent',
        bottom: 'bottom-full left-1/2 -translate-x-1/2 border-l-4 border-r-4 border-b-4 border-l-transparent border-r-transparent',
        left: 'left-full top-1/2 -translate-y-1/2 border-t-4 border-b-4 border-l-4 border-t-transparent border-b-transparent',
        right: 'right-full top-1/2 -translate-y-1/2 border-t-4 border-b-4 border-r-4 border-t-transparent border-b-transparent',
    };
</script>

<div class="relative inline-block">
    <div
        onmouseenter={() => isVisible = true}
        onmouseleave={() => isVisible = false}
        onfocus={() => isVisible = true}
        onblur={() => isVisible = false}
        role="tooltip"
    >
        {@render children?.()}
    </div>

    {#if isVisible}
        <div
            bind:this={tooltipEl}
            class={`absolute z-50 w-max rounded-md bg-gray-900 px-3 py-2 text-sm text-white shadow-lg dark:bg-gray-700 ${positionClasses[position]} ${className}`}
            role="tooltip"
        >
            {@render content()}
            <div
                class={`absolute h-0 w-0 border-gray-900 dark:border-gray-700 ${arrowClasses[position]}`}
            ></div>
        </div>
    {/if}
</div>
