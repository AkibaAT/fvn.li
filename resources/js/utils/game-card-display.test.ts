import { describe, expect, test } from 'vitest';

import { formatAuthorsInline, formatReleasedDate, formatReleaseDates, formatUpdatedDate, formatWordCount } from './game-card-display';

describe('game card display helpers', () => {
    test('formats the primary word count with an English secondary count', () => {
        expect(formatWordCount({ primary_word_count: 4128, primary_language_label: 'EN', english_word_count: 4128 })).toBe('4,128 words');
        expect(formatWordCount({ primary_word_count: 4128, primary_language_label: 'JA', english_word_count: 3900 })).toBe(
            '4,128 words (JA) · 3,900 EN',
        );
        expect(formatWordCount({ primary_word_count: null, primary_language_label: 'EN' })).toBe('Word count pending');
        expect(formatWordCount({ primary_word_count: null }, null)).toBeNull();
    });

    test('omits the updated half when it matches the release date', () => {
        expect(formatReleaseDates({ initially_published_at: '2026-09-14T00:00:00Z', latest_version_published_at: '2026-10-02T00:00:00Z' })).toMatch(
            /^Released .+ · Updated .+$/,
        );
        expect(formatReleaseDates({ initially_published_at: '2026-09-14T00:00:00Z', latest_version_published_at: '2026-09-14T00:00:00Z' })).toMatch(
            /^Released .+$/,
        );
        expect(formatReleaseDates({ initially_published_at: null, latest_version_published_at: '2026-10-02T00:00:00Z' })).toBeNull();
    });

    test('collapses author markup onto a single line', () => {
        expect(formatAuthorsInline('Ada Lovelace<br>Grace Hopper')).toBe('Ada Lovelace Grace Hopper');
        expect(formatAuthorsInline('Ada\n\nGrace')).toBe('Ada Grace');
        expect(formatAuthorsInline('  Ada   Grace  ')).toBe('Ada Grace');
        expect(formatAuthorsInline(undefined)).toBe('');
        expect(formatAuthorsInline('')).toBe('');
    });

    test('splits release and update dates for the row columns', () => {
        const game = { initially_published_at: '2026-09-14T00:00:00Z', latest_version_published_at: '2026-10-02T00:00:00Z' };

        expect(formatReleasedDate(game)).toMatch(/^\w+ \d+, \d{4}$/);
        expect(formatUpdatedDate(game)).toMatch(/^\w+ \d+, \d{4}$/);
        expect(formatUpdatedDate({ initially_published_at: '2026-09-14T00:00:00Z', latest_version_published_at: '2026-09-14T00:00:00Z' })).toBeNull();
        expect(formatReleasedDate({ initially_published_at: null })).toBeNull();
    });
});
