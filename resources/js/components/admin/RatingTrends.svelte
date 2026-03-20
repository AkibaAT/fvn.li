<script lang="ts">
    import Chart from '@/components/charts/Chart.svelte';
    import { Chart as ChartJS, type Plugin, type TooltipItem } from 'chart.js';
    import type { MonthlyTrendData } from '@/types/system';

    interface RatingStats {
        monthly_trend: MonthlyTrendData[];
        visible_games_monthly_trend: MonthlyTrendData[];
    }

    let { ratingStats }: { ratingStats: RatingStats } = $props();

    let colors = $state({
        axisTextColor: '',
        gridColor: '',
        tooltipBg: '',
        tooltipBorder: '',
        tooltipTitle: '',
        tooltipBody: '',
        warningColor: '',
        warningBgColor: '',
        successColor: '',
        successBgColor: '',
        hoverLineColor: '',
    });

    $effect(() => {
        if (typeof document === 'undefined') return;

        const el = document.documentElement;

        const updateColors = () => {
            const isDarkMode = el.classList.contains('dark');
            colors = {
                axisTextColor: getComputedStyle(el)
                    .getPropertyValue(isDarkMode ? '--color-chart-axis-line-dark' : '--color-chart-axis-line-light')
                    .trim(),
                gridColor: getComputedStyle(el).getPropertyValue('--color-chart-grid-split-line').trim(),
                tooltipBg: getComputedStyle(el).getPropertyValue('--color-tooltip-background').trim(),
                tooltipBorder: getComputedStyle(el).getPropertyValue('--color-tooltip-border').trim(),
                tooltipTitle: getComputedStyle(el).getPropertyValue('--color-tooltip-title').trim(),
                tooltipBody: getComputedStyle(el).getPropertyValue('--color-tooltip-body').trim(),
                warningColor: getComputedStyle(el).getPropertyValue('--color-chart-warning').trim(),
                warningBgColor: getComputedStyle(el).getPropertyValue('--color-chart-warning-bg').trim(),
                successColor: getComputedStyle(el).getPropertyValue('--color-chart-success').trim(),
                successBgColor: getComputedStyle(el).getPropertyValue('--color-chart-success-bg').trim(),
                hoverLineColor: getComputedStyle(el)
                    .getPropertyValue(isDarkMode ? '--color-chart-grid-line-light' : '--color-chart-axis-line-light')
                    .trim(),
            };
        };

        updateColors();

        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.attributeName === 'class') {
                    updateColors();
                }
            }
        });
        observer.observe(el, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    });

    const allRatingsData = $derived.by(() => {
        if (!ratingStats.monthly_trend) {
            return { labels: [] as string[], datasets: [] };
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
                    borderColor: colors.warningColor,
                    backgroundColor: colors.warningBgColor,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
            ],
        };
    });

    const listedGamesData = $derived.by(() => {
        if (!ratingStats.visible_games_monthly_trend) {
            return { labels: [] as string[], datasets: [] };
        }
        const labels = ratingStats.visible_games_monthly_trend.map((d) => {
            const date = new Date(d.month);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                year: 'numeric',
            });
        });
        const data = ratingStats.visible_games_monthly_trend.map((d) => d.count);
        return {
            labels,
            datasets: [
                {
                    label: 'Listed Games Ratings',
                    data,
                    borderColor: colors.successColor,
                    backgroundColor: colors.successBgColor,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
            ],
        };
    });

    const chartOptions = $derived({
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
                backgroundColor: colors.tooltipBg,
                borderColor: colors.tooltipBorder,
                borderWidth: 1,
                titleColor: colors.tooltipTitle,
                bodyColor: colors.tooltipBody,
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
                    color: colors.axisTextColor,
                },
                grid: {
                    display: true,
                    color: colors.gridColor,
                    drawTicks: false,
                    borderColor: colors.gridColor,
                    borderDash: [2, 2],
                },
            },
            y: {
                ticks: {
                    color: colors.axisTextColor,
                    precision: 0,
                },
                grid: {
                    display: true,
                    color: colors.gridColor,
                    borderDash: [3, 3],
                    drawBorder: false,
                },
            },
        },
    });

    const hoverLinePlugin: Plugin<'line'> = $derived({
        id: 'hoverLine',
        afterDatasetsDraw: (chart) => {
            const { ctx, tooltip, chartArea } = chart;
            if (!tooltip?.getActiveElements()?.length) return;
            const maybeCaretX = (tooltip as unknown as Record<string, unknown>).caretX as number | undefined;
            const x = maybeCaretX;
            if (!x || x < chartArea.left || x > chartArea.right) return;

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(x, chartArea.top);
            ctx.lineTo(x, chartArea.bottom);
            ctx.lineWidth = 1;
            ctx.strokeStyle = colors.hoverLineColor;
            ctx.stroke();
            ctx.restore();
        },
    });

    $effect(() => {
        ChartJS.register(hoverLinePlugin);
    });
</script>

<!-- All Ratings Trend -->
<div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
    <div class="space-y-8">
        <div>
            <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">All Ratings Trend</h2>
            <div class="relative h-[240px] w-full">
                <Chart data={allRatingsData} options={chartOptions} style="height: 240px; width: 100%" />
            </div>
        </div>
    </div>
</div>

<!-- Listed Games Ratings Trend -->
<div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
    <div class="space-y-8">
        <div>
            <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Listed Games Ratings Trend</h2>
            <div class="relative h-[240px] w-full">
                <Chart data={listedGamesData} options={chartOptions} style="height: 240px; width: 100%" />
            </div>
        </div>
    </div>
</div>
