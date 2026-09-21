import type { GameCardGame } from '@/hooks/useGameCard.svelte';
import { formatLocalDate } from './date-formatting';

type WordCountFields = Pick<GameCardGame, 'primary_word_count' | 'primary_language_name' | 'english_word_count'>;

export function formatAuthorsInline(authors?: string | null): string {
    if (!authors) return '';

    return authors
        .replace(/<br\s*\/?>(\s*)/gi, ' ')
        .replace(/\n+/g, ' ')
        .replace(/\s{2,}/g, ' ')
        .trim();
}

const hasCount = (value: number | null | undefined): value is number => typeof value === 'number' && value > 0;

const compactNumber = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 });

const words = (count: number, compact = false) => `${compact && count >= 100_000 ? compactNumber.format(count) : count.toLocaleString()} words`;

type WordCountOptions = { compact?: boolean; withLanguage?: boolean };

export function formatWordCount(game: WordCountFields, { compact = false, withLanguage = true }: WordCountOptions = {}): string | null {
    const english = game.english_word_count;
    const primary = game.primary_word_count;

    if (hasCount(english) && (!hasCount(primary) || english * 2 >= primary)) return words(english, compact);
    if (!hasCount(primary)) return null;

    const language = game.primary_language_name;
    const label = words(primary, compact);

    return withLanguage && language && language !== 'English' ? `${label} in ${language}` : label;
}

export function formatWordCountBreakdown(game: WordCountFields): string | null {
    const language = game.primary_language_name;
    if (!language || language === 'English' || !hasCount(game.primary_word_count)) return null;

    const original = `${language} (original): ${words(game.primary_word_count)}`;

    return hasCount(game.english_word_count) ? `${original} · English: ${words(game.english_word_count)}` : original;
}

type DateFields = Pick<GameCardGame, 'initially_published_at' | 'latest_version_published_at'>;

type ThumbnailFields = {
    thumb_url?: string | null;
    optimized_thumbnails?: { default?: { path: string } } | null;
};

export function getGameThumbnail(game: ThumbnailFields): string {
    return game.optimized_thumbnails?.default?.path ? `/storage/${game.optimized_thumbnails.default.path}` : game.thumb_url || '';
}

export function formatReleasedDate(game: DateFields): string | null {
    return formatLocalDate(game.initially_published_at ?? null);
}

export function formatUpdatedDate(game: DateFields): string | null {
    const released = formatReleasedDate(game);
    const updated = formatLocalDate(game.latest_version_published_at ?? null);

    return updated && updated !== released ? updated : null;
}

export function formatReleaseDates(game: DateFields): string | null {
    const released = formatReleasedDate(game);
    if (!released) return null;

    const updated = formatUpdatedDate(game);

    return updated ? `Released ${released} · Updated ${updated}` : `Released ${released}`;
}

export function formatLatestDate(game: DateFields): string | null {
    const updated = formatUpdatedDate(game);
    if (updated) return `Updated ${updated}`;

    const released = formatReleasedDate(game);

    return released ? `Released ${released}` : null;
}
