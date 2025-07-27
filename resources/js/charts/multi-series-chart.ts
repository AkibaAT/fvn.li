import {baseComponents, type ChartOptions, echarts} from './base';
import {LineChart} from 'echarts/charts';
import {LegendComponent, ToolboxComponent} from 'echarts/components';
import type {MonthlyTrendData} from '@/types/system';

echarts.use([
    ...baseComponents,
    LineChart,
    LegendComponent,
    ToolboxComponent
]);

interface SeriesData {
    name: string;
    data: MonthlyTrendData[];
    color: string;
}

interface MultiSeriesOptions extends ChartOptions {
    title?: string;
    animation?: boolean;
}

export class MultiSeriesChart {
    private chart: echarts.ECharts | null = null;
    private observer: MutationObserver | null = null;

    constructor(
        private element: HTMLElement,
        private series: SeriesData[],
        private options: MultiSeriesOptions = {}
    ) {
    }

    init() {
        this.chart = echarts.init(this.element);
        this.chart.setOption(this.getChartOptions());
        this.setupDarkMode();
        this.setupResizeHandler();
    }

    dispose() {
        this.observer?.disconnect();
        this.chart?.dispose();
    }

    private getChartOptions() {
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#4B5563' : '#E5E7EB';  // Swapped: now uses old grid colors
        const gridColor = isDark ? '#ffffff' : '#6B7280';  // Swapped: now uses old text colors

        // Get all unique dates from all series and sort chronologically
        const allDateObjects = new Map<string, Date>();
        this.series.forEach(series => {
            series.data.forEach(item => {
                const date = new Date(item.month);
                const dateStr = date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
                allDateObjects.set(dateStr, date);
            });
        });
        
        // Sort dates chronologically
        const dates = Array.from(allDateObjects.entries())
            .sort(([,a], [,b]) => a.getTime() - b.getTime())
            .map(([dateStr]) => dateStr);

        // Create series data
        const seriesConfig = this.series.map((series, index) => {
            const values = dates.map(date => {
                const item = series.data.find(d => {
                    const itemDate = new Date(d.month);
                    const itemDateStr = itemDate.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
                    return itemDateStr === date;
                });
                return item ? item.count : 0;
            });

            return {
                name: series.name,
                type: 'line',
                smooth: true,
                data: values,
                lineStyle: {
                    width: 3,
                    color: series.color
                },
                itemStyle: {
                    color: series.color
                },
                areaStyle: {
                    opacity: 0.1,
                    color: series.color
                },
                symbol: 'circle',
                symbolSize: 6,
                emphasis: {
                    focus: 'series',
                    lineStyle: {
                        width: 4
                    },
                    itemStyle: {
                        borderColor: series.color,
                        borderWidth: 2
                    }
                },
                markPoint: {
                    data: [
                        { type: 'max', name: 'Max' },
                        { type: 'min', name: 'Min' }
                    ]
                }
            };
        });

        return {
            backgroundColor: 'transparent',
            textStyle: {
                color: textColor,
                fontFamily: 'system-ui, -apple-system, sans-serif'
            },
            title: {
                text: this.options.title || 'Analytics Overview',
                left: 'center',
                top: '20px',
                textStyle: {
                    color: textColor,
                    fontSize: 16,
                    fontWeight: 'normal'
                }
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'cross',
                    label: {
                        backgroundColor: '#6a7985'
                    }
                },
                backgroundColor: isDark ? 'rgba(31, 41, 55, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                borderColor: isDark ? '#374151' : '#E5E7EB',
                borderWidth: 1,
                textStyle: {
                    color: textColor
                }
            },
            legend: {
                data: this.series.map(s => s.name),
                top: '50px',
                left: 'center',
                textStyle: {
                    color: textColor,
                    fontSize: 12
                },
                selected: this.series.reduce((acc, series) => {
                    acc[series.name] = true;
                    return acc;
                }, {} as Record<string, boolean>)
            },
            grid: {
                left: '3%',
                right: '4%',
                bottom: '3%',
                top: '100px',
                containLabel: true
            },
            toolbox: {
                feature: {
                    saveAsImage: {
                        show: true,
                        title: 'Save as Image',
                        iconStyle: {
                            borderColor: textColor
                        }
                    }
                },
                right: '20px',
                top: '20px'
            },
            xAxis: [
                {
                    type: 'category',
                    boundaryGap: false,
                    data: dates,
                    axisLabel: {
                        color: textColor,
                        fontSize: 11,
                        rotate: 45,
                        interval: 0
                    },
                    axisLine: {
                        lineStyle: {
                            color: gridColor
                        }
                    },
                    splitLine: {
                        show: false
                    }
                }
            ],
            yAxis: [
                {
                    type: 'value',
                    axisLabel: {
                        color: textColor,
                        fontSize: 11
                    },
                    axisLine: {
                        show: false
                    },
                    axisTick: {
                        show: false
                    },
                    splitLine: {
                        lineStyle: {
                            color: gridColor,
                            type: 'dashed'
                        }
                    }
                }
            ],
            series: seriesConfig,
            animation: this.options.animation !== false,
            animationDuration: this.options.animation !== false ? 1000 : 0,
            animationEasing: 'cubicOut'
        };
    }

    private setupDarkMode() {
        this.observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    // Re-initialize chart with updated theme
                    if (this.chart) {
                        this.chart.setOption(this.getChartOptions());
                    }
                }
            });
        });

        this.observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    private setupResizeHandler() {
        const handleResize = () => this.chart?.resize();
        window.addEventListener('resize', handleResize);
    }
}