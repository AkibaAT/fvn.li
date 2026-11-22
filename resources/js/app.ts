import '../css/app.css';
import type { ResolvedComponent } from '@inertiajs/svelte';
import { createInertiaApp } from '@inertiajs/svelte';
import { hydrate, mount } from 'svelte';
import { initializeAppearance } from '@/hooks/use-appearance.svelte';
import AppFrame from '@/layouts/AppFrame.svelte';
import { trackPreviousUrl } from '@/utils/page-view';
import { revealInlineSpoiler } from '@/utils/review-spoilers';
import { installZiggyGlobals } from '@/utils/ziggy';
import type { SharedZiggyConfig } from '@/utils/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'FVN.li';
const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.svelte');

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const loader = pages[`./pages/${name}.svelte`];

        if (!loader) {
            throw new Error(`Unknown Inertia page: ${name}`);
        }

        const resolved = await loader();

        return {
            default: resolved.default,
            layout: resolved.layout || (AppFrame as ResolvedComponent['layout']),
        };
    },
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        installZiggyGlobals(props.initialPage.props.ziggy as SharedZiggyConfig | undefined, { pinLocation: false });

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
trackPreviousUrl();

document.addEventListener('click', (event) => {
    if (revealInlineSpoiler(event.target)) event.preventDefault();
});

document.addEventListener('keydown', (event) => {
    if ((event.key === 'Enter' || event.key === ' ') && revealInlineSpoiler(event.target)) event.preventDefault();
});
