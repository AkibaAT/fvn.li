<script lang="ts" module>
    export type BadgeTone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'orange' | 'purple';
    export type BadgeVariant = 'soft' | 'solid' | 'outline';
</script>

<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { Snippet } from 'svelte';

    interface Props {
        children?: Snippet;
        variant?: BadgeVariant | 'default' | 'secondary';
        tone?: BadgeTone;
        size?: 'sm' | 'md' | 'lg';
        class?: string;
    }

    let { children, variant = 'soft', tone, size = 'md', class: className = '' }: Props = $props();

    const baseClasses = 'inline-flex items-center justify-center gap-1 rounded-[3px] border font-semibold tracking-[0.02em] whitespace-nowrap';
    const normalizedVariant = $derived(variant === 'default' || variant === 'secondary' ? 'soft' : variant);
    const normalizedTone = $derived(tone ?? (variant === 'secondary' ? 'neutral' : variant === 'default' ? 'neutral' : 'primary'));

    const toneClasses: Record<BadgeTone, Record<BadgeVariant, string>> = {
        neutral: {
            soft: 'border-border-strong text-fg-muted',
            solid: 'border-transparent bg-fg text-surface',
            outline: 'border-border-strong text-fg-muted',
        },
        primary: {
            soft: 'border-border-strong text-fg-muted',
            solid: 'border-transparent bg-accent text-on-accent',
            outline: 'border-border-strong text-fg-muted',
        },
        success: {
            soft: 'border-green-600/50 text-green-800 dark:border-green-500/40 dark:text-green-300',
            solid: 'border-transparent bg-green-700 text-white',
            outline: 'border-green-600/60 text-green-800 dark:text-green-300',
        },
        warning: {
            soft: 'border-amber-600/50 text-amber-800 dark:border-amber-500/40 dark:text-amber-300',
            solid: 'border-transparent bg-amber-700 text-white',
            outline: 'border-amber-600/60 text-amber-800 dark:text-amber-300',
        },
        danger: {
            soft: 'border-red-600/50 text-red-700 dark:border-red-500/40 dark:text-red-300',
            solid: 'border-transparent bg-red-700 text-white',
            outline: 'border-red-600/60 text-red-700 dark:text-red-300',
        },
        info: {
            soft: 'border-border-strong text-fg-muted',
            solid: 'border-transparent bg-fg text-surface',
            outline: 'border-border-strong text-fg-muted',
        },
        orange: {
            soft: 'border-orange-600/50 text-orange-800 dark:border-orange-500/40 dark:text-orange-300',
            solid: 'border-transparent bg-orange-700 text-white',
            outline: 'border-orange-600/60 text-orange-800 dark:text-orange-300',
        },
        purple: {
            soft: 'border-purple-600/50 text-purple-800 dark:border-purple-500/40 dark:text-purple-300',
            solid: 'border-transparent bg-purple-700 text-white',
            outline: 'border-purple-600/60 text-purple-800 dark:text-purple-300',
        },
    };

    const sizeClasses: Record<string, string> = {
        sm: 'px-1.5 py-0.5 text-[10px]',
        md: 'px-1.5 py-0.5 text-[11px]',
        lg: 'px-2 py-1 text-[12px]',
    };

    let classes = $derived(twMerge(clsx(baseClasses, toneClasses[normalizedTone][normalizedVariant], sizeClasses[size], className)));
</script>

<span class={classes}>
    {@render children?.()}
</span>
