import { AxiosInstance } from 'axios';

declare global {
    interface Window {
        axios: AxiosInstance;
    }
}

import type { MonthlyTrendData } from './system';

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
