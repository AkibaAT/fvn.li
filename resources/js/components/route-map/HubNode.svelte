<script lang="ts">
    import RouteMapNodeShell from './RouteMapNodeShell.svelte';

    let { data }: { data: any } = $props();
</script>

<RouteMapNodeShell variant="hub">
    <div class="hub-label">{data.label}</div>
    <div class="hub-count">{data.hub_choice_count} routes</div>
</RouteMapNodeShell>

<style>
    .hub-label {
        font-size: 12px;
        font-weight: 600;
        color: #312e81;
    }

    :global(.dark) .hub-label {
        color: #e0e7ff;
    }

    .hub-count {
        font-size: 10px;
        color: #6366f1;
        margin-top: 2px;
    }

    :global(.dark) .hub-count {
        color: #a5b4fc;
    }
</style>
