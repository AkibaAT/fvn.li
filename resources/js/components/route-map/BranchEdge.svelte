<script lang="ts">
    import { BaseEdge, getBezierPath } from '@xyflow/svelte';
    import type { EdgeProps } from '@xyflow/svelte';

    type ParallelLane = {
        index: number;
        total: number;
    };

    type BranchEdgeData = {
        edge_type?: string;
        parallel?: ParallelLane;
    };

    let {
        id,
        sourceX,
        sourceY,
        targetX,
        targetY,
        sourcePosition,
        targetPosition,
        label,
        labelStyle,
        markerStart,
        markerEnd,
        style,
        interactionWidth,
        data,
    }: EdgeProps = $props();

    let edgeData = $derived((data ?? {}) as BranchEdgeData);
    let parallel = $derived(edgeData.parallel);
    let isParallel = $derived((parallel?.total ?? 1) > 1);
    let laneDistance = $derived.by(() => {
        if (!parallel || parallel.total <= 1) return 0;

        return (parallel.index - (parallel.total - 1) / 2) * 34;
    });
    let fallbackPathResult = $derived.by(() => {
        return getBezierPath({
            sourceX,
            sourceY,
            targetX,
            targetY,
            sourcePosition,
            targetPosition,
        });
    });
    let labelOffset = $derived.by(() => {
        if (!isParallel || laneDistance === 0) return { x: 0, y: 0 };

        const deltaX = targetX - sourceX;
        const deltaY = targetY - sourceY;
        const length = Math.hypot(deltaX, deltaY) || 1;

        return {
            x: (-deltaY / length) * laneDistance,
            y: (deltaX / length) * laneDistance,
        };
    });
    let labelX = $derived(fallbackPathResult[1] + labelOffset.x);
    let labelY = $derived(fallbackPathResult[2] + labelOffset.y);
</script>

<BaseEdge {id} path={fallbackPathResult[0]} {labelX} {labelY} {label} {labelStyle} {markerStart} {markerEnd} {interactionWidth} {style} />
