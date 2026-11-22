import type { Config, route as routeFn } from 'ziggy-js';

declare global {
    // Global Ziggy route helper available in browser and SSR
    var route: typeof routeFn;
    var Ziggy: Config;
    var ziggy: Config;
}

export {};
