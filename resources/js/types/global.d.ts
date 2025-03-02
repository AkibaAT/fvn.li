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
    }
}

export {};
