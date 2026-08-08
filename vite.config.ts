import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        svelte(),
    ],
    resolve: {
        alias: [
            {
                find: /~(.+)/,
                replacement: resolve(import.meta.dirname, 'node_modules/$1'),
            },
            {
                find: 'ziggy-js',
                replacement: resolve(import.meta.dirname, 'vendor/tightenco/ziggy'),
            },
        ],
    },
    server: {
        host: '0.0.0.0',
        strictPort: true,
        port: 5273,
        watch: {
            ignored: ['**/.idea/**', '**/bootstrap/cache/**', '**/public/assets/tinymce/**', '**/storage/**', '**/vendor/**'],
        },
        hmr: {
            protocol: 'wss',
            host: `${process.env.DDEV_SITENAME}.${process.env.DDEV_TLD}`,
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('/node_modules/chart.js/')) {
                        return 'chart';
                    }
                },
            },
        },
    },
});
