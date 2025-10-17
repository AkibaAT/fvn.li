import Chart from '@/components/charts/chart';
import React, {useMemo, useState} from 'react';
import {formatDateTimeWithTimezone, getUserTimezone} from '@/utils/date-formatting';

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

interface GameStatsProps {
    clickStats?: ClickStats;
    dailyStats?: DailyStats[];
}

export default function GameStats({clickStats, dailyStats}: GameStatsProps) {
    const [activeTab, setActiveTab] = useState<'overview' | 'pageviews' | 'external' | 'downloads'>('overview');

    // Helper function to safely get CSS variable (SSR-safe)
    const getCSSVariable = (varName: string, fallback: string = '#000000'): string => {
        if (typeof document === 'undefined') return fallback;
        return getComputedStyle(document.documentElement).getPropertyValue(varName).trim() || fallback;
    };

    // Chart data preparation
    const chartData = useMemo(() => {
        if (!dailyStats || dailyStats.length === 0) return null;

        const labels = dailyStats.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
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

        return {overviewData, pageViewsData, externalData};
    }, [dailyStats]);

    const downloadsChartData = useMemo(() => {
        if (!dailyStats || dailyStats.length === 0) return null;

        const labels = dailyStats.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
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
    }, [dailyStats]);

    const tabs = [
        {id: 'overview', label: 'Overview'},
        {id: 'pageviews', label: 'Page Views'},
        {id: 'external', label: 'itch.io Visits'},
        {id: 'downloads', label: 'Downloads'},
    ] as const;

    return (
        <div className="space-y-6">
            {/* Analytics Summary */}
            {clickStats && (
                <div className="space-y-6">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div
                            className="rounded-xl border border-gray-200/50 bg-white/70 p-4 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Page Views</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {clickStats.page_views_unique}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                {clickStats.page_views_total} total
                            </div>
                        </div>
                        <div
                            className="rounded-xl border border-gray-200/50 bg-white/70 p-4 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">itch.io Visits</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {clickStats.external_project_unique}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                {clickStats.external_project_total} total
                            </div>
                        </div>
                        <div
                            className="rounded-xl border border-gray-200/50 bg-white/70 p-4 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Downloads</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {(() => {
                                    // Try to get downloads from daily stats first (more accurate)
                                    if (dailyStats && dailyStats.length > 0) {
                                        return dailyStats.reduce((sum, day) => sum + day.custom_links_unique, 0);
                                    }
                                    // Fallback to clickStats custom_links
                                    if (Array.isArray(clickStats.custom_links)) {
                                        return clickStats.custom_links.reduce((sum, link) => sum + link.unique_clicks, 0);
                                    }
                                    return 0;
                                })()}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                {(() => {
                                    // Try to get downloads from daily stats first (more accurate)
                                    if (dailyStats && dailyStats.length > 0) {
                                        return dailyStats.reduce((sum, day) => sum + day.custom_links_total, 0);
                                    }
                                    // Fallback to clickStats custom_links
                                    if (Array.isArray(clickStats.custom_links)) {
                                        return clickStats.custom_links.reduce((sum, link) => sum + link.total_clicks, 0);
                                    }
                                    return 0;
                                })()} total
                            </div>
                        </div>
                    </div>

                    {/* Analytics Insights */}
                    <div
                        className="rounded-xl border border-gray-200/50 bg-white/70 p-4 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                        <h3 className="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                            Insights (Last 30 Days)
                        </h3>
                        <div className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            {dailyStats && dailyStats.length > 0 && (() => {
                                const recentDays = dailyStats.slice(-7);
                                const avgDailyViews = recentDays.reduce((sum, day) => sum + day.page_views_unique, 0) / recentDays.length;
                                const totalViews = dailyStats.reduce((sum, day) => sum + day.page_views_unique, 0);
                                const totalDownloads = dailyStats.reduce((sum, day) => sum + day.custom_links_unique, 0);
                                const userTimezone = getUserTimezone();

                                return (
                                    <>
                                        <div>• Total unique views: <strong>{totalViews}</strong></div>
                                        {avgDailyViews > 0 && (
                                            <div>•
                                                Averaging <strong>{Math.round(avgDailyViews * 10) / 10}</strong> unique
                                                views per day this week</div>
                                        )}
                                        {totalDownloads > 0 && (
                                            <div>• Total downloads: <strong>{totalDownloads}</strong></div>
                                        )}
                                        {clickStats.last_page_view && (
                                            <div>• Last page
                                                view: <strong>{formatDateTimeWithTimezone(clickStats.last_page_view, false) || clickStats.last_page_view}</strong>
                                            </div>
                                        )}
                                        <div className="mt-3 pt-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-500">
                                            All times shown in your local timezone ({userTimezone})
                                        </div>
                                    </>
                                );
                            })()}
                            {(!dailyStats || dailyStats.length === 0) && (
                                <div>• No analytics data available yet. Share your game to start seeing insights!</div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Chart Tabs */}
            <div
                className="rounded-xl border border-gray-200/50 bg-white/70 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                <div className="border-b border-gray-200 dark:border-gray-700">
                    <nav className="flex space-x-8 px-6" aria-label="Tabs">
                        {tabs.map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium ${
                                    activeTab === tab.id
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </nav>
                </div>

                <div className="p-6">
                    {activeTab === 'overview' && chartData?.overviewData && (
                        <div className="h-80">
                            <Chart
                                data={chartData.overviewData}
                                options={{
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top' as const,
                                            labels: {
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                            }
                                        },
                                        title: {display: false},
                                    },
                                    scales: {
                                        x: {
                                            grid: {
                                                color: getCSSVariable('--color-chart-grid-line-dark', '#374151'),
                                            },
                                            ticks: {
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            grid: {
                                                color: getCSSVariable('--color-chart-grid-line-dark', '#374151'),
                                            },
                                            ticks: {
                                                stepSize: 1,
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                                callback: function (value) {
                                                    if (Number.isInteger(value)) {
                                                        return value;
                                                    }
                                                }
                                            }
                                        },
                                    },
                                }}
                                style={{height: '100%', width: '100%'}}
                            />
                        </div>
                    )}

                    {activeTab === 'pageviews' && chartData?.pageViewsData && (
                        <div className="h-80">
                            <Chart
                                data={chartData.pageViewsData}
                                options={{
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top' as const,
                                            labels: {
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                            }
                                        },
                                        title: {display: false},
                                    },
                                    scales: {
                                        x: {
                                            grid: {
                                                color: getCSSVariable('--color-chart-grid-line-dark', '#374151'),
                                            },
                                            ticks: {
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            grid: {
                                                color: getCSSVariable('--color-chart-grid-line-dark', '#374151'),
                                            },
                                            ticks: {
                                                stepSize: 1,
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                                callback: function (value) {
                                                    if (Number.isInteger(value)) {
                                                        return value;
                                                    }
                                                }
                                            }
                                        },
                                    },
                                }}
                                style={{height: '100%', width: '100%'}}
                            />
                        </div>
                    )}

                    {activeTab === 'external' && chartData?.externalData && (
                        <div className="h-80">
                            <Chart
                                data={chartData.externalData}
                                options={{
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top' as const,
                                            labels: {
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                            }
                                        },
                                        title: {display: false},
                                    },
                                    scales: {
                                        x: {
                                            grid: {
                                                color: getCSSVariable('--color-chart-grid-line-dark', '#374151'),
                                            },
                                            ticks: {
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            grid: {
                                                color: getCSSVariable('--color-chart-grid-line-dark', '#374151'),
                                            },
                                            ticks: {
                                                stepSize: 1,
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                                callback: function (value) {
                                                    if (Number.isInteger(value)) {
                                                        return value;
                                                    }
                                                }
                                            }
                                        },
                                    },
                                }}
                                style={{height: '100%', width: '100%'}}
                            />
                        </div>
                    )}

                    {activeTab === 'downloads' && downloadsChartData && (
                        <div className="h-80">
                            <Chart
                                data={downloadsChartData}
                                options={{
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'top' as const,
                                            labels: {
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                            }
                                        },
                                        title: {display: false},
                                    },
                                    scales: {
                                        x: {
                                            grid: {
                                                color: getCSSVariable('--color-chart-grid-line-dark', '#374151'),
                                            },
                                            ticks: {
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            grid: {
                                                color: getCSSVariable('--color-chart-grid-line-dark', '#374151'),
                                            },
                                            ticks: {
                                                stepSize: 1,
                                                color: getCSSVariable('--color-chart-grid-axis-label', '#9ca3af'),
                                                callback: function (value) {
                                                    if (Number.isInteger(value)) {
                                                        return value;
                                                    }
                                                }
                                            }
                                        },
                                    },
                                }}
                                style={{height: '100%', width: '100%'}}
                            />
                        </div>
                    )}

                    {!chartData && !downloadsChartData && (
                        <div className="flex h-80 items-center justify-center text-gray-500 dark:text-gray-400">
                            No analytics data available yet. Share your game to start seeing insights!
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
