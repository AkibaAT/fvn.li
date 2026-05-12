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

<label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300" for={id}>
    <input
        {id}
        type="radio"
        {checked}
        class={twMerge(clsx('h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700', className))}
        {...restProps}
    />
    {#if label}
        <span>{label}</span>
    {/if}
</label>
{#if error}
    <p class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>
{/if}
