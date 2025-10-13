import Chart from '@/components/charts/chart';
import type {MonthlyTrendData} from '@/types/system';
import {Chart as ChartJS, type Plugin, type TooltipItem} from 'chart.js';
import React, {useEffect, useMemo, useState} from 'react';

interface RatingStats {
    monthly_trend: MonthlyTrendData[];
    visible_games_monthly_trend: MonthlyTrendData[];
}

interface RatingTrendsProps {
    ratingStats: RatingStats;
}

const RatingTrends: React.FC<RatingTrendsProps> = ({ratingStats}) => {
    const [isDark, setIsDark] = useState<boolean>(
        typeof document !== 'undefined'
            ? document.documentElement.classList.contains('dark')
            : false,
    );

    useEffect(() => {
        const el = document.documentElement;
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.attributeName === 'class') {
                    setIsDark(el.classList.contains('dark'));
                }
            }
        });
        observer.observe(el, {attributes: true, attributeFilter: ['class']});
        return () => observer.disconnect();
    }, []);

    const axisTextColor = getComputedStyle(document.documentElement).getPropertyValue(isDark ? '--color-chart-axis-line-dark' : '--color-chart-axis-line-light').trim();
    const gridColor = getComputedStyle(document.documentElement).getPropertyValue('--color-chart-grid-split-line').trim();
    const tooltipBg = getComputedStyle(document.documentElement).getPropertyValue('--color-tooltip-background').trim();
    const tooltipBorder = getComputedStyle(document.documentElement).getPropertyValue('--color-tooltip-border').trim();
    const tooltipTitle = getComputedStyle(document.documentElement).getPropertyValue('--color-tooltip-title').trim();
    const tooltipBody = getComputedStyle(document.documentElement).getPropertyValue('--color-tooltip-body').trim();

    const allRatingsData = useMemo(() => {
        if (!ratingStats.monthly_trend) {
            return {labels: [], datasets: []};
        }
        const labels = ratingStats.monthly_trend.map((d) => {
            const date = new Date(d.month);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                year: 'numeric',
            });
        });
        const data = ratingStats.monthly_trend.map((d) => d.count);
        return {
            labels,
            datasets: [
                {
                    label: 'All Ratings',
                    data,
                    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--color-chart-warning').trim(),
                    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-chart-warning-bg').trim(),
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
            ],
        };
    }, [ratingStats.monthly_trend]);

    const listedGamesData = useMemo(() => {
        if (!ratingStats.visible_games_monthly_trend) {
            return {labels: [], datasets: []};
        }
        const labels = ratingStats.visible_games_monthly_trend.map((d) => {
            const date = new Date(d.month);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                year: 'numeric',
            });
        });
        const data = ratingStats.visible_games_monthly_trend.map(
            (d) => d.count,
        );
        return {
            labels,
            datasets: [
                {
                    label: 'Listed Games Ratings',
                    data,
                    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--color-chart-success').trim(),
                    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-chart-success-bg').trim(),
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
            ],
        };
    }, [ratingStats.visible_games_monthly_trend]);

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index' as const,
            intersect: false,
        },
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                enabled: true,
                backgroundColor: tooltipBg,
                borderColor: tooltipBorder,
                borderWidth: 1,
                titleColor: tooltipTitle,
                bodyColor: tooltipBody,
                displayColors: false,
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    title: (items: TooltipItem<'line'>[]) => {
                        return items[0]?.label ?? '';
                    },
                    label: (item: TooltipItem<'line'>) => {
                        const label = item.dataset.label || 'Value';
                        const raw = item.parsed.y as number;
                        const val = new Intl.NumberFormat().format(raw ?? 0);
                        return `${label}: ${val}`;
                    },
                },
            },
        },
        scales: {
            x: {
                ticks: {
                    color: axisTextColor,
                },
                grid: {
                    display: true,
                    color: gridColor,
                    drawTicks: false,
                    borderColor: gridColor,
                    borderDash: [2, 2],
                },
            },
            y: {
                ticks: {
                    color: axisTextColor,
                    precision: 0,
                },
                grid: {
                    display: true,
                    color: gridColor,
                    borderDash: [3, 3],
                    drawBorder: false,
                },
            },
        },
    };

    // Plugin to draw a vertical hover line intersecting the chart
    const hoverLinePlugin: Plugin<'line'> = {
        id: 'hoverLine',
        afterDatasetsDraw: (chart) => {
            const {ctx, tooltip, chartArea} = chart;
            if (!tooltip?.getActiveElements()?.length) return;
            // caretX is not in the public types; access via index signature safely
            const maybeCaretX = (tooltip as unknown as Record<string, unknown>)
                .caretX as number | undefined;
            const x = maybeCaretX;
            if (!x || x < chartArea.left || x > chartArea.right) return;

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(x, chartArea.top);
            ctx.lineTo(x, chartArea.bottom);
            ctx.lineWidth = 1;
            ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue(isDark ? '--color-chart-grid-line-light' : '--color-chart-axis-line-light').trim();
            ctx.stroke();
            ctx.restore();
        },
    };

    // Register plugin globally to avoid passing it via props
    ChartJS.register(hoverLinePlugin);

    return (
        <>
            {/* All Ratings Trend */}
            <div className="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                <div className="space-y-8">
                    <div>
                        <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                            All Ratings Trend
                        </h2>
                        <div className="relative h-[240px] w-full">
                            <Chart
                                data={allRatingsData}
                                options={chartOptions}
                                style={{height: '240px', width: '100%'}}
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Listed Games Ratings Trend */}
            <div className="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                <div className="space-y-8">
                    <div>
                        <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Listed Games Ratings Trend
                        </h2>
                        <div className="relative h-[240px] w-full">
                            <Chart
                                data={listedGamesData}
                                options={chartOptions}
                                style={{height: '240px', width: '100%'}}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

export default RatingTrends;
