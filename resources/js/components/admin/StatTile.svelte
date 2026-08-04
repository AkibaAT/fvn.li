<script lang="ts">
    import type { Snippet } from 'svelte';

    let {
        label,
        value,
        valueContent,
        detail,
        class: className = '',
        containerClass = '',
    }: {
        label: string;
        value: string | number;
        valueContent?: Snippet;
        detail?: Snippet;
        class?: string;
        containerClass?: string;
    } = $props();
</script>

<div class={containerClass}>
    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{label}</dt>
    <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100 {className}">
        {#if valueContent}{@render valueContent()}{:else}{value}{/if}
    </dd>
    {@render detail?.()}
</div>
