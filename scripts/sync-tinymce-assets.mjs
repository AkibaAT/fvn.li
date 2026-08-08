import { access, cp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const runtimeDirectories = ['skins', 'models', 'themes', 'icons', 'plugins'];
const markerFilename = '.source-version';

async function pathExists(path) {
    try {
        await access(path);
        return true;
    } catch (error) {
        if (error?.code === 'ENOENT') return false;
        throw error;
    }
}

export async function syncTinyMceAssets({
    sourceDir = resolve(import.meta.dirname, '../node_modules/tinymce'),
    destinationDir = resolve(import.meta.dirname, '../public/assets/tinymce'),
} = {}) {
    const packageMetadata = JSON.parse(await readFile(join(sourceDir, 'package.json'), 'utf8'));
    const sourceVersion = packageMetadata.version;
    const markerPath = join(destinationDir, markerFilename);
    const corePath = join(destinationDir, 'tinymce.min.js');
    const existingVersion = await readFile(markerPath, 'utf8').catch((error) => {
        if (error?.code === 'ENOENT') return null;
        throw error;
    });

    const requiredPaths = [corePath, ...runtimeDirectories.map((directory) => join(destinationDir, directory))];
    const assetsComplete = (await Promise.all(requiredPaths.map(pathExists))).every(Boolean);

    if (existingVersion?.trim() === sourceVersion && assetsComplete) {
        return { copied: false, version: sourceVersion };
    }

    await rm(destinationDir, { recursive: true, force: true });
    await mkdir(destinationDir, { recursive: true });
    await cp(join(sourceDir, 'tinymce.min.js'), corePath);

    for (const directory of runtimeDirectories) {
        await cp(join(sourceDir, directory), join(destinationDir, directory), {
            recursive: true,
            filter: (source) => !source.endsWith('.ts') && !source.endsWith('.map'),
        });
    }

    await writeFile(markerPath, `${sourceVersion}\n`, 'utf8');

    return { copied: true, version: sourceVersion };
}

if (process.argv[1] && pathToFileURL(resolve(process.argv[1])).href === import.meta.url) {
    const result = await syncTinyMceAssets();
    console.log(result.copied ? `Synced TinyMCE ${result.version} browser assets.` : `TinyMCE ${result.version} browser assets are current.`);
}
