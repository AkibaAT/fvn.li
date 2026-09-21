<script lang="ts">
    import type { MetaTags } from '@/types/meta-tags';

    let { metaTags, title }: { metaTags?: MetaTags | null; title?: string } = $props();

    const isBrowser = typeof window !== 'undefined';
    const finalBrowserTitle = $derived(metaTags?.browserTitle ?? metaTags?.title ?? title);
</script>

<svelte:head>
    {#if isBrowser && finalBrowserTitle}<title>{finalBrowserTitle} - FVN.li</title>{/if}
    {#if isBrowser && metaTags?.noindex}<meta name="robots" content="noindex" />{/if}
</svelte:head>
