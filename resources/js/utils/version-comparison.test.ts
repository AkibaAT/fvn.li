import { describe, expect, test } from 'vitest';
import { formatBytesDiff, formatDiff, getDiffColor } from './version-comparison';

describe('version comparison formatting', () => {
    test('signs byte differences in both directions', () => {
        expect(formatBytesDiff(2048)).toBe('+2 KB');
        expect(formatBytesDiff(-2048)).toBe('-2 KB');
        expect(formatBytesDiff(0)).toBe('-');
    });

    test('signs count differences', () => {
        expect(formatDiff(1200)).toBe(`+${(1200).toLocaleString()}`);
        expect(formatDiff(0)).toBe('-');
    });

    test('pairs each diff colour with a light and dark variant', () => {
        expect(getDiffColor(1)).toBe('text-green-700 dark:text-green-400');
        expect(getDiffColor(-1)).toBe('text-red-700 dark:text-red-400');
        expect(getDiffColor(0)).toBe('text-fg-faint');
    });
});
