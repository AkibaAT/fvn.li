import { describe, expect, test } from 'vitest';
import { normalizeScreenshots, resolveDeletedScreenshots, resolveUploadedScreenshots, type Screenshot } from './screenshotState';

const currentScreenshots: Screenshot[] = [
    { url: 'https://example.com/old-a.jpg' },
    { url: 'https://example.com/old-b.jpg' },
];

describe('screenshot state reconciliation', () => {
    test('normalizes object keyed screenshot payloads into arrays', () => {
        expect(
            normalizeScreenshots({
                0: { url: 'https://example.com/a.jpg' },
                1: { url: 'https://example.com/b.jpg' },
            }),
        ).toEqual([{ url: 'https://example.com/a.jpg' }, { url: 'https://example.com/b.jpg' }]);
    });

    test('uses complete upload response so the newly uploaded screenshot renders immediately', () => {
        const updatedScreenshots = resolveUploadedScreenshots(
            currentScreenshots,
            [...currentScreenshots, { url: 'https://example.com/new.jpg' }],
            [{ url: 'https://example.com/new.jpg' }],
        );

        expect(updatedScreenshots).toEqual([
            { url: 'https://example.com/old-a.jpg' },
            { url: 'https://example.com/old-b.jpg' },
            { url: 'https://example.com/new.jpg' },
        ]);
    });

    test('falls back to appending new upload payload when complete list is missing', () => {
        const updatedScreenshots = resolveUploadedScreenshots(currentScreenshots, null, [{ url: 'https://example.com/new.jpg' }]);

        expect(updatedScreenshots).toEqual([
            { url: 'https://example.com/old-a.jpg' },
            { url: 'https://example.com/old-b.jpg' },
            { url: 'https://example.com/new.jpg' },
        ]);
    });

    test('uses complete delete response so only the deleted screenshot disappears', () => {
        const updatedScreenshots = resolveDeletedScreenshots(currentScreenshots, 0, [{ url: 'https://example.com/old-b.jpg' }]);

        expect(updatedScreenshots).toEqual([{ url: 'https://example.com/old-b.jpg' }]);
    });

    test('falls back to local deletion when delete response payload is missing', () => {
        const updatedScreenshots = resolveDeletedScreenshots(currentScreenshots, 1, null);

        expect(updatedScreenshots).toEqual([{ url: 'https://example.com/old-a.jpg' }]);
    });
});
