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

    const baseClasses = 'inline-flex items-center justify-center rounded-full font-medium';
    const normalizedVariant = $derived(variant === 'default' || variant === 'secondary' ? 'soft' : variant);
    const normalizedTone = $derived(tone ?? (variant === 'secondary' ? 'neutral' : variant === 'default' ? 'neutral' : 'primary'));

    const toneClasses: Record<BadgeTone, Record<BadgeVariant, string>> = {
        neutral: {
            soft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            solid: 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-950',
            outline: 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-200',
        },
        primary: {
            soft: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            solid: 'bg-blue-600 text-white',
            outline: 'border border-blue-300 text-blue-700 dark:border-blue-700 dark:text-blue-300',
        },
        success: {
            soft: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            solid: 'bg-green-600 text-white',
            outline: 'border border-green-300 text-green-700 dark:border-green-700 dark:text-green-300',
        },
        warning: {
            soft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
            solid: 'bg-amber-600 text-white',
            outline: 'border border-amber-300 text-amber-800 dark:border-amber-700 dark:text-amber-300',
        },
        danger: {
            soft: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            solid: 'bg-red-600 text-white',
            outline: 'border border-red-300 text-red-700 dark:border-red-700 dark:text-red-300',
        },
        info: {
            soft: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
            solid: 'bg-indigo-600 text-white',
            outline: 'border border-indigo-300 text-indigo-700 dark:border-indigo-700 dark:text-indigo-300',
        },
        orange: {
            soft: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
            solid: 'bg-orange-600 text-white',
            outline: 'border border-orange-300 text-orange-700 dark:border-orange-700 dark:text-orange-300',
        },
        purple: {
            soft: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            solid: 'bg-purple-600 text-white',
            outline: 'border border-purple-300 text-purple-700 dark:border-purple-700 dark:text-purple-300',
        },
    };

    const sizeClasses: Record<string, string> = {
        sm: 'px-2 py-1 text-xs',
        md: 'px-2.5 py-1.5 text-xs',
        lg: 'px-3 py-2 text-sm',
    };

    let classes = $derived(twMerge(clsx(baseClasses, toneClasses[normalizedTone][normalizedVariant], sizeClasses[size], className)));
</script>

<span class={classes}>
    {@render children?.()}
</span>
