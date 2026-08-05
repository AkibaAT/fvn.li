<script lang="ts" module>
    export type AlertTone = 'warning' | 'danger' | 'info' | 'note' | 'success' | 'neutral';
</script>

<script lang="ts">
    import AlertCircleIcon from '@/components/icons/AlertCircle.svelte';
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { Snippet } from 'svelte';

    interface Props {
        title?: string;
        tone?: AlertTone;
        layout?: 'block' | 'inline';
        role?: 'alert' | 'status';
        icon?: Snippet;
        children: Snippet;
        actions?: Snippet;
        class?: string;
    }

    let {
        title,
        tone = 'warning',
        layout = 'block',
        role = 'alert',
        icon,
        children,
        actions,
        class: className = '',
    }: Props = $props();

    const toneClasses: Record<AlertTone, { box: string; icon: string; title: string; body: string }> = {
        warning: {
            box: 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20',
            icon: 'text-amber-600 dark:text-amber-400',
            title: 'text-amber-800 dark:text-amber-300',
            body: 'text-amber-700 dark:text-amber-300',
        },
        danger: {
            box: 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20',
            icon: 'text-red-600 dark:text-red-400',
            title: 'text-red-800 dark:text-red-300',
            body: 'text-red-700 dark:text-red-300',
        },
        info: {
            box: 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20',
            icon: 'text-blue-600 dark:text-blue-400',
            title: 'text-blue-800 dark:text-blue-300',
            body: 'text-blue-700 dark:text-blue-300',
        },
        note: {
            box: 'border-indigo-200 bg-indigo-50 dark:border-indigo-800 dark:bg-indigo-900/20',
            icon: 'text-indigo-600 dark:text-indigo-400',
            title: 'text-indigo-800 dark:text-indigo-300',
            body: 'text-indigo-700 dark:text-indigo-300',
        },
        success: {
            box: 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20',
            icon: 'text-green-600 dark:text-green-400',
            title: 'text-green-800 dark:text-green-300',
            body: 'text-green-700 dark:text-green-300',
        },
        neutral: {
            box: 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/70',
            icon: 'text-gray-500 dark:text-gray-400',
            title: 'text-gray-800 dark:text-gray-200',
            body: 'text-gray-700 dark:text-gray-300',
        },
    };

    let classes = $derived(
        twMerge(
            clsx(
                'border',
                layout === 'inline' ? 'rounded-lg p-3' : 'rounded-xl p-6 backdrop-blur-xl',
                toneClasses[tone].box,
                className,
            ),
        ),
    );
</script>

<div class={classes} {role}>
    <div class={layout === 'inline' ? 'flex items-center justify-between gap-4' : 'flex items-start gap-3'}>
        <div class="flex min-w-0 items-start gap-3">
            <div class="mt-0.5 shrink-0 {toneClasses[tone].icon}" aria-hidden="true">
                {#if icon}
                    {@render icon()}
                {:else}
                    <AlertCircleIcon class="h-5 w-5" />
                {/if}
            </div>
            <div class="min-w-0">
                {#if title}
                    {#if layout === 'block'}
                        <h3 class="font-semibold {toneClasses[tone].title}">{title}</h3>
                    {:else}
                        <div class="font-semibold {toneClasses[tone].title}">{title}</div>
                    {/if}
                {/if}
                <div class="text-sm {title ? 'mt-1' : ''} {toneClasses[tone].body}">{@render children()}</div>
                {#if layout === 'block' && actions}<div class="mt-4">{@render actions()}</div>{/if}
            </div>
        </div>
        {#if layout === 'inline' && actions}<div class="shrink-0">{@render actions()}</div>{/if}
    </div>
</div>
