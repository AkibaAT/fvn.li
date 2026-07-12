<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        id?: string;
        label?: string;
        error?: string;
        help?: string;
        required?: boolean;
        children?: Snippet;
        class?: string;
    }

    let { id, label, error, help, required = false, children, class: className = '' }: Props = $props();
</script>

<div class={className}>
    {#if label}
        <label for={id} class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {label}
            {#if required}
                <span class="text-red-600 dark:text-red-400">*</span>
            {/if}
        </label>
    {/if}

    {@render children?.()}

    {#if error}
        <p id={id ? `${id}-message` : undefined} class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>
    {:else if help}
        <p id={id ? `${id}-message` : undefined} class="mt-1 text-sm text-gray-500 dark:text-gray-400">{help}</p>
    {/if}
</div>
