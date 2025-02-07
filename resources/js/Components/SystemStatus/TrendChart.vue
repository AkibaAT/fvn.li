<script setup lang="ts">
import type { ECharts } from 'echarts';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import type { MonthlyTrendData } from '@/types/system';

interface Props {
    data: MonthlyTrendData[];
    lineColor?: string;
    areaColor?: string;
    textColor?: string;
    gridColor?: string;
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    lineColor: '#EAB308', // yellow-500
    areaColor: 'rgba(234, 179, 8, 0.1)',
    textColor: '#6B7280', // gray-500
    gridColor: '#E5E7EB', // gray-200
    title: undefined,
});

const chartContainer = ref<HTMLElement | null>(null);
const isLoading = ref(true);
let chart: ECharts | null = null;
let echarts: any = null;

// Prepare chart data
const getChartOptions = () => {
    const dates = props.data.map(item => {
        const date = new Date(item.month);
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    const values = props.data.map(item => item.count);

    return {
        title: props.title ? {
            text: props.title,
            textStyle: {
                color: props.textColor,
                fontSize: 16,
                fontWeight: 'normal'
            },
            left: 'center',
            top: 0,
            padding: [0, 0, 20, 0]
        } : undefined,
        tooltip: {
            trigger: 'axis',
            formatter: '{b}: {c} ratings',
            backgroundColor: 'rgba(255, 255, 255, 0.9)',
            borderColor: props.gridColor,
            textStyle: {
                color: props.textColor
            }
        },
        grid: {
            left: '2%',
            right: '2%',
            bottom: '20%',
            top: '5%',
            containLabel: true
        },
        dataZoom: [{
            show: true,
            start: 0,
            end: 100,
            height: 30,
            bottom: 10,
            borderColor: props.gridColor,
            backgroundColor: 'rgba(255, 255, 255, 0.1)',
            fillerColor: props.areaColor,
            handleStyle: {
                color: props.lineColor,
                borderColor: props.lineColor
            },
            textStyle: {
                color: props.textColor
            },
            moveHandleStyle: {
                color: props.lineColor
            },
            selectedDataBackground: {
                lineStyle: {
                    color: props.lineColor
                },
                areaStyle: {
                    color: props.areaColor
                }
            }
        }],
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: dates,
            axisLabel: {
                color: props.textColor,
                rotate: 45
            },
            axisLine: {
                lineStyle: {
                    color: props.gridColor
                }
            },
            splitLine: {
                show: false
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                color: props.textColor,
                formatter: '{value}'
            },
            axisLine: {
                show: false
            },
            splitLine: {
                lineStyle: {
                    color: props.gridColor,
                    type: 'dashed'
                }
            }
        },
        series: [
            {
                name: 'Ratings',
                type: 'line',
                smooth: true,
                data: values,
                itemStyle: {
                    color: props.lineColor
                },
                lineStyle: {
                    width: 2,
                    color: props.lineColor
                },
                areaStyle: {
                    color: props.areaColor
                },
                symbol: 'circle',
                symbolSize: 6
            }
        ]
    };
};

// Initialize chart
const initChart = async () => {
    try {
        // Dynamically import echarts core and necessary components
        const echartsModule = await import('echarts/core');
        const { LineChart } = await import('echarts/charts');
        const {
            TitleComponent,
            TooltipComponent,
            GridComponent,
            DataZoomComponent
        } = await import('echarts/components');
        const { UniversalTransition } = await import('echarts/features');
        const { CanvasRenderer } = await import('echarts/renderers');

        echarts = echartsModule;

        // Register necessary components
        echarts.use([
            LineChart,
            TitleComponent,
            TooltipComponent,
            GridComponent,
            DataZoomComponent,
            UniversalTransition,
            CanvasRenderer
        ]);

        if (chartContainer.value) {
            chart = echarts.init(chartContainer.value);
            chart?.setOption(getChartOptions());
        }
    } catch (error) {
        console.error('Failed to initialize chart:', error);
    } finally {
        isLoading.value = false;
    }
};

// Handle window resize
const handleResize = () => {
    chart?.resize();
};

// Watch for data changes
watch(() => props.data, () => {
    chart?.setOption(getChartOptions());
}, { deep: true });

// Dark mode handling
const updateDarkMode = (isDark: boolean) => {
    if (!chart) return;

    const options = chart.getOption();
    if (!options || !options.tooltip) return;

    // Update colors for dark mode
    const tooltipBackgroundColor = isDark ? 'rgba(31, 41, 55, 0.9)' : 'rgba(255, 255, 255, 0.9)';
    const tooltipBorderColor = isDark ? '#374151' : '#E5E7EB';
    const dataZoomBg = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(255, 255, 255, 0.1)';

    chart.setOption({
        tooltip: {
            backgroundColor: tooltipBackgroundColor,
            borderColor: tooltipBorderColor
        },
        dataZoom: [{
            backgroundColor: dataZoomBg
        }]
    });
};

// Lifecycle hooks
onMounted(() => {
    initChart();
    window.addEventListener('resize', handleResize);

    // Set up dark mode observer
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                const isDark = document.documentElement.classList.contains('dark');
                updateDarkMode(isDark);
            }
        });
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    chart?.dispose();
});
</script>

<template>
    <div class="relative h-[300px] w-full">
        <div ref="chartContainer" class="h-full w-full"></div>
        <div
            v-if="isLoading"
            class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50"
        >
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
        </div>
    </div>
</template>
