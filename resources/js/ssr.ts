import {createInertiaApp} from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import {renderToString} from '@vue/server-renderer';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {createSSRApp, DefineComponent, h} from 'vue';
import {ZiggyVue, Config} from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
            ),
        setup({App, props, plugin}) {
            const ziggyConfig: Config = {
                ...page.props.ziggy,
                location: page.props.ziggy.location,
                // Set port to null if it's undefined
                port: page.props.ziggy.port ?? null
            };

            return createSSRApp({render: () => h(App, props)})
                .use(plugin)
                .use(ZiggyVue, ziggyConfig);
        },
    }),
);
