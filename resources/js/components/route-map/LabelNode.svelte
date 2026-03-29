<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';

    let { data }: { data: any } = $props();
</script>

<Handle type="target" position={Position.Top} />
<div class="label-node">
    <div class="label-name">{data.label}</div>
    {#if data.menu_prompt}
        <div class="prompt">&ldquo;{data.menu_prompt}&rdquo;</div>
    {/if}
</div>
<Handle type="source" position={Position.Bottom} />

<style>
    .label-node {
        background: var(--xy-node-background-color, var(--xy-node-background-color-default));
        border: var(--xy-node-border, var(--xy-node-border-default));
        border-radius: var(--xy-node-border-radius, var(--xy-node-border-radius-default));
        color: var(--xy-node-color, var(--xy-node-color-default));
        padding: 10px;
        text-align: center;
        line-height: 1.4;
        width: 200px;
    }

    .label-name {
        font-size: 12px;
        font-weight: 500;
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
