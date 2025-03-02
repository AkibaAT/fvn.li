import {baseComponents, type ChartOptions, echarts, getBaseChartOptions} from './base';
import {LineChart} from 'echarts/charts';
import type {MonthlyTrendData} from '@/types/system';

echarts.use([
    ...baseComponents,
    LineChart
]);

export class TrendChart {
    private chart: echarts.ECharts | null = null;
    private observer: MutationObserver | null = null;

    constructor(
        private element: HTMLElement,
        private data: MonthlyTrendData[],
        private options: ChartOptions = {}
    ) {
    }

    init() {
        this.chart = echarts.init(this.element);
        this.chart.setOption(getBaseChartOptions(this.data, this.options));
        this.setupDarkMode();
        this.setupResizeHandler();
    }

    dispose() {
        this.observer?.disconnect();
        this.chart?.dispose();
    }

    private setupDarkMode() {
        this.observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    const isDark = document.documentElement.classList.contains('dark');
                    this.updateDarkMode(isDark);
                }
            });
        });

        this.observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    private updateDarkMode(isDark: boolean) {
        if (!this.chart) return;

        const tooltipBackgroundColor = isDark ? 'rgba(31, 41, 55, 0.9)' : 'rgba(255, 255, 255, 0.9)';
        const tooltipBorderColor = isDark ? '#374151' : '#E5E7EB';
        const dataZoomBg = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(255, 255, 255, 0.1)';

        this.chart.setOption({
            tooltip: {
                backgroundColor: tooltipBackgroundColor,
                borderColor: tooltipBorderColor
            },
            dataZoom: [{
                backgroundColor: dataZoomBg
            }]
        });
    }

    private setupResizeHandler() {
        const handleResize = () => this.chart?.resize();
        window.addEventListener('resize', handleResize);
    }
}
