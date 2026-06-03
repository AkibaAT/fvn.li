import { createInertiaApp } from '@inertiajs/svelte';
import type { ResolvedComponent } from '@inertiajs/svelte';
import createServer from '@inertiajs/svelte/server';
import { render } from 'svelte/server';
import { route as ziggyRoute } from 'ziggy-js';
import type { Config as ZiggyConfig } from 'ziggy-js';
import PersistentLayout from '@/layouts/PersistentLayout.svelte';

const appName = import.meta.env.VITE_APP_NAME || 'FVN.li';

type SharedZiggyConfig = ZiggyConfig & {
    location?: string | URL | ZiggyConfig['location'];
};

type ZiggyGlobal = typeof globalThis & {
    Ziggy?: ZiggyConfig;
    ziggy?: ZiggyConfig;
    route?: typeof ziggyRoute;
};

function normalizeZiggyConfig(ziggy: SharedZiggyConfig): ZiggyConfig {
    return {
        ...ziggy,
        location:
            typeof ziggy.location === 'string'
                ? new URL(ziggy.location, ziggy.url)
                : ziggy.location,
    };
}

function installZiggyGlobals(ziggy?: SharedZiggyConfig): void {
    if (!ziggy) {
        return;
    }

    const config = normalizeZiggyConfig(ziggy);
    const ziggyGlobal = globalThis as ZiggyGlobal;

    ziggyGlobal.Ziggy = config;
    ziggyGlobal.ziggy = config;
    ziggyGlobal.route = ((name?: string, params?: unknown, absolute?: boolean, customConfig?: ZiggyConfig) =>
        ziggyRoute(name as never, params as never, absolute, customConfig ?? config)) as typeof ziggyRoute;
}

createServer((page) => {
    installZiggyGlobals(page.props?.ziggy as SharedZiggyConfig | undefined);

    return createInertiaApp({
        page,
        resolve: (name) => {
            const pages = import.meta.glob<ResolvedComponent>(
                './pages/**/*.svelte',
                { eager: true },
            );
            const resolved = pages[`./pages/${name}.svelte`] as any;

            return { ...resolved, layout: resolved?.layout || PersistentLayout };
        },
        title: (title) => (title ? `${title} - ${appName}` : appName),
        setup({ App, props }) {
            return render(App, { props });
        },
    });
});
