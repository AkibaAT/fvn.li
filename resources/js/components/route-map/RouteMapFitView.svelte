<script lang="ts">
    import { useSvelteFlow } from '@xyflow/svelte';

    let { layoutVersion }: { layoutVersion: number } = $props();
    const { fitView } = useSvelteFlow();
    let lastLayoutVersion = -1;

    $effect(() => {
        if (layoutVersion === lastLayoutVersion) return;
        lastLayoutVersion = layoutVersion;

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                void fitView({ padding: 0.12, minZoom: 0.01, maxZoom: 1 });
            });
        });
    });
</script>
