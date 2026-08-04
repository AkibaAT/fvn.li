<script lang="ts">
    import RouteMapNodeShell from './RouteMapNodeShell.svelte';

    let { data }: { data: any } = $props();
</script>

<RouteMapNodeShell variant="condition" unresolved={data.targets_unresolved_node}>
    <div class="condition-label">{data.label}</div>
</RouteMapNodeShell>

<style>
</style>
