import {baseComponents, type ChartOptions, echarts} from './base';
import {BarChart, LineChart} from 'echarts/charts';
import {LegendComponent} from 'echarts/components';

echarts.use([
    ...baseComponents,
    LineChart,
    BarChart,
    LegendComponent
]);

interface DailyStats {
    date: string;
    page_views_unique: number;
    page_views_total: number;
    external_project_unique: number;
    external_project_total: number;
    custom_links_unique: number;
    custom_links_total: number;
}

interface LinkStats {
    link_name: string;
    unique_clicks: number;
    total_clicks: number;
}

export class AnalyticsChart {
    private charts: { [key: string]: echarts.ECharts } = {};

    constructor(
        private dailyStats: DailyStats[],
        private linkStats: LinkStats[]
    ) {}

    init() {
        this.initOverviewChart();
        this.initPageViewsChart();
        this.initExternalChart();
        this.initDownloadsChart();
        this.setupDarkMode();
        this.setupResizeHandler();
    }

    dispose() {
        Object.values(this.charts).forEach(chart => chart.dispose());
    }

    private getBaseOption(): any {
        const isDark = document.documentElement.classList.contains('dark');
        
        return {
            backgroundColor: 'transparent',
            textStyle: {
                color: isDark ? '#ffffff' : '#374151',
                fontFamily: 'system-ui, -apple-system, sans-serif'
            },
            grid: {
                left: '3%',
                right: '4%',
                bottom: '3%',
                top: '15%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                axisLine: {
                    lineStyle: {
                        color: isDark ? '#64748b' : '#d1d5db'
                    }
                },
                axisLabel: {
                    color: isDark ? '#ffffff' : '#374151',
                    fontSize: 12
                },
                splitLine: {
                    show: true,
                    lineStyle: {
                        color: isDark ? '#64748b' : '#e5e7eb'
                    }
                }
            },
            yAxis: {
                type: 'value',
                axisLine: {
                    lineStyle: {
                        color: isDark ? '#64748b' : '#d1d5db'
                    }
                },
                axisLabel: {
                    color: isDark ? '#ffffff' : '#374151',
                    fontSize: 12
                },
                splitLine: {
                    show: true,
                    lineStyle: {
                        color: isDark ? '#64748b' : '#e5e7eb'
                    }
                }
            },
            legend: {
                show: true,
                top: '10px',
                left: 'center',
                textStyle: {
                    color: isDark ? '#ffffff' : '#374151',
                    fontSize: 12
                }
            },
            tooltip: {
                backgroundColor: isDark ? 'rgba(31, 41, 55, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                borderColor: isDark ? '#374151' : '#E5E7EB',
                textStyle: {
                    color: isDark ? '#ffffff' : '#374151'
                }
            },
            animation: false
        };
    }

    private initOverviewChart() {
        const container = document.getElementById('overviewChart');
        if (!container) return;

        const dates = this.dailyStats.map(day => {
            const date = new Date(day.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        this.charts.overview = echarts.init(container);
        const baseOption = this.getBaseOption();
        const option = {
            ...baseOption,
            xAxis: {
                ...baseOption.xAxis,
                data: dates
            },
            series: [
                {
                    name: 'Page Views (Unique)',
                    type: 'line',
                    data: this.dailyStats.map(day => day.page_views_unique),
                    lineStyle: { color: '#3b82f6', width: 3 },
                    itemStyle: { color: '#3b82f6' },
                    smooth: true
                },
                {
                    name: 'itch.io Visits (Unique)',
                    type: 'line',
                    data: this.dailyStats.map(day => day.external_project_unique),
                    lineStyle: { color: '#8b5cf6', width: 3 },
                    itemStyle: { color: '#8b5cf6' },
                    smooth: true
                },
                {
                    name: 'Downloads (Unique)',
                    type: 'line',
                    data: this.dailyStats.map(day => day.custom_links_unique),
                    lineStyle: { color: '#10b981', width: 3 },
                    itemStyle: { color: '#10b981' },
                    smooth: true
                }
            ]
        };
        this.charts.overview.setOption(option);
    }

    private initPageViewsChart() {
        const container = document.getElementById('pageviewsChart');
        if (!container) return;

        const dates = this.dailyStats.map(day => {
            const date = new Date(day.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        this.charts.pageviews = echarts.init(container);
        const baseOption = this.getBaseOption();
        const option = {
            ...baseOption,
            xAxis: {
                ...baseOption.xAxis,
                data: dates
            },
            series: [
                {
                    name: 'Unique Views',
                    type: 'line',
                    data: this.dailyStats.map(day => day.page_views_unique),
                    lineStyle: { color: '#3b82f6', width: 3 },
                    itemStyle: { color: '#3b82f6' },
                    symbol: 'circle',
                    symbolSize: 6,
                    smooth: true
                },
                {
                    name: 'Total Views',
                    type: 'line',
                    data: this.dailyStats.map(day => day.page_views_total),
                    lineStyle: { color: '#93c5fd', width: 3 },
                    itemStyle: { color: '#93c5fd' },
                    symbol: 'circle',
                    symbolSize: 6,
                    smooth: true
                }
            ]
        };
        this.charts.pageviews.setOption(option);
    }

    private initExternalChart() {
        const container = document.getElementById('externalChart');
        if (!container) return;

        const dates = this.dailyStats.map(day => {
            const date = new Date(day.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        this.charts.external = echarts.init(container);
        const baseOption = this.getBaseOption();
        const option = {
            ...baseOption,
            xAxis: {
                ...baseOption.xAxis,
                data: dates
            },
            series: [
                {
                    name: 'Unique Visits',
                    type: 'line',
                    data: this.dailyStats.map(day => day.external_project_unique),
                    lineStyle: { color: '#8b5cf6', width: 3 },
                    itemStyle: { color: '#8b5cf6' },
                    symbol: 'circle',
                    symbolSize: 6,
                    smooth: true
                },
                {
                    name: 'Total Visits',
                    type: 'line',
                    data: this.dailyStats.map(day => day.external_project_total),
                    lineStyle: { color: '#c4b5fd', width: 3 },
                    itemStyle: { color: '#c4b5fd' },
                    symbol: 'circle',
                    symbolSize: 6,
                    smooth: true
                }
            ]
        };
        this.charts.external.setOption(option);
    }

    private initDownloadsChart() {
        const container = document.getElementById('downloadsChart');
        if (!container || !this.linkStats.length) return;

        const linkNames = this.linkStats.map(link => link.link_name);
        const linkColors = ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316', '#ec4899'];

        this.charts.downloads = echarts.init(container);
        const baseOption = this.getBaseOption();
        const option = {
            ...baseOption,
            xAxis: {
                ...baseOption.xAxis,
                data: linkNames
            },
            legend: {
                show: false // Hide legend for bar chart with single series
            },
            series: [
                {
                    name: 'Unique Downloads',
                    type: 'bar',
                    data: this.linkStats.map((link, index) => ({
                        value: link.unique_clicks,
                        itemStyle: {
                            color: linkColors[index % linkColors.length]
                        }
                    }))
                }
            ]
        };
        this.charts.downloads.setOption(option);
    }

    private setupDarkMode() {
        const observer = new MutationObserver(() => {
            // Re-apply options when dark mode changes
            Object.entries(this.charts).forEach(([key, chart]) => {
                const baseOption = this.getBaseOption();
                chart.setOption({
                    textStyle: baseOption.textStyle,
                    xAxis: baseOption.xAxis,
                    yAxis: baseOption.yAxis,
                    legend: baseOption.legend,
                    tooltip: baseOption.tooltip
                });
            });
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    private setupResizeHandler() {
        const handleResize = () => {
            Object.values(this.charts).forEach(chart => chart.resize());
        };
        window.addEventListener('resize', handleResize);
    }

    showChart(chartType: string) {
        // Hide all chart containers
        document.querySelectorAll('.chart-container').forEach(container => {
            container.classList.add('hidden');
        });

        // Remove active class from all tabs
        document.querySelectorAll('.chart-tab').forEach(tab => {
            tab.classList.remove('active', 'border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            tab.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
        });

        // Show selected chart container
        const chartContainer = document.getElementById('chart-' + chartType);
        if (chartContainer) {
            chartContainer.classList.remove('hidden');
        }

        // Add active class to selected tab
        const activeTab = document.getElementById('tab-' + chartType);
        if (activeTab) {
            activeTab.classList.add('active', 'border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            activeTab.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
        }

        // Resize chart if needed
        if (this.charts[chartType]) {
            this.charts[chartType].resize();
        }
    }
}