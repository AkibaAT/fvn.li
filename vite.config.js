import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { visualizer } from 'rollup-plugin-visualizer';
import path from "node:path";
import { copyFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';

export default defineConfig(({ command, mode }) => ({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
                'resources/js/tinymce-entry.ts',
                'resources/js/charts-entry.ts',
                'resources/js/game-analytics-entry.ts',
                'resources/js/push-notifications.js',
                'resources/js/list-buttons.ts',
                'resources/js/toggle-notifications.ts',
                'resources/js/screenshots-lightbox.ts'
            ],
            refresh: true,
        }),
        visualizer({ gzipSize: true, brotliSize: true }),
        // Plugin to copy TinyMCE assets
        {
            name: 'copy-tinymce-assets',
            buildStart() {
                // Copy TinyMCE skins and other assets to public directory
                this.addWatchFile('node_modules/tinymce/skins');
            },
            generateBundle() {
                // This will copy TinyMCE assets during build
                const copyTinyMCEAssets = async () => {
                    const srcDir = 'node_modules/tinymce';
                    const destDir = 'public/assets/tinymce';
                    
                    if (!existsSync(destDir)) {
                        await mkdir(destDir, { recursive: true });
                    }
                    
                    // Copy skins
                    if (!existsSync(`${destDir}/skins`)) {
                        await mkdir(`${destDir}/skins`, { recursive: true });
                    }
                };
                
                return copyTinyMCEAssets();
            }
        }
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
