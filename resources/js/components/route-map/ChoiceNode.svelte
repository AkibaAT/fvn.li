<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';

    let { data }: { data: any } = $props();
</script>

<Handle type="target" position={Position.Top} />
<div class="choice-node">
    <div class="choice-text">{data.choice_text}</div>
    {#if data.var_summary}
        <div class="var-changes">{data.var_summary}</div>
    {/if}
</div>
<Handle type="source" position={Position.Bottom} />

<style>
    .choice-node {
        padding: 6px 12px;
        text-align: center;
        line-height: 1.3;
        width: 160px;
        white-space: normal;
        word-wrap: break-word;
    }

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
