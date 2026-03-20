<script lang="ts" module>
    export interface CardProps {
        children?: import('svelte').Snippet;
        class?: string;
        hover?: boolean;
        padding?: 'none' | 'sm' | 'md' | 'lg';
    }
</script>

<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        children?: Snippet;
        class?: string;
        hover?: boolean;
        padding?: 'none' | 'sm' | 'md' | 'lg';
    }

    let { children, class: className = '', hover = false, padding = 'md' }: Props = $props();

    const baseClasses = 'rounded-lg border bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700';

    let hoverClasses = $derived(hover ? 'transition-all duration-200 hover:shadow-md hover:scale-[1.02]' : '');

    const paddingClasses: Record<string, string> = {
        none: '',
        sm: 'p-3',
        md: 'p-4',
        lg: 'p-6',
    };
</script>

<div class={`${baseClasses} ${hoverClasses} ${paddingClasses[padding]} ${className}`}>
    {@render children?.()}
</div>
