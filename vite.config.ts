import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {resolve} from 'node:path';
import {defineConfig} from 'vite';
import {visualizer} from 'rollup-plugin-visualizer';
import {cp, mkdir} from 'node:fs/promises';
import {existsSync} from 'node:fs';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx'
            ],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        visualizer({gzipSize: true, brotliSize: true}),
        // Copy TinyMCE assets to public so base_url works (core, plugins, skins, themes, icons, models)
        {
            name: 'copy-tinymce-assets',
            async buildStart() {
                this.addWatchFile('node_modules/tinymce/skins');
                this.addWatchFile('node_modules/tinymce/plugins');
                // Also copy on dev start so /assets/tinymce is available
                const srcDir = 'node_modules/tinymce';
                const destDir = 'public/assets/tinymce';
                if (!existsSync(destDir)) {
                    await mkdir(destDir, {recursive: true});
                }
                // Core script
                await cp(`${srcDir}/tinymce.min.js`, `${destDir}/tinymce.min.js`).catch(() => {
                });
                await cp(`${srcDir}/skins`, `${destDir}/skins`, {recursive: true}).catch(() => {
                });
                await cp(`${srcDir}/models`, `${destDir}/models`, {recursive: true}).catch(() => {
                });
                await cp(`${srcDir}/themes`, `${destDir}/themes`, {recursive: true}).catch(() => {
                });
                await cp(`${srcDir}/icons`, `${destDir}/icons`, {recursive: true}).catch(() => {
                });
                await cp(`${srcDir}/plugins`, `${destDir}/plugins`, {recursive: true}).catch(() => {
                });
            },
            async generateBundle() {
                const srcDir = 'node_modules/tinymce';
                const destDir = 'public/assets/tinymce';
                if (!existsSync(destDir)) {
                    await mkdir(destDir, {recursive: true});
                }
                // Copy all required assets
                await cp(`${srcDir}/tinymce.min.js`, `${destDir}/tinymce.min.js`).catch(() => {
                });
                await cp(`${srcDir}/skins`, `${destDir}/skins`, {recursive: true});
                await cp(`${srcDir}/models`, `${destDir}/models`, {recursive: true});
                await cp(`${srcDir}/themes`, `${destDir}/themes`, {recursive: true});
                await cp(`${srcDir}/icons`, `${destDir}/icons`, {recursive: true});
                await cp(`${srcDir}/plugins`, `${destDir}/plugins`, {recursive: true});
            }
        }
    ],
    esbuild: {
        jsx: 'automatic',
    },
    resolve: {
        alias: [
            {
                find: /~(.+)/,
                replacement: resolve(__dirname, 'node_modules/$1')
            },
            {
                find: 'ziggy-js',
                replacement: resolve(__dirname, 'vendor/tightenco/ziggy')
            }
        ]
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
            host: `${process.env.DDEV_SITENAME}.${process.env.DDEV_TLD}`
        }
    }
});
