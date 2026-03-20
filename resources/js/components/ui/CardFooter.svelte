<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        children?: Snippet;
        class?: string;
    }

    let { children, class: className = '' }: Props = $props();
</script>

<div class={`mt-4 flex items-center ${className}`}>
    {@render children?.()}
</div>
