import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { existsSync } from 'node:fs';
import { cp, mkdir } from 'node:fs/promises';
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
        // Copy TinyMCE assets to public so base_url works (core, plugins, skins, themes, icons, models)
        {
            name: 'copy-tinymce-assets',
            async buildStart() {
                this.addWatchFile('node_modules/tinymce/skins');
                this.addWatchFile('node_modules/tinymce/plugins');
                const srcDir = 'node_modules/tinymce';
                const destDir = 'public/assets/tinymce';
                if (!existsSync(destDir)) {
                    await mkdir(destDir, { recursive: true });
                }
                await cp(`${srcDir}/tinymce.min.js`, `${destDir}/tinymce.min.js`).catch(() => {});
                await cp(`${srcDir}/skins`, `${destDir}/skins`, { recursive: true }).catch(() => {});
                await cp(`${srcDir}/models`, `${destDir}/models`, { recursive: true }).catch(() => {});
                await cp(`${srcDir}/themes`, `${destDir}/themes`, { recursive: true }).catch(() => {});
                await cp(`${srcDir}/icons`, `${destDir}/icons`, { recursive: true }).catch(() => {});
                await cp(`${srcDir}/plugins`, `${destDir}/plugins`, { recursive: true }).catch(() => {});
            },
            async generateBundle() {
                const srcDir = 'node_modules/tinymce';
                const destDir = 'public/assets/tinymce';
                if (!existsSync(destDir)) {
                    await mkdir(destDir, { recursive: true });
                }
                await cp(`${srcDir}/tinymce.min.js`, `${destDir}/tinymce.min.js`).catch(() => {});
                await cp(`${srcDir}/skins`, `${destDir}/skins`, { recursive: true });
                await cp(`${srcDir}/models`, `${destDir}/models`, { recursive: true });
                await cp(`${srcDir}/themes`, `${destDir}/themes`, { recursive: true });
                await cp(`${srcDir}/icons`, `${destDir}/icons`, { recursive: true });
                await cp(`${srcDir}/plugins`, `${destDir}/plugins`, { recursive: true });
            },
        },
    ],
    resolve: {
        alias: [
            {
                find: /~(.+)/,
                replacement: resolve(__dirname, 'node_modules/$1'),
            },
            {
                find: 'ziggy-js',
                replacement: resolve(__dirname, 'vendor/tightenco/ziggy'),
            },
        ],
    },
    server: {
        host: '0.0.0.0',
        strictPort: true,
        port: 5273,
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
                    if (id.includes('/node_modules/elkjs/')) {
                        return 'elk';
                    }
                },
            },
        },
    },
});
