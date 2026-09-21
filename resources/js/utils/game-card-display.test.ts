import { describe, expect, test } from 'vitest';

import {
    formatAuthorsInline,
    formatLatestDate,
    formatReleasedDate,
    formatReleaseDates,
    formatUpdatedDate,
    formatWordCount,
    formatWordCountBreakdown,
} from './game-card-display';

describe('game card display helpers', () => {
    test('shows the count of whichever language carries most of the text', () => {
        expect(formatWordCount({ primary_word_count: 4128, primary_language_name: 'English', english_word_count: 4128 })).toBe('4,128 words');
        expect(formatWordCount({ primary_word_count: 1743, primary_language_name: 'Mandarin Chinese', english_word_count: 15720 })).toBe(
            '15,720 words',
        );
        expect(formatWordCount({ primary_word_count: 1395, primary_language_name: 'Mandarin Chinese', english_word_count: 27 })).toBe(
            '1,395 words in Mandarin Chinese',
        );
        expect(formatWordCount({ primary_word_count: 4128, primary_language_name: 'Japanese', english_word_count: null })).toBe(
            '4,128 words in Japanese',
        );
        expect(formatWordCount({ primary_word_count: null, primary_language_name: 'English' })).toBeNull();
    });

    test('abbreviates large counts and drops the language in compact form', () => {
        expect(
            formatWordCount({ primary_word_count: 1165793, primary_language_name: 'English', english_word_count: 1165793 }, { compact: true }),
        ).toBe('1.2M words');
        expect(formatWordCount({ primary_word_count: 51425, primary_language_name: 'English', english_word_count: 51425 }, { compact: true })).toBe(
            '51,425 words',
        );
        expect(
            formatWordCount(
                { primary_word_count: 4128, primary_language_name: 'Japanese', english_word_count: null },
                { compact: true, withLanguage: false },
            ),
        ).toBe('4,128 words');
    });

    test('breaks the count down per language for non-English originals', () => {
        expect(formatWordCountBreakdown({ primary_word_count: 1743, primary_language_name: 'Mandarin Chinese', english_word_count: 15720 })).toBe(
            'Mandarin Chinese (original): 1,743 words · English: 15,720 words',
        );
        expect(formatWordCountBreakdown({ primary_word_count: 4128, primary_language_name: 'Japanese', english_word_count: null })).toBe(
            'Japanese (original): 4,128 words',
        );
        expect(formatWordCountBreakdown({ primary_word_count: 4128, primary_language_name: 'English', english_word_count: 4128 })).toBeNull();
    });

    test('card date shows the latest of update and release', () => {
        expect(formatLatestDate({ initially_published_at: '2026-09-14T00:00:00Z', latest_version_published_at: '2026-10-02T00:00:00Z' })).toMatch(
            /^Updated /,
        );
        expect(formatLatestDate({ initially_published_at: '2026-09-14T00:00:00Z', latest_version_published_at: '2026-09-14T00:00:00Z' })).toMatch(
            /^Released /,
        );
        expect(formatLatestDate({ initially_published_at: null, latest_version_published_at: null })).toBeNull();
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
