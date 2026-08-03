<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    let { isAuthenticated, class: className = '' }: { isAuthenticated: boolean; class?: string } = $props();

    const href = $derived(route(isAuthenticated ? 'dashboard' : 'login'));
    const label = $derived(isAuthenticated ? 'Dashboard' : 'Log In');
</script>

<Link {href} class={className}>{label}</Link>
