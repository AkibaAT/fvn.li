<script lang="ts">
    import type { Snippet } from 'svelte';
    import { Alert, Button } from '@/components/ui';

    interface Props {
        /** URL to return to after the itch.io OAuth flow completes. */
        intended: string;
        /** Optional icon snippet; defaults to the Alert's stock alert-circle icon. */
        icon?: Snippet;
    }

    let { intended, icon }: Props = $props();
</script>

<Alert title="Connect your itch.io account to manage your games" layout="inline" role="status" {icon}>
    After connecting, we'll show your owned games here for quick editing and analytics.
    {#snippet actions()}
        <Button href={route('auth.redirect', { provider: 'itchio', intended })} inertia={false} size="sm">Connect itch.io</Button>
    {/snippet}
</Alert>
