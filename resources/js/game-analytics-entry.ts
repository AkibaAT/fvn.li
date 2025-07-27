import {AnalyticsChart} from './charts/analytics-chart';

// Create a promise that resolves when initialization is complete
const initPromise = new Promise<void>((resolve) => {
    // Initialize the analytics chart functionality
    window.initializeAnalyticsChart = (dailyStats: any[], linkStats: any[]) => {
        const chart = new AnalyticsChart(dailyStats, linkStats);
        chart.init();
        
        // Make showChart function globally available
        window.showChart = (chartType: string) => {
            chart.showChart(chartType);
        };
        
        return chart;
    };
    resolve();
});

// Export the promise so we can wait for it
window.analyticsChartInitialized = initPromise;