<script lang="ts">
    import type { Snippet } from 'svelte';
    import { Alert, Button } from '@/components/ui';

    interface Props {
        loading?: boolean;
        loadingContent?: Snippet;
        error?: string | null;
        errorTitle?: string;
        onRetry?: () => void;
        children?: Snippet;
    }

    let {
        loading = false,
        loadingContent,
        error = null,
        errorTitle = 'Failed to load',
        onRetry = () => window.location.reload(),
        children,
    }: Props = $props();
</script>

{#if loading}
    {@render loadingContent?.()}
{:else if error}
    <Alert title={errorTitle} tone="danger">
        <p>{error}</p>
        {#snippet actions()}
            <Button type="button" tone="danger" size="sm" onclick={onRetry}>Retry</Button>
        {/snippet}
    </Alert>
{:else}
    {@render children?.()}
{/if}
