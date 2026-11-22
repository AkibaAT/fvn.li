<script lang="ts" module>
    import {
        BarController,
        BarElement,
        CategoryScale,
        Chart as ChartJS,
        Filler,
        Legend,
        LineController,
        LinearScale,
        LineElement,
        PointElement,
        Title,
        Tooltip,
    } from 'chart.js';

    ChartJS.register(
        BarController,
        BarElement,
        CategoryScale,
        Filler,
        Legend,
        LineController,
        LinearScale,
        LineElement,
        PointElement,
        Title,
        Tooltip,
    );
</script>

<script lang="ts">
    import { onMount, onDestroy, untrack } from 'svelte';
    import type { ChartData, ChartOptions, Plugin } from 'chart.js';

    let {
        type = 'line',
        data,
        options,
        style,
        class: className,
        plugins,
    }: {
        type?: 'line' | 'bar';
        data: ChartData<'line'> | ChartData<'bar'>;
        options?: ChartOptions<'line'> | ChartOptions<'bar'>;
        style?: string;
        class?: string;
        plugins?: Plugin<'line'>[] | Plugin<'bar'>[];
    } = $props();

    /** Deep-copy reactive proxy to plain object, preserving functions */
    function toPlain<T>(obj: T): T {
        if (obj == null || typeof obj !== 'object') return obj;
        if (typeof obj === 'function') return obj;
        if (Array.isArray(obj)) return obj.map(toPlain) as T;
        const plain: Record<string, unknown> = {};
        for (const key of Object.keys(obj as Record<string, unknown>)) {
            const val = (obj as Record<string, unknown>)[key];
            plain[key] = typeof val === 'function' ? val : toPlain(val);
        }
        return plain as T;
    }

    let canvasRef: HTMLCanvasElement;
    let chart: ChartJS | null = null;
    let mounted = false;

    onMount(() => {
        chart = new ChartJS(canvasRef, {
            type,
            data: toPlain(data) as any,
            options: toPlain(options) as any,
            plugins: plugins as any,
        });

        mounted = true;
    });

    $effect(() => {
        // Track reactive deps
        const newData = toPlain(data);
        const newOptions = options ? toPlain(options) : undefined;

        // Read chart without tracking to avoid circular dependency
        const c = untrack(() => chart);
        if (!c || !mounted) return;

        c.data = newData;
        if (c.options && newOptions) {
            Object.assign(c.options, newOptions);
        }
        c.update();
    });

    onDestroy(() => {
        if (chart) chart.destroy();
        chart = null;
    });
</script>

<div {style} class={className}>
    <canvas bind:this={canvasRef}></canvas>
</div>
