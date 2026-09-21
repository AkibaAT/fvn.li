<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { HTMLInputAttributes } from 'svelte/elements';

    interface Props extends HTMLInputAttributes {
        label?: string;
        error?: string;
        class?: string;
    }

    let { label, error, class: className = '', id, checked = false, ...restProps }: Props = $props();
</script>

<label class="inline-flex cursor-pointer items-center gap-2 text-[13px] text-fg-muted" for={id}>
    <input
        {id}
        type="radio"
        {checked}
        class={twMerge(clsx('h-4 w-4 shrink-0 cursor-pointer border-border bg-surface-alt accent-accent', className))}
        {...restProps}
    />
    {#if label}
        <span>{label}</span>
    {/if}
</label>
{#if error}
    <p class="mt-1 text-[12px] text-red-600 dark:text-red-400" role="alert">{error}</p>
{/if}
