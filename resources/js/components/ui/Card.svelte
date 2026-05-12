<script lang="ts" module>
    export type CardVariant = 'panel' | 'glass' | 'flat' | 'soft' | 'outline';
    export type CardTone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info';
    export type CardPadding = 'none' | 'xs' | 'sm' | 'md' | 'lg';

    export interface CardProps {
        children?: import('svelte').Snippet;
        class?: string;
        hover?: boolean;
        padding?: CardPadding;
        variant?: CardVariant;
        tone?: CardTone;
        id?: string;
    }
</script>

<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        children?: Snippet;
        class?: string;
        hover?: boolean;
        padding?: CardPadding;
        variant?: CardVariant;
        tone?: CardTone;
    }

    let { children, class: className = '', hover = false, padding = 'md', variant = 'panel', tone = 'neutral', ...restProps }: Props = $props();

    const variantClasses: Record<CardVariant, string> = {
        panel: 'rounded-lg bg-white shadow-sm dark:bg-gray-800',
        glass: 'rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70',
        flat: 'rounded-lg bg-white dark:bg-gray-800',
        soft: 'rounded-lg border bg-gray-50 dark:bg-gray-900/40',
        outline: 'rounded-lg border bg-transparent',
    };

    const toneClasses: Record<CardTone, string> = {
        neutral: 'border-gray-200 dark:border-gray-700',
        primary: 'border-blue-200 dark:border-blue-800',
        success: 'border-green-200 dark:border-green-800',
        warning: 'border-amber-200 dark:border-amber-800',
        danger: 'border-red-200 dark:border-red-800',
        info: 'border-indigo-200 dark:border-indigo-800',
    };

    const paddingClasses: Record<CardPadding, string> = {
        none: '',
        xs: 'p-2',
        sm: 'p-3',
        md: 'p-4 md:p-6',
        lg: 'p-6',
    };

    let hoverClasses = $derived(hover ? 'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md' : '');
    let classes = $derived(twMerge(clsx(variantClasses[variant], toneClasses[tone], paddingClasses[padding], hoverClasses, className)));
</script>

<div class={classes} {...restProps}>
    {@render children?.()}
</div>
