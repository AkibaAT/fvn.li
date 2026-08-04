<script lang="ts">
    import RouteMapNodeShell from './RouteMapNodeShell.svelte';

    let { data }: { data: any } = $props();
</script>

<RouteMapNodeShell variant="choice">
    <div class="choice-text">{data.choice_text}</div>
    {#if data.var_summary}
        <div class="var-changes">{data.var_summary}</div>
    {/if}
</RouteMapNodeShell>

<style>
    .choice-text {
        font-size: 12px;
        font-weight: 600;
        color: #451a03;
    }

    :global(.dark) .choice-text {
        color: #fef3c7;
    }

    .var-changes {
        font-size: 10px;
        font-family: ui-monospace, monospace;
        color: #78716c;
        margin-top: 2px;
    }

    :global(.dark) .var-changes {
        color: #a8a29e;
    }
</style>
