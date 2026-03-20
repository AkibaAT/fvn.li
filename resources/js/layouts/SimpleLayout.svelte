<script lang="ts">
    import type { Snippet } from 'svelte';
    import Container from '@/components/Container.svelte';

    interface Props {
        children: Snippet;
        title?: string;
    }

    let { children, title }: Props = $props();
</script>

<svelte:head>
    {#if title}
        <title>{title}</title>
    {/if}
</svelte:head>

<Container>
    {@render children()}
</Container>
