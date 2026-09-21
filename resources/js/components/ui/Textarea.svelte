<script lang="ts">
    import { cn } from '@/utils/cn';
    import type { HTMLTextareaAttributes } from 'svelte/elements';
    import Field from './Field.svelte';

    interface Props extends HTMLTextareaAttributes {
        label?: string;
        error?: string;
        help?: string;
        class?: string;
        fieldClass?: string;
    }

    let { label, error, help, class: className = '', fieldClass = '', id, required, value = $bindable(), ...restProps }: Props = $props();
    const fieldId = $derived(id ?? undefined);
    const isRequired = $derived(required ?? undefined);

    const textareaClass = $derived(
        cn('block w-full rounded-md border border-border-input bg-surface-alt px-3 py-2 text-sm text-fg transition-colors placeholder:text-fg-faint focus:border-fg-muted focus:outline-none disabled:cursor-not-allowed disabled:opacity-60',
                error && 'border-red-500 focus:border-red-500',
                className),
    );
</script>

<Field id={fieldId} {label} {error} {help} required={isRequired} class={fieldClass}>
    <textarea
        id={fieldId}
        required={isRequired}
        bind:value
        class={textareaClass}
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={fieldId && (help || error) ? `${fieldId}-message` : undefined}
        {...restProps}
    ></textarea>
</Field>
