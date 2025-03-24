import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { visualizer } from 'rollup-plugin-visualizer';
import path from "node:path";

export default defineConfig(({ command, mode }) => ({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
                'resources/js/charts-entry.ts',
                'resources/js/push-notifications.js'
            ],
            refresh: true,
        }),
        visualizer({ gzipSize: true, brotliSize: true })
    ],
    resolve: {
        alias: [
            {
                find: /~(.+)/,
                replacement: path.join(process.cwd(), 'node_modules/$1')
            }
        ]
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: (id) => {
                    if (id.includes('node_modules')) {
                        if (id.includes('echarts')) {
                            return 'echarts';
                        }
                    }
                },
            },
        },
    },
    server: {
        // respond to all hosts
        host: '0.0.0.0',
        strictPort: true,
        port: 5173,
        hmr: {
            // Force the Vite client to connect via SSL
            // This will also force a "https://" URL in the public/hot file
            protocol: 'wss',
            // The host where the Vite dev server can be accessed
            // This will also force this host to be written to the public/hot file
            host: `${process.env.DDEV_HOSTNAME}`
        }
    }
}));
