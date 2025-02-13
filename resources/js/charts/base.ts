import * as echarts from 'echarts/core';
import {
    TitleComponent,
    TooltipComponent,
    GridComponent,
    DataZoomComponent
} from 'echarts/components';
import { UniversalTransition } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';
import {MonthlyTrendData} from "@/types/system";

export const baseComponents = [
    TitleComponent,
    TooltipComponent,
    GridComponent,
    DataZoomComponent,
    UniversalTransition,
    CanvasRenderer
];

export { echarts };

export interface ChartOptions {
    lineColor?: string;
    areaColor?: string;
    textColor?: string;
    gridColor?: string;
    title?: string;
}

export function getBaseChartOptions(data: MonthlyTrendData[], options: ChartOptions = {}) {
    const {
        lineColor = '#EAB308',
        areaColor = 'rgba(234, 179, 8, 0.1)',
        textColor = '#6B7280',
        gridColor = '#E5E7EB',
        title
    } = options;

    const dates = data.map(item => {
        const date = new Date(item.month);
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    const values = data.map(item => item.count);

    return {
        title: title ? {
            text: title,
            textStyle: {
                color: textColor,
                fontSize: 16,
                fontWeight: 'normal'
            },
            left: 'center',
            top: 0,
            padding: [0, 0, 20, 0]
        } : undefined,
        tooltip: {
            trigger: 'axis',
            formatter: '{b}: {c} ratings',
            backgroundColor: 'rgba(255, 255, 255, 0.9)',
            borderColor: gridColor,
            textStyle: {
                color: textColor
            }
        },
        grid: {
            left: '2%',
            right: '2%',
            bottom: '20%',
            top: '5%',
            containLabel: true
        },
        dataZoom: [{
            show: true,
            start: 0,
            end: 100,
            height: 30,
            bottom: 10,
            borderColor: gridColor,
            backgroundColor: 'rgba(255, 255, 255, 0.1)',
            fillerColor: areaColor,
            handleStyle: {
                color: lineColor,
                borderColor: lineColor
            },
            textStyle: {
                color: textColor
            },
            moveHandleStyle: {
                color: lineColor
            },
            selectedDataBackground: {
                lineStyle: {
                    color: lineColor
                },
                areaStyle: {
                    color: areaColor
                }
            }
        }],
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: dates,
            axisLabel: {
                color: textColor,
                rotate: 45
            },
            axisLine: {
                lineStyle: {
                    color: gridColor
                }
            },
            splitLine: {
                show: false
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                color: textColor,
                formatter: '{value}'
            },
            axisLine: {
                show: false
            },
            splitLine: {
                lineStyle: {
                    color: gridColor,
                    type: 'dashed'
                }
            }
        },
        series: [
            {
                name: 'Ratings',
                type: 'line',
                smooth: true,
                data: values,
                itemStyle: {
                    color: lineColor
                },
                lineStyle: {
                    width: 2,
                    color: lineColor
                },
                areaStyle: {
                    color: areaColor
                },
                symbol: 'circle',
                symbolSize: 6
            }
        ]
    };
}
