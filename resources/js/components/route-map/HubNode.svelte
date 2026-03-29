<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';

    let { data }: { data: any } = $props();
</script>

<Handle type="target" position={Position.Top} />
<div class="hub-node">
    <div class="hub-label">{data.label}</div>
    <div class="hub-count">{data.hub_choice_count} routes</div>
</div>
<Handle type="source" position={Position.Bottom} />

<style>
    .hub-node {
        background: var(--xy-node-hub-bg, #e0e7ff);
        border: 2px solid var(--xy-node-hub-border, #6366f1);
        border-radius: 8px;
        padding: 8px 14px;
        text-align: center;
        line-height: 1.4;
    }

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
