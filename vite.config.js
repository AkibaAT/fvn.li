import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { visualizer } from 'rollup-plugin-visualizer';
import path from "node:path";

export default defineConfig(({ command, mode }) => ({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
            ],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
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
        // Only apply manual chunks for client build, not SSR
        rollupOptions: mode !== 'ssr' ? {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/echarts')) {
                        if (id.includes('/charts/') ||
                            id.includes('/components/') ||
                            id.includes('/features/') ||
                            id.includes('/renderers/')) {
                            return 'echarts-components';
                        }
                        return 'echarts';
                    }
                }
            },
        } : undefined,
        chunkSizeWarningLimit: 1000,
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
