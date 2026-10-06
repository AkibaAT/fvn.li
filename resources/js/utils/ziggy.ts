import { route as ziggyRoute } from 'ziggy-js';
import type { Config as ZiggyConfig } from 'ziggy-js';

export type SharedZiggyConfig = ZiggyConfig & {
    location?: string | URL | ZiggyConfig['location'];
};

type ZiggyGlobal = typeof globalThis & {
    Ziggy?: ZiggyConfig;
    ziggy?: ZiggyConfig;
    route?: typeof ziggyRoute;
};

export function installZiggyGlobals(ziggy: SharedZiggyConfig | undefined, { pinLocation }: { pinLocation: boolean }): void {
    if (!ziggy) {
        return;
    }

    const { location, ...routes } = ziggy;
    const config: ZiggyConfig = pinLocation
        ? { ...routes, location: typeof location === 'string' ? new URL(location, ziggy.url) : location }
        : routes;
    const ziggyGlobal = globalThis as ZiggyGlobal;

    ziggyGlobal.Ziggy = config;
    ziggyGlobal.ziggy = config;
    ziggyGlobal.route = ((name?: string, params?: unknown, absolute?: boolean, customConfig?: ZiggyConfig) =>
        ziggyRoute(name as never, params as never, absolute, customConfig ?? config)) as typeof ziggyRoute;
}
