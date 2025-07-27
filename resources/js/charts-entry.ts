import {TrendChart} from './charts/trend-chart';
import {MultiSeriesChart} from './charts/multi-series-chart';
import type {MonthlyTrendData} from './types/system';

// Create a promise that resolves when initialization is complete
const initPromise = new Promise<void>((resolve) => {
    // Initialize the chart functionality
    window.initializeTrendChart = (
        element: HTMLElement,
        data: MonthlyTrendData[],
        options = {}
    ) => {
        const chart = new TrendChart(element, data, options);
        chart.init();
        return chart;
    };

    // Initialize multi-series chart functionality
    window.initializeMultiSeriesChart = (
        element: HTMLElement,
        series: Array<{name: string, data: MonthlyTrendData[], color: string}>,
        options = {}
    ) => {
        const chart = new MultiSeriesChart(element, series, options);
        chart.init();
        return chart;
    };
    resolve();
});

// Export the promise so we can wait for it
window.chartInitialized = initPromise;
