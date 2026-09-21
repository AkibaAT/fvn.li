<script lang="ts">
    import { cn } from '@/utils/cn';
    import type { HTMLInputAttributes } from 'svelte/elements';

    interface Props extends HTMLInputAttributes {
        label?: string;
        error?: string;
        class?: string;
    }

    let { label, error, class: className = '', id, checked = $bindable(false), ...restProps }: Props = $props();
</script>

<label class="inline-flex cursor-pointer items-center gap-2 text-ui text-fg-muted" for={id}>
    <input
        {id}
        type="checkbox"
        bind:checked
        class={cn('h-4 w-4 shrink-0 cursor-pointer rounded-sm border-border-input bg-surface-alt accent-accent', className)}
        aria-invalid={error ? 'true' : undefined}
        {...restProps}
    />
    {#if label}
        <span>{label}</span>
    {/if}
</label>
{#if error}
    <p class="mt-1 text-xs text-red-600 dark:text-red-400" role="alert">{error}</p>
{/if}
