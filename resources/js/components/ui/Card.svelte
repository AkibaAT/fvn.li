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
        panel: 'rounded-lg border border-border bg-surface',
        glass: 'rounded-lg border border-border bg-surface',
        flat: 'rounded-lg border border-border bg-surface',
        soft: 'rounded-lg border border-border bg-surface-alt',
        outline: 'rounded-lg border border-border bg-transparent',
    };

    const toneClasses: Record<CardTone, string> = {
        neutral: 'border-border',
        primary: 'border-accent',
        success: 'border-green-600/50',
        warning: 'border-amber-600/50',
        danger: 'border-red-600/50',
        info: 'border-border-strong',
    };

    const paddingClasses: Record<CardPadding, string> = {
        none: '',
        xs: 'p-2',
        sm: 'p-3',
        md: 'p-4 md:p-6',
        lg: 'p-6',
    };

    let hoverClasses = $derived(hover ? 'transition-colors hover:border-border-strong' : '');
    let classes = $derived(twMerge(clsx(variantClasses[variant], toneClasses[tone], paddingClasses[padding], hoverClasses, className)));
</script>

<div class={classes} {...restProps}>
    {@render children?.()}
</div>
