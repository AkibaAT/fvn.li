import Chart from '@/components/charts/chart';
import type {Plugin, TooltipItem} from 'chart.js';
import React, {useEffect, useMemo, useState} from 'react';

interface YearDistributionData {
    year: number;
    count: number;
}

interface ReleaseYearStats {
    year_distribution: YearDistributionData[];
}

interface ReleaseYearDistributionProps {
    releaseYearStats: ReleaseYearStats;
}

const ReleaseYearDistribution: React.FC<ReleaseYearDistributionProps> = ({
    releaseYearStats,
}) => {
    const [isDark, setIsDark] = useState<boolean>(
        typeof document !== 'undefined'
            ? document.documentElement.classList.contains('dark')
            : false,
    );

    // Store computed styles in state to avoid SSR issues
    const [colors, setColors] = useState({
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

    useEffect(() => {
        if (typeof document === 'undefined') return;

        const el = document.documentElement;

        // Update colors when component mounts or dark mode changes
        const updateColors = () => {
            const isDarkMode = el.classList.contains('dark');
            setIsDark(isDarkMode);
            setColors({
                axisTextColor: getComputedStyle(el).getPropertyValue(
                    isDarkMode
                        ? '--color-chart-axis-line-dark'
                        : '--color-chart-axis-line-light',
                ).trim(),
                gridColor: getComputedStyle(el)
                    .getPropertyValue('--color-chart-grid-split-line')
                    .trim(),
                tooltipBg: getComputedStyle(el)
                    .getPropertyValue('--color-tooltip-background')
                    .trim(),
                tooltipBorder: getComputedStyle(el)
                    .getPropertyValue('--color-tooltip-border')
                    .trim(),
                tooltipTitle: getComputedStyle(el)
                    .getPropertyValue('--color-tooltip-title')
                    .trim(),
                tooltipBody: getComputedStyle(el)
                    .getPropertyValue('--color-tooltip-body')
                    .trim(),
                primaryColor: getComputedStyle(el)
                    .getPropertyValue('--color-chart-primary')
                    .trim(),
                primaryBgColor: getComputedStyle(el)
                    .getPropertyValue('--color-chart-primary-bg')
                    .trim(),
                hoverLineColor: isDarkMode
                    ? 'rgba(203, 213, 225, 0.5)'
                    : 'rgba(71, 85, 105, 0.5)',
            });
        };

        // Initial update
        updateColors();

        // Watch for dark mode changes
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.attributeName === 'class') {
                    updateColors();
                }
            }
        });
        observer.observe(el, {attributes: true, attributeFilter: ['class']});
        return () => observer.disconnect();
    }, []);

    const {
        axisTextColor,
        gridColor,
        tooltipBg,
        tooltipBorder,
        tooltipTitle,
        tooltipBody,
        primaryColor,
        primaryBgColor,
    } = colors;

    const chartData = useMemo(() => {
        if (!releaseYearStats.year_distribution) {
            return {labels: [], datasets: []};
        }
        const labels = releaseYearStats.year_distribution.map((d) =>
            d.year.toString(),
        );
        const data = releaseYearStats.year_distribution.map((d) => d.count);
        return {
            labels,
            datasets: [
                {
                    label: 'Games Released',
                    data,
                    borderColor: primaryColor,
                    backgroundColor: primaryBgColor,
                    borderWidth: 1,
                },
            ],
        };
    }, [releaseYearStats.year_distribution, primaryColor, primaryBgColor]);

    const chartOptions = useMemo(
        () => ({
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
                        title: (items: TooltipItem<'bar'>[]) => {
                            return items[0]?.label ?? '';
                        },
                        label: (item: TooltipItem<'bar'>) => {
                            const val = new Intl.NumberFormat().format(
                                (item.parsed.y as number) ?? 0,
                            );
                            return `Games: ${val}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: axisTextColor,
                        maxRotation: 45,
                        minRotation: 45,
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
        }),
        [
            axisTextColor,
            gridColor,
            tooltipBg,
            tooltipBorder,
            tooltipTitle,
            tooltipBody,
        ],
    );

    return (
        <div className="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
            <div className="space-y-8">
                <div>
                    <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Listed Games by Release Year
                    </h2>
                    <div className="relative h-[300px] w-full">
                        <Chart
                            type="bar"
                            data={chartData}
                            options={chartOptions}
                            style={{height: '300px', width: '100%'}}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ReleaseYearDistribution;
