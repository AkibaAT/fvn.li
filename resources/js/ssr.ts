import { createInertiaApp } from '@inertiajs/svelte';
import type { ResolvedComponent } from '@inertiajs/svelte';
import createServer from '@inertiajs/svelte/server';
import { render } from 'svelte/server';
import AppFrame from '@/layouts/AppFrame.svelte';
import { installZiggyGlobals } from '@/utils/ziggy';
import type { SharedZiggyConfig } from '@/utils/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'FVN.li';

createServer((page) => {
    installZiggyGlobals(page.props?.ziggy as SharedZiggyConfig | undefined, { pinLocation: true });

    return createInertiaApp({
        page,
        resolve: (name) => {
            const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.svelte', { eager: true });
            const resolved = pages[`./pages/${name}.svelte`] as any;

            return { ...resolved, layout: resolved?.layout || AppFrame };
        },
        title: (title) => (title ? `${title} - ${appName}` : appName),
        setup({ App, props }) {
            return render(App, { props });
        },
    });
});
