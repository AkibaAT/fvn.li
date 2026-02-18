import {createInertiaApp} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import {type Config, route, type RouteName} from 'ziggy-js';
import PersistentLayout from './layouts/PersistentLayout';

const appName = import.meta.env.VITE_APP_NAME || 'FVN.li';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) => {
            return resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob('./pages/**/*.tsx'),
            ).then((page) => {
                // Force all pages to use persistent layout (same as app.tsx)
                (page as any).default.layout = (pageContent: React.ReactNode) => <PersistentLayout children={pageContent} />;
                return page;
            });
        },
        setup: ({App, props}) => {
            type ZiggyName = Parameters<typeof route>[0];
            type ZiggyParams = Parameters<typeof route>[1];

            const ziggy = page.props.ziggy as Config & { location: string };

            // @ts-expect-error: Assigning ziggy route helper to global for SSR environment
            global.route<RouteName> = (name, params, absolute) =>
                route(name as ZiggyName, params as ZiggyParams, absolute, {
                    ...ziggy,
                    location: new URL(ziggy.location),
                });

            // Create a new QueryClient per request to avoid shared state between SSR requests
            const queryClient = new QueryClient();

            return (
                <QueryClientProvider client={queryClient}>
                    <App {...props} />
                </QueryClientProvider>
            );
        },
    }),
);
