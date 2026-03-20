<script lang="ts">
    import Chart from '@/components/charts/Chart.svelte';
    import { formatDateTimeWithTimezone, getUserTimezone } from '@/utils/date-formatting';

    interface DailyStats {
        date: string;
        page_views_unique: number;
        page_views_total: number;
        external_project_unique: number;
        external_project_total: number;
        custom_links_unique: number;
        custom_links_total: number;
    }

    interface ClickStats {
        page_views_total: number;
        page_views_unique: number;
        last_page_view?: string;
        external_project_total: number;
        external_project_unique: number;
        last_external_project?: string;
        custom_links?: Array<{
            link_id: string;
            link_name: string;
            total_clicks: number;
            unique_clicks: number;
            last_click?: string;
        }>;
    }

    let {
        clickStats,
        dailyStats,
    }: {
        clickStats?: ClickStats;
        dailyStats?: DailyStats[];
    } = $props();

    let activeTab = $state<'overview' | 'pageviews' | 'external' | 'downloads'>('overview');

    const getCSSVariable = (varName: string, fallback: string = '#000000'): string => {
        if (typeof document === 'undefined') return fallback;
        return getComputedStyle(document.documentElement).getPropertyValue(varName).trim() || fallback;
    };

    const chartData = $derived((() => {
        if (!dailyStats || dailyStats.length === 0) return null;

        const labels = dailyStats.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        const overviewData = {
            labels,
            datasets: [
                {
                    label: 'Page Views (Unique)',
                    data: dailyStats.map(d => d.page_views_unique),
                    borderColor: getCSSVariable('--color-chart-primary', '#3b82f6'),
                    backgroundColor: getCSSVariable('--color-chart-primary-bg', 'rgba(59, 130, 246, 0.1)'),
                    tension: 0.4,
                },
                {
                    label: 'itch.io Visits (Unique)',
                    data: dailyStats.map(d => d.external_project_unique),
                    borderColor: getCSSVariable('--color-chart-secondary', '#8b5cf6'),
                    backgroundColor: getCSSVariable('--color-chart-secondary-bg', 'rgba(139, 92, 246, 0.1)'),
                    tension: 0.4,
                },
                {
                    label: 'Downloads (Unique)',
                    data: dailyStats.map(d => d.custom_links_unique),
                    borderColor: getCSSVariable('--color-chart-success', '#10b981'),
                    backgroundColor: getCSSVariable('--color-chart-success-bg', 'rgba(16, 185, 129, 0.1)'),
                    tension: 0.4,
                },
            ],
        };

        const pageViewsData = {
            labels,
            datasets: [
                {
                    label: 'Unique Views',
                    data: dailyStats.map(d => d.page_views_unique),
                    borderColor: getCSSVariable('--color-chart-primary', '#3b82f6'),
                    backgroundColor: getCSSVariable('--color-chart-primary-bg', 'rgba(59, 130, 246, 0.1)'),
                    tension: 0.4,
                },
                {
                    label: 'Total Views',
                    data: dailyStats.map(d => d.page_views_total),
                    borderColor: getCSSVariable('--color-chart-primary-light', '#93c5fd'),
                    backgroundColor: getCSSVariable('--color-chart-primary-light-bg', 'rgba(147, 197, 253, 0.1)'),
                    tension: 0.4,
                },
            ],
        };

        const externalData = {
            labels,
            datasets: [
                {
                    label: 'Unique Visits',
                    data: dailyStats.map(d => d.external_project_unique),
                    borderColor: getCSSVariable('--color-chart-secondary', '#8b5cf6'),
                    backgroundColor: getCSSVariable('--color-chart-secondary-bg', 'rgba(139, 92, 246, 0.1)'),
                    tension: 0.4,
                },
                {
                    label: 'Total Visits',
                    data: dailyStats.map(d => d.external_project_total),
                    borderColor: getCSSVariable('--color-chart-secondary-light', '#c4b5fd'),
                    backgroundColor: getCSSVariable('--color-chart-secondary-light-bg', 'rgba(196, 181, 253, 0.1)'),
                    tension: 0.4,
                },
            ],
        };

        return { overviewData, pageViewsData, externalData };
    })());

    const downloadsChartData = $derived((() => {
        if (!dailyStats || dailyStats.length === 0) return null;

        const labels = dailyStats.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        return {
            labels,
            datasets: [
                {
                    label: 'Unique Downloads',
                    data: dailyStats.map(d => d.custom_links_unique),
                    borderColor: getCSSVariable('--color-chart-success', '#10b981'),
                    backgroundColor: getCSSVariable('--color-chart-success-bg', 'rgba(16, 185, 129, 0.1)'),
                    tension: 0.4,
                },
                {
                    label: 'Total Downloads',
                    data: dailyStats.map(d => d.custom_links_total),
                    borderColor: getCSSVariable('--color-chart-success-light', '#6ee7b7'),
                    backgroundColor: getCSSVariable('--color-chart-success-light-bg', 'rgba(110, 231, 183, 0.1)'),
                    tension: 0.4,
                },
            ],
        };
    })());

    const tabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'pageviews', label: 'Page Views' },
        { id: 'external', label: 'itch.io Visits' },
        { id: 'downloads', label: 'Downloads' },
    ] as const;

    const makeChartOptions = (legendColor: string, gridColor: string) => ({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top' as const,
                labels: { color: legendColor },
            },
            title: { display: false },
        },
        scales: {
            x: {
                grid: { color: gridColor },
                ticks: { color: legendColor },
            },
            y: {
                beginAtZero: true,
                grid: { color: gridColor },
                ticks: {
                    stepSize: 1,
                    color: legendColor,
                    callback: function (value: number | string) {
                        if (Number.isInteger(value)) return value;
                    },
                },
            },
        },
    });

    const chartOptions = makeChartOptions(
        getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
        getCSSVariable('--color-chart-grid-line-dark', '#374151'),
    );

    // Computed stats
    const downloadsUnique = $derived((() => {
        if (dailyStats && dailyStats.length > 0) {
            return dailyStats.reduce((sum, day) => sum + day.custom_links_unique, 0);
        }
        if (Array.isArray(clickStats?.custom_links)) {
            return clickStats!.custom_links!.reduce((sum, link) => sum + link.unique_clicks, 0);
        }
        return 0;
    })());

    const downloadsTotal = $derived((() => {
        if (dailyStats && dailyStats.length > 0) {
            return dailyStats.reduce((sum, day) => sum + day.custom_links_total, 0);
        }
        if (Array.isArray(clickStats?.custom_links)) {
            return clickStats!.custom_links!.reduce((sum, link) => sum + link.total_clicks, 0);
        }
        return 0;
    })());

    const insights = $derived((() => {
        if (!dailyStats || dailyStats.length === 0) return null;
        const recentDays = dailyStats.slice(-7);
        const avgDailyViews = recentDays.reduce((sum, day) => sum + day.page_views_unique, 0) / recentDays.length;
        const totalViews = dailyStats.reduce((sum, day) => sum + day.page_views_unique, 0);
        const totalDownloads = dailyStats.reduce((sum, day) => sum + day.custom_links_unique, 0);
        return { avgDailyViews, totalViews, totalDownloads, userTimezone: getUserTimezone() };
    })());
</script>

<div class="space-y-6">
    <!-- Analytics Summary -->
    {#if clickStats}
        <div class="space-y-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200/50 bg-white/70 p-4 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Page Views</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{clickStats.page_views_unique}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{clickStats.page_views_total} total</div>
                </div>
                <div class="rounded-xl border border-gray-200/50 bg-white/70 p-4 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">itch.io Visits</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{clickStats.external_project_unique}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{clickStats.external_project_total} total</div>
                </div>
                <div class="rounded-xl border border-gray-200/50 bg-white/70 p-4 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Downloads</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{downloadsUnique}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{downloadsTotal} total</div>
                </div>
            </div>

            <!-- Analytics Insights -->
            <div class="rounded-xl border border-gray-200/50 bg-white/70 p-4 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Insights (Last 30 Days)</h3>
                <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    {#if insights}
                        <div>&#8226; Total unique views: <strong>{insights.totalViews}</strong></div>
                        {#if insights.avgDailyViews > 0}
                            <div>&#8226; Averaging <strong>{Math.round(insights.avgDailyViews * 10) / 10}</strong> unique views per day this week</div>
                        {/if}
                        {#if insights.totalDownloads > 0}
                            <div>&#8226; Total downloads: <strong>{insights.totalDownloads}</strong></div>
                        {/if}
                        {#if clickStats.last_page_view}
                            <div>&#8226; Last page view: <strong>{formatDateTimeWithTimezone(clickStats.last_page_view, false) || clickStats.last_page_view}</strong></div>
                        {/if}
                        <div class="mt-3 border-t border-gray-200 pt-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-500">
                            All times shown in your local timezone ({insights.userTimezone})
                        </div>
                    {:else}
                        <div>&#8226; No analytics data available yet. Share your game to start seeing insights!</div>
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    <!-- Chart Tabs -->
    <div class="rounded-xl border border-gray-200/50 bg-white/70 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
                {#each tabs as tab (tab.id)}
                    <button
                        onclick={() => activeTab = tab.id}
                        class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium {activeTab === tab.id
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'}"
                    >
                        {tab.label}
                    </button>
                {/each}
            </nav>
        </div>

        <div class="p-6">
            {#if activeTab === 'overview' && chartData?.overviewData}
                <Chart data={chartData.overviewData} options={chartOptions} style="height: 320px;" />
            {/if}

            {#if activeTab === 'pageviews' && chartData?.pageViewsData}
                <Chart data={chartData.pageViewsData} options={chartOptions} style="height: 320px;" />
            {/if}

            {#if activeTab === 'external' && chartData?.externalData}
                <Chart data={chartData.externalData} options={chartOptions} style="height: 320px;" />
            {/if}

            {#if activeTab === 'downloads' && downloadsChartData}
                <Chart data={downloadsChartData} options={chartOptions} style="height: 320px;" />
            {/if}

            {#if !chartData && !downloadsChartData}
                <div class="flex h-80 items-center justify-center text-gray-500 dark:text-gray-400">
                    No analytics data available yet. Share your game to start seeing insights!
                </div>
            {/if}
        </div>
    </div>
</div>
