<script lang="ts">
    import { useSvelteFlow } from '@xyflow/svelte';

    const TOP_PADDING = 24;
    const BOTTOM_PADDING = 24;
    const FIT_VIEW_OPTIONS = { padding: 0.12, minZoom: 0.01, maxZoom: 1 };

    let { layoutVersion }: { layoutVersion: number } = $props();
    const { fitView, getNodes, getNodesBounds, getViewport, setViewport } = useSvelteFlow();
    let lastLayoutVersion = -1;

    async function fitAndTopAlign(): Promise<void> {
        if (!(await fitView(FIT_VIEW_OPTIONS))) return;

        const nodes = getNodes();
        if (nodes.length === 0) return;

        const bounds = getNodesBounds(nodes);
        const fittedViewport = getViewport();
        const paneWidth = 2 * (fittedViewport.x + (bounds.x + bounds.width / 2) * fittedViewport.zoom);
        const paneHeight = 2 * (fittedViewport.y + (bounds.y + bounds.height / 2) * fittedViewport.zoom);
        const zoom = Math.min(
            FIT_VIEW_OPTIONS.maxZoom,
            Math.max(FIT_VIEW_OPTIONS.minZoom, (paneHeight - TOP_PADDING - BOTTOM_PADDING) / bounds.height),
        );
        const startNode = nodes.find((node) => node.data?.is_start) ?? nodes[0];
        const startBounds = getNodesBounds([startNode]);

        await setViewport({
            x: paneWidth / 2 - (startBounds.x + startBounds.width / 2) * zoom,
            y: TOP_PADDING - bounds.y * zoom,
            zoom,
        });
    }

    $effect(() => {
        if (layoutVersion === lastLayoutVersion) return;
        lastLayoutVersion = layoutVersion;

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                void fitAndTopAlign();
            });
        });
    });
</script>
