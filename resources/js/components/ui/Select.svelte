<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { HTMLSelectAttributes } from 'svelte/elements';
    import type { Snippet } from 'svelte';
    import Field from './Field.svelte';

    interface Props extends HTMLSelectAttributes {
        label?: string;
        error?: string;
        help?: string;
        children?: Snippet;
        class?: string;
        fieldClass?: string;
    }

    let { label, error, help, children, class: className = '', fieldClass = '', id, required, value = $bindable(), ...restProps }: Props = $props();
    const fieldId = $derived(id ?? undefined);
    const isRequired = $derived(required ?? undefined);

    const selectClass = $derived(
        twMerge(
            clsx(
                'block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-700 dark:text-white',
                error && 'border-red-300 focus:border-red-500 focus:ring-red-500 dark:border-red-700',
                className,
            ),
        ),
    );
</script>

<Field id={fieldId} {label} {error} {help} required={isRequired} class={fieldClass}>
    <select id={fieldId} required={isRequired} bind:value class={selectClass} aria-invalid={error ? 'true' : undefined} {...restProps}>
        {@render children?.()}
    </select>
</Field>
