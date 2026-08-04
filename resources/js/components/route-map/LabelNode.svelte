<script lang="ts">
    import RouteMapNodeShell from './RouteMapNodeShell.svelte';

    let { data }: { data: any } = $props();
</script>

<RouteMapNodeShell variant="label" returnsToCaller={data.returns_to_caller} unresolved={data.is_unresolved}>
    <div class="label-name">{data.label}</div>
    {#if data.returns_to_caller}
        <div class="return-label">returns</div>
    {/if}
    {#if data.is_unresolved}
        <div class="unresolved-label">missing target</div>
    {/if}
    {#if data.menu_prompt}
        <div class="prompt">&ldquo;{data.menu_prompt}&rdquo;</div>
    {/if}
</RouteMapNodeShell>

<style>
    .label-name {
        font-size: 12px;
        font-weight: 500;
        overflow-wrap: anywhere;
    }

    .return-label {
        color: #0369a1;
        font-size: 10px;
        font-weight: 600;
        margin-top: 2px;
    }

    :global(.dark) .return-label {
        color: #7dd3fc;
    }

    .unresolved-label {
        color: #b91c1c;
        font-size: 10px;
        font-weight: 600;
        margin-top: 2px;
    }

    :global(.dark) .unresolved-label {
        color: #fca5a5;
    }

    .prompt {
        font-size: 10px;
        font-style: italic;
        color: #6b7280;
        margin-top: 4px;
        white-space: normal;
        word-wrap: break-word;
    }

    :global(.dark) .prompt {
        color: #9ca3af;
    }
</style>
