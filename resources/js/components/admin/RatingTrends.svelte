<script lang="ts">
    import { onMount } from 'svelte';
    import type { Plugin, TooltipItem } from 'chart.js';
    import type { MonthlyTrendData } from '@/types/system';
    import { Card } from '@/components/ui';

    type ChartComponentType = typeof import('@/components/charts/Chart.svelte').default;

    interface RatingStats {
        monthly_trend: MonthlyTrendData[];
        visible_games_monthly_trend: MonthlyTrendData[];
    }

    let { ratingStats }: { ratingStats: RatingStats } = $props();
    let ChartComponent = $state<ChartComponentType | null>(null);

    let colors = $state({
        axisTextColor: '',
        gridColor: '',
        tooltipBg: '',
        tooltipBorder: '',
        tooltipTitle: '',
        tooltipBody: '',
        primaryColor: '',
        primaryBgColor: '',
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
                primaryColor: getComputedStyle(el).getPropertyValue('--color-chart-primary').trim(),
                primaryBgColor: getComputedStyle(el).getPropertyValue('--color-chart-primary-bg').trim(),
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
                    borderColor: colors.primaryColor,
                    backgroundColor: colors.primaryBgColor,
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
                    borderColor: colors.primaryColor,
                    backgroundColor: colors.primaryBgColor,
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

    const chartPlugins = $derived([hoverLinePlugin]);

    onMount(() => {
        void import('@/components/charts/Chart.svelte').then((module) => {
            ChartComponent = module.default;
        });
    });
</script>

<section class="space-y-6">
    <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Rating history</h2>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <Card variant="outline" padding="lg" class="border-gray-200 shadow-none dark:border-gray-700 dark:bg-gray-800/60">
            <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">All ratings</h3>
            <div class="relative h-[240px] w-full">
                {#if ChartComponent}
                    <ChartComponent data={allRatingsData} options={chartOptions} plugins={chartPlugins} style="height: 240px; width: 100%" />
                {/if}
            </div>
        </Card>

        <Card variant="outline" padding="lg" class="border-gray-200 shadow-none dark:border-gray-700 dark:bg-gray-800/60">
            <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Listed games</h3>
            <div class="relative h-[240px] w-full">
                {#if ChartComponent}
                    <ChartComponent data={listedGamesData} options={chartOptions} plugins={chartPlugins} style="height: 240px; width: 100%" />
                {/if}
            </div>
        </Card>
    </div>
</section>
