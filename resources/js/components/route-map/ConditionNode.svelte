<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';

    let { data }: { data: any } = $props();
</script>

<div class:unresolved={data.targets_unresolved_node} class="condition-node">
    <Handle type="target" position={Position.Top} />
    <div class="condition-label">{data.label}</div>
    <Handle type="source" position={Position.Bottom} />
</div>

<style>
    .condition-node {
        background: var(--rm-edge-label-bg);
        border: 1px solid var(--rm-edge-label-border);
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
        box-sizing: border-box;
        color: var(--rm-edge-label-text);
        font-size: 11px;
        line-height: 1.25;
        padding: 4px 8px;
        position: relative;
        text-align: left;
        white-space: pre-line;
        width: 260px;
        word-break: break-word;
    }

    .condition-node :global(.svelte-flow__handle) {
        height: 1px;
        opacity: 0;
        width: 1px;
    }

    .unresolved {
        background: #fef2f2;
        border-color: #ef4444;
        color: #b91c1c;
        font-weight: 600;
    }

    :global(.dark) .unresolved {
        background: rgba(127, 29, 29, 0.55);
        color: #fca5a5;
    }
</style>
