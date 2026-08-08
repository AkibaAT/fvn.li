import { mkdir, mkdtemp, readFile, rm, stat, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { syncTinyMceAssets } from '../../../scripts/sync-tinymce-assets.mjs';

const temporaryDirectories: string[] = [];

async function createFixture(): Promise<{ destinationDir: string; sourceDir: string }> {
    const root = await mkdtemp(join(tmpdir(), 'tinymce-assets-'));
    temporaryDirectories.push(root);
    const sourceDir = join(root, 'source');
    const destinationDir = join(root, 'public');

    await mkdir(sourceDir, { recursive: true });
    await writeFile(join(sourceDir, 'package.json'), JSON.stringify({ version: '8.8.2' }));
    await writeFile(join(sourceDir, 'tinymce.min.js'), 'runtime');

    for (const directory of ['skins', 'models', 'themes', 'icons', 'plugins']) {
        await mkdir(join(sourceDir, directory), { recursive: true });
        await writeFile(join(sourceDir, directory, 'runtime.js'), directory);
        await writeFile(join(sourceDir, directory, 'source.ts'), 'not a browser asset');
    }

    return { destinationDir, sourceDir };
}

afterEach(async () => {
    await Promise.all(temporaryDirectories.splice(0).map((directory) => rm(directory, { recursive: true, force: true })));
});

describe('TinyMCE asset synchronization', () => {
    it('copies browser assets without publishing TypeScript sources', async () => {
        const paths = await createFixture();

        await expect(syncTinyMceAssets(paths)).resolves.toEqual({ copied: true, version: '8.8.2' });
        await expect(readFile(join(paths.destinationDir, 'skins/runtime.js'), 'utf8')).resolves.toBe('skins');
        await expect(readFile(join(paths.destinationDir, 'skins/source.ts'), 'utf8')).rejects.toMatchObject({ code: 'ENOENT' });
    });

    it('does not touch current assets on subsequent runs', async () => {
        const paths = await createFixture();
        await syncTinyMceAssets(paths);
        const corePath = join(paths.destinationDir, 'tinymce.min.js');
        const initialModifiedAt = (await stat(corePath)).mtimeMs;

        await expect(syncTinyMceAssets(paths)).resolves.toEqual({ copied: false, version: '8.8.2' });
        expect((await stat(corePath)).mtimeMs).toBe(initialModifiedAt);
    });
});
