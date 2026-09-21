<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        title: string;
        description?: string;
        /** Optional call-to-action row rendered under the description. */
        children?: Snippet;
        class?: string;
    }

    let { title, description, children, class: className = '' }: Props = $props();
</script>

<div class="flex flex-col items-center justify-center py-12 text-center {className}">
    <h3 class="text-md font-semibold text-fg">{title}</h3>
    {#if description}
        <p class="mt-2 max-w-md text-sm text-fg-muted">{description}</p>
    {/if}
    {#if children}
        <div class="mt-4">
            {@render children()}
        </div>
    {/if}
</div>
