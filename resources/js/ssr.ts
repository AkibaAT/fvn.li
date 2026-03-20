import { createInertiaApp } from '@inertiajs/svelte';
import type { ResolvedComponent } from '@inertiajs/svelte';
import createServer from '@inertiajs/svelte/server';
import { render } from 'svelte/server';
import PersistentLayout from '@/layouts/PersistentLayout.svelte';

const appName = import.meta.env.VITE_APP_NAME || 'FVN.li';

createServer((page) =>
    createInertiaApp({
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
    }),
);
