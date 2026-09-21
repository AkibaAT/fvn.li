<script lang="ts">
    import { cn } from '@/utils/cn';
    import type { Snippet } from 'svelte';

    interface Props {
        children: Snippet;
        class?: string;
    }

    let { children, class: className = '' }: Props = $props();
</script>

<div class={cn('rounded-lg border border-border bg-surface', className)}>
    {@render children()}
</div>
