import type { GameCardGame } from '@/hooks/useGameCard.svelte';
import { formatLocalDate } from './date-formatting';

type WordCountFields = Pick<GameCardGame, 'primary_word_count' | 'primary_language_label' | 'english_word_count'>;

/**
 * Inline author markup for the single truncated author line: `<br>` tags and
 * newlines collapse to spaces. Returns '' when there are no authors.
 */
export function formatAuthorsInline(authors?: string | null): string {
    if (!authors) return '';

    return authors
        .replace(/<br\s*\/?>(\s*)/gi, ' ')
        .replace(/\n+/g, ' ')
        .replace(/\s{2,}/g, ' ')
        .trim();
}

/**
 * Card/row meta line: "4,128 words", or "4,128 words (JA) · 3,900 EN" when the
 * primary language is not English. Missing counts become "Word count pending"
 * on cards; rows pass `fallback` to omit instead.
 */
export function formatWordCount(game: WordCountFields, fallback: string | null = 'Word count pending'): string | null {
    const primary = game.primary_word_count;
    if (typeof primary !== 'number' || primary <= 0) return fallback;

    const label = game.primary_language_label || 'EN';
    const english = game.english_word_count;
    if (label !== 'EN' && typeof english === 'number' && english > 0) {
        return `${primary.toLocaleString()} words (${label}) · ${english.toLocaleString()} EN`;
    }

    return `${primary.toLocaleString()} words`;
}

type DateFields = Pick<GameCardGame, 'initially_published_at' | 'latest_version_published_at'>;

/** Release date on its own, for rows that give release and update separate columns. */
export function formatReleasedDate(game: DateFields): string | null {
    return formatLocalDate(game.initially_published_at ?? null);
}

/** Update date on its own, or null when there is no release or it never changed. */
export function formatUpdatedDate(game: DateFields): string | null {
    const released = formatReleasedDate(game);
    const updated = formatLocalDate(game.latest_version_published_at ?? null);

    return updated && updated !== released ? updated : null;
}

/**
 * "Released 14 Sept 2026 · Updated 2 Oct 2026". The updated half is omitted
 * when it matches the release date, and the whole line when there is no
 * release date.
 */
export function formatReleaseDates(game: DateFields): string | null {
    const released = formatReleasedDate(game);
    if (!released) return null;

    const updated = formatUpdatedDate(game);

    return updated ? `Released ${released} · Updated ${updated}` : `Released ${released}`;
}
