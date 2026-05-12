import '../css/app.css';
import { createInertiaApp } from '@inertiajs/svelte';
import { hydrate, mount } from 'svelte';
import { initializeAppearance } from '@/hooks/use-appearance.svelte';
import AppFrame from '@/layouts/AppFrame.svelte';

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob('./pages/**/*.svelte', {
            import: 'default',
        });

        const page = (await pages[`./pages/${name}.svelte`]?.()) as any;

        // Apply the app frame to all pages by default
        if (!page) {
            throw new Error(`Unknown Inertia page: ${name}`);
        }

        return {
            default: page,
            layout: page.layout || AppFrame,
        };
    },
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        if (el.dataset.serverRendered === 'true') {
            hydrate(App, { target: el, props });
        } else {
            mount(App, { target: el, props });
        }
    },
    progress: {
        color: 'var(--color-progress-primary)',
    },
});

initializeAppearance();
