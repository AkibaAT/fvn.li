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
            box: 'border-amber-600/40 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/30',
            icon: 'text-amber-600 dark:text-amber-400',
            title: 'text-amber-900 dark:text-amber-200',
            body: 'text-amber-800 dark:text-amber-300',
        },
        danger: {
            box: 'border-red-600/40 bg-red-50 dark:border-red-800/60 dark:bg-red-950/30',
            icon: 'text-red-600 dark:text-red-400',
            title: 'text-red-900 dark:text-red-200',
            body: 'text-red-800 dark:text-red-300',
        },
        success: {
            box: 'border-green-600/40 bg-green-50 dark:border-green-800/60 dark:bg-green-950/30',
            icon: 'text-green-600 dark:text-green-400',
            title: 'text-green-900 dark:text-green-200',
            body: 'text-green-800 dark:text-green-300',
        },
        info: {
            box: 'border-border bg-surface-alt',
            icon: 'text-fg-muted',
            title: 'text-fg',
            body: 'text-fg-muted',
        },
        note: {
            box: 'border-border bg-surface-alt',
            icon: 'text-fg-muted',
            title: 'text-fg',
            body: 'text-fg-muted',
        },
        neutral: {
            box: 'border-border bg-surface-alt',
            icon: 'text-fg-muted',
            title: 'text-fg',
            body: 'text-fg-muted',
        },
    };

    let classes = $derived(twMerge(clsx('rounded-lg border', layout === 'inline' ? 'p-3' : 'p-6', toneClasses[tone].box, className)));
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
                <div class="text-[13px] {title ? 'mt-1' : ''} {toneClasses[tone].body}">{@render children()}</div>
                {#if layout === 'block' && actions}<div class="mt-4">{@render actions()}</div>{/if}
            </div>
        </div>
        {#if layout === 'inline' && actions}<div class="shrink-0">{@render actions()}</div>{/if}
    </div>
</div>
