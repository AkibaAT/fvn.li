<script lang="ts">
    import type { Snippet } from 'svelte';
    import clsx from 'clsx';

    let { children, class: className }: { children: Snippet; class?: string } = $props();
</script>

<div class={clsx('mx-auto w-full max-w-[1240px] px-4 sm:px-5 lg:px-7', className)}>
    {@render children()}
</div>
