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
                'block w-full rounded-md border border-border bg-surface-alt px-3 py-2 text-sm text-fg transition-colors placeholder:text-fg-faint focus:border-border-strong focus:outline-none disabled:cursor-not-allowed disabled:opacity-60',
                error && 'border-red-500 focus:border-red-500',
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
