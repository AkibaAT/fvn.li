<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';
    import type { Snippet } from 'svelte';

    let {
        children,
        variant,
        unresolved = false,
        returnsToCaller = false,
    }: {
        children: Snippet;
        variant: 'hub' | 'choice' | 'condition' | 'label';
        unresolved?: boolean;
        returnsToCaller?: boolean;
    } = $props();
</script>

<div class="node {variant}" class:unresolved class:returns-to-caller={returnsToCaller}>
    <Handle type="target" position={Position.Top} />
    {@render children()}
    <Handle type="source" position={Position.Bottom} />
</div>

<style>
    .node {
        box-sizing: border-box;
        position: relative;
    }
    .hub {
        background: var(--xy-node-hub-bg, #e0e7ff);
        border: 2px solid var(--xy-node-hub-border, #6366f1);
        border-radius: 8px;
        line-height: 1.4;
        padding: 8px 14px;
        text-align: center;
        width: 140px;
    }
    .choice {
        line-height: 1.3;
        padding: 6px 12px;
        text-align: center;
        white-space: normal;
        width: 184px;
        word-wrap: break-word;
    }
    .condition {
        background: var(--rm-edge-label-bg);
        border: 1px solid var(--rm-edge-label-border);
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
        color: var(--rm-edge-label-text);
        font-size: 11px;
        line-height: 1.25;
        padding: 4px 8px;
        text-align: left;
        white-space: pre-line;
        width: 260px;
        word-break: break-word;
    }
    .condition :global(.svelte-flow__handle) {
        height: 1px;
        opacity: 0;
        width: 1px;
    }
    .label {
        background: var(--xy-node-background-color, var(--xy-node-background-color-default));
        border: var(--xy-node-border, var(--xy-node-border-default));
        border-radius: var(--xy-node-border-radius, var(--xy-node-border-radius-default));
        color: var(--xy-node-color, var(--xy-node-color-default));
        line-height: 1.4;
        padding: 10px;
        text-align: center;
        width: 220px;
    }
    .unresolved {
        background: #fef2f2;
        border-color: #ef4444;
        color: #b91c1c;
        font-weight: 600;
    }
    .returns-to-caller {
        background: #f0f9ff;
        border-color: #0ea5e9;
    }
    :global(.dark) .unresolved {
        background: rgba(127, 29, 29, 0.5);
        color: #fca5a5;
    }
    :global(.dark) .returns-to-caller {
        background: rgba(12, 74, 110, 0.45);
    }
</style>
