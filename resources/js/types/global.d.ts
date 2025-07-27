import {AxiosInstance} from 'axios';
import type {MonthlyTrendData} from './system';

declare global {
    interface Window {
        axios: AxiosInstance;
    }
}

declare global {
    interface Window {
        initializeTrendChart?: (
            element: HTMLElement,
            data: MonthlyTrendData[],
            options?: {
                lineColor?: string;
                areaColor?: string;
            }
        ) => void;
        chartInitialized?: Promise<void>;
        initializeMultiSeriesChart?: (
            element: HTMLElement,
            series: Array<{name: string, data: any[], color: string}>,
            options?: any
        ) => any;
    }
}

export {};
