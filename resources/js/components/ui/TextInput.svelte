<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { HTMLInputAttributes } from 'svelte/elements';
    import Field from './Field.svelte';

    interface Props extends HTMLInputAttributes {
        label?: string;
        error?: string;
        help?: string;
        class?: string;
        fieldClass?: string;
    }

    let { label, error, help, class: className = '', fieldClass = '', id, required, value = $bindable(), ...restProps }: Props = $props();
    const fieldId = $derived(id ?? undefined);
    const isRequired = $derived(required ?? undefined);

    const inputClass = $derived(
        twMerge(
            clsx(
                'block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-700 dark:text-white',
                error && 'border-red-300 focus:border-red-500 focus:ring-red-500 dark:border-red-700',
                className,
            ),
        ),
    );
</script>

<Field id={fieldId} {label} {error} {help} required={isRequired} class={fieldClass}>
    <input
        id={fieldId}
        required={isRequired}
        bind:value
        class={inputClass}
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={help || error ? `${fieldId}-message` : undefined}
        {...restProps}
    />
</Field>
