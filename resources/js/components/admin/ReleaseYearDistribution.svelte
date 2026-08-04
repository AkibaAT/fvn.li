<script lang="ts">
    import { Card } from '@/components/ui';
    import { onMount } from 'svelte';
    import type { TooltipItem } from 'chart.js';

    type ChartComponentType = typeof import('@/components/charts/Chart.svelte').default;

    interface YearDistributionData {
        year: number;
        count: number;
    }

    interface ReleaseYearStats {
        year_distribution: YearDistributionData[];
    }

    let { releaseYearStats }: { releaseYearStats: ReleaseYearStats } = $props();
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
                hoverLineColor: isDarkMode ? 'rgba(203, 213, 225, 0.5)' : 'rgba(71, 85, 105, 0.5)',
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

    const chartData = $derived.by(() => {
        if (!releaseYearStats.year_distribution) {
            return { labels: [] as string[], datasets: [] };
        }
        const labels = releaseYearStats.year_distribution.map((d) => d.year.toString());
        const data = releaseYearStats.year_distribution.map((d) => d.count);
        return {
            labels,
            datasets: [
                {
                    label: 'Games Released',
                    data,
                    borderColor: colors.primaryColor,
                    backgroundColor: colors.primaryBgColor,
                    borderWidth: 1,
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
                    title: (items: TooltipItem<'bar'>[]) => {
                        return items[0]?.label ?? '';
                    },
                    label: (item: TooltipItem<'bar'>) => {
                        const val = new Intl.NumberFormat().format((item.parsed.y as number) ?? 0);
                        return `Games: ${val}`;
                    },
                },
            },
        },
        scales: {
            x: {
                ticks: {
                    color: colors.axisTextColor,
                    maxRotation: 45,
                    minRotation: 45,
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

    onMount(() => {
        void import('@/components/charts/Chart.svelte').then((module) => {
            ChartComponent = module.default;
        });
    });
</script>

<section class="space-y-6">
    <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Release years</h2>

    <Card variant="outline" class="border-gray-200 shadow-none dark:border-gray-700 dark:bg-gray-800/60">
        <h3 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Listed games by release year</h3>
        <div class="relative h-[300px] w-full">
            {#if ChartComponent}
                <ChartComponent type="bar" data={chartData} options={chartOptions} style="height: 300px; width: 100%" />
            {/if}
        </div>
    </Card>
</section>
