<script lang="ts">
    import { Alert } from '@/components/ui';
    import CheckCircleIcon from '@/components/icons/CheckCircle.svelte';
    import XCircleIcon from '@/components/icons/XCircle.svelte';

    interface Props {
        message?: string;
        error?: string;
    }

    let { message, error }: Props = $props();
</script>

{#if message}
    <Alert tone="success" layout="inline" role="status" class="mx-4 mt-4">
        <span class="font-medium">{message}</span>
        {#snippet icon()}<CheckCircleIcon class="h-5 w-5" />{/snippet}
    </Alert>
{/if}

{#if error}
    <Alert tone="danger" layout="inline" class="mx-4 mt-4">
        <span class="font-medium">{error}</span>
        {#snippet icon()}<XCircleIcon class="h-5 w-5" />{/snippet}
    </Alert>
{/if}
