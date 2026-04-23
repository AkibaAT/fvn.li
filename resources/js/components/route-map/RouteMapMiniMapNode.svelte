<script lang="ts">
    import type { ClassValue } from 'svelte/elements';

    type Props = {
        id: string;
        x?: number;
        y?: number;
        width?: number;
        height?: number;
        color?: string;
        strokeColor?: string;
        strokeWidth?: number;
        shapeRendering?: string;
        selected?: boolean;
        class?: ClassValue;
    };

    let {
        x = 0,
        y = 0,
        width = 0,
        height = 0,
        color = '#475569',
        strokeColor = '#0f172a',
        strokeWidth = 2,
        shapeRendering,
        selected = false,
        class: className = '',
    }: Props = $props();

    let centerX = $derived(x + width / 2);
    let centerY = $derived(y + height / 2);
    let radius = $derived(Math.max(180, Math.min(420, Math.max(width, height) * 0.9)));
</script>

<circle
    class={className}
    class:selected
    cx={centerX}
    cy={centerY}
    r={radius}
    fill={color}
    stroke={strokeColor}
    stroke-width={Math.max(strokeWidth, 2)}
    shape-rendering={shapeRendering}
    vector-effect="non-scaling-stroke"
/>
