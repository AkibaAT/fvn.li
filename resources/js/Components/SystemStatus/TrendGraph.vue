// Components/SystemStatus/TrendGraph.vue
<script setup lang="ts">
import { formatNumber } from '@/utils/formatters';
import { computed } from 'vue';

interface DataPoint {
    month: string;
    count: number;
}

interface Props {
    data: DataPoint[];
    lineColor?: string;
    textColor?: string;
    gridColor?: string;
}

const props = withDefaults(defineProps<Props>(), {
    lineColor: '#EAB308', // yellow-500
    textColor: '#6B7280', // gray-500
    gridColor: '#E5E7EB', // gray-200
});

// SVG dimensions and padding
const width = 1000;
const height = 300;
const padding = {
    top: 20,
    right: 10,
    bottom: 60,
    left: 45,
};

// Calculate actual graph dimensions
const graphWidth = width - (padding.left + padding.right);
const graphHeight = height - (padding.top + padding.bottom);

// Get min/max values
const maxCount = computed(() => Math.max(...props.data.map((d) => d.count)));

// Calculate Y-axis scale (round up to nearest 100)
const yMax = computed(() => Math.ceil(maxCount.value / 100) * 100);
const yScale = computed(() => graphHeight / yMax.value);

// Calculate X-axis scale
const xScale = computed(() => graphWidth / (props.data.length - 1));

// Generate points for the line
const points = computed(() => {
    return props.data
        .map((point, index) => {
            const x = index * xScale.value + padding.left;
            const y = height - padding.bottom - point.count * yScale.value;
            return `${x},${y}`;
        })
        .join(' ');
});

// Generate Y-axis labels (5 evenly spaced points)
const yLabels = computed(() => {
    return Array.from({ length: 5 }, (_, i) => {
        const value = Math.round(yMax.value * (i / 4));
        const y = height - padding.bottom - value * yScale.value;
        return { value, y };
    });
});

// Generate X-axis labels (show every 3rd month)
const xLabels = computed(() => {
    return props.data.map((point, index) => {
        const date = new Date(point.month);
        return {
            label: date.toLocaleDateString(undefined, {
                month: 'short',
                year: 'numeric',
            }),
            x: index * xScale.value + padding.left,
            show: index % 3 === 0,
        };
    });
});

const viewBox = `0 0 ${width} ${height}`;
</script>

<template>
    <div class="w-full">
        <svg
            :viewBox="viewBox"
            class="h-auto w-full"
            preserveAspectRatio="xMidYMid meet"
        >
            <!-- Background grid lines -->
            <template v-for="label in yLabels" :key="label.y">
                <line
                    :x1="padding.left"
                    :y1="label.y"
                    :x2="width - padding.right"
                    :y2="label.y"
                    :stroke="gridColor"
                    stroke-width="1"
                    stroke-dasharray="4,4"
                />
                <text
                    :x="padding.left - 10"
                    :y="label.y"
                    text-anchor="end"
                    alignment-baseline="middle"
                    :fill="textColor"
                    class="text-xs"
                >
                    {{ formatNumber(label.value) }}
                </text>
            </template>

            <!-- Trend line -->
            <path
                :d="`M ${points}`"
                fill="none"
                :stroke="lineColor"
                stroke-width="2"
                class="transition-all duration-300"
            />

            <!-- Data points -->
            <template v-for="point in points.split(' ')" :key="point">
                <circle
                    :cx="point.split(',')[0]"
                    :cy="point.split(',')[1]"
                    r="3"
                    :fill="lineColor"
                    class="transition-all duration-300"
                />
            </template>

            <!-- X-axis labels -->
            <template v-for="label in xLabels" :key="label.x">
                <text
                    v-if="label.show"
                    :x="label.x"
                    :y="height - padding.bottom + 20"
                    text-anchor="end"
                    :fill="textColor"
                    class="text-xs"
                    :transform="`rotate(-45, ${label.x}, ${height - padding.bottom + 20})`"
                >
                    {{ label.label }}
                </text>
            </template>
        </svg>
    </div>
</template>
