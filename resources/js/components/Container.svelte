<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/utils/cn';

    let { children, class: className }: { children: Snippet; class?: string } = $props();
</script>

<div class={cn('mx-auto w-full max-w-page px-4 sm:px-5 lg:px-7', className)}>
    {@render children()}
</div>
