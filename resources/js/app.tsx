import axios from 'axios';
import '../css/app.css';

import {initializeAppearance} from '@/hooks/use-appearance';
import {createInertiaApp} from '@inertiajs/react';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {createRoot} from 'react-dom/client';
import PersistentLayout from './layouts/PersistentLayout';

const appName = import.meta.env.VITE_APP_NAME || 'FVN.li';

// Initialize a global axios instance configured for Laravel & Inertia
if (typeof window !== 'undefined') {
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    const csrf =
        typeof document !== 'undefined'
            ? document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content')
            : undefined;
    if (csrf) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
    }
    // Expose to window for code using window.axios
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (window as any).axios = axios;
}

interface PageComponent {
    default: {
        layout?: ((pageContent: React.ReactNode) => React.ReactNode) | undefined;
    };
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        return resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ).then((page) => {
            // Force all pages to use persistent layout
            (page as PageComponent).default.layout = (pageContent: React.ReactNode) => <PersistentLayout children={pageContent} />;
            return page;
        });
    },
    setup({el, App, props}) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: 'var(--color-progress-primary)',
    },
});

// Initialize appearance after hydration (preload script in Blade prevents initial flash)
initializeAppearance();
