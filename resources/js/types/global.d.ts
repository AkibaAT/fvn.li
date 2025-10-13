import {AxiosInstance} from 'axios';
import type {route as routeFn} from 'ziggy-js';
import type {MonthlyTrendData} from './system';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    // Global Ziggy route helper available in browser and SSR

    var route: typeof routeFn;
}

declare global {
    interface Window {
        initializeTrendChart?: (
            element: HTMLElement,
            data: MonthlyTrendData[],
            options?: {
                lineColor?: string;
                areaColor?: string;
            },
        ) => void;
        chartInitialized?: Promise<void>;
        initializeMultiSeriesChart?: (
            element: HTMLElement,
            series: Array<{ name: string; data: unknown[]; color: string }>,
            options?: Record<string, unknown>,
        ) => unknown;
    }
}

export {};
