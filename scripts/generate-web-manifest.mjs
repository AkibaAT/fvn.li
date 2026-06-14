import { copyFile, stat, writeFile } from 'node:fs/promises';
import prettier from 'prettier';

const iconVersion = Math.max((await stat('public/icon-192.png')).mtimeMs, (await stat('public/icon-512.png')).mtimeMs);

const manifest = {
    name: 'FVN.li - Visual Novel Database',
    short_name: 'FVN.li',
    description: 'Track, discover, and review visual novels',
    start_url: '/',
    display: 'standalone',
    background_color: '#f8fafc',
    theme_color: '#3B82F6',
    orientation: 'any',
    categories: ['entertainment', 'games'],
    icons: [
        {
            src: `/icon-192.png?v=${Math.trunc(iconVersion)}`,
            sizes: '192x192',
            type: 'image/png',
            purpose: 'any maskable',
        },
        {
            src: `/icon-512.png?v=${Math.trunc(iconVersion)}`,
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any maskable',
        },
    ],
};

const prettierOptions = {
    ...((await prettier.resolveConfig('public/manifest.json')) ?? {}),
    filepath: 'public/manifest.json',
};
const formattedManifest = await prettier.format(JSON.stringify(manifest), prettierOptions);

await writeFile('public/manifest.json', formattedManifest);
await copyFile('public/manifest.json', 'public/site.webmanifest');
