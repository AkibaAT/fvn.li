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
        border: 1px solid var(--xy-node-hub-border, #6366f1);
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
        border-radius: 8px;
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
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        line-height: 1.4;
        padding: 10px;
        text-align: center;
        width: 220px;
    }
    .unresolved {
        background: var(--surface);
        border-color: #dc2626;
        color: #b91c1c;
        font-weight: 600;
    }
    .returns-to-caller {
        background: var(--surface);
        border-color: #0284c7;
    }
    :global(.dark) .unresolved {
        border-color: #f87171;
        color: #fca5a5;
    }
    :global(.dark) .returns-to-caller {
        border-color: #38bdf8;
    }
</style>
