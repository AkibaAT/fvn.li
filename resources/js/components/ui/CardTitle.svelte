<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        children?: Snippet;
        class?: string;
    }

    let { children, class: className = '' }: Props = $props();
</script>

<h3 class={`text-lg font-semibold text-gray-900 dark:text-white ${className}`}>
    {@render children?.()}
</h3>
