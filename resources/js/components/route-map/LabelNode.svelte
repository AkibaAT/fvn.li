<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';

    let { data }: { data: any } = $props();
</script>

<div class:returns-to-caller={data.returns_to_caller} class:unresolved={data.is_unresolved} class="label-node">
    <Handle type="target" position={Position.Top} />
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
    <Handle type="source" position={Position.Bottom} />
</div>

<style>
    .label-node {
        background: var(--xy-node-background-color, var(--xy-node-background-color-default));
        border: var(--xy-node-border, var(--xy-node-border-default));
        border-radius: var(--xy-node-border-radius, var(--xy-node-border-radius-default));
        color: var(--xy-node-color, var(--xy-node-color-default));
        padding: 10px;
        text-align: center;
        line-height: 1.4;
        width: 220px;
        box-sizing: border-box;
        position: relative;
    }

    .label-name {
        font-size: 12px;
        font-weight: 500;
    }

    .unresolved {
        border-color: #ef4444;
        background: #fef2f2;
    }

    .returns-to-caller {
        border-color: #0ea5e9;
        background: #f0f9ff;
    }

    :global(.dark) .unresolved {
        background: rgba(127, 29, 29, 0.45);
    }

    :global(.dark) .returns-to-caller {
        background: rgba(12, 74, 110, 0.45);
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
