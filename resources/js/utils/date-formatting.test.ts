import { describe, expect, test, vi } from 'vitest';
import {
    formatDateTimeWithTimezone,
    formatFutureDateTime,
    formatLocalDate,
    formatLocalDateTime,
    formatRelativeDateTime,
    formatCalendarDate,
    localDateTimeToUtc,
    toLocalDateTimeInput,
} from './date-formatting';

describe('date formatting utilities', () => {
    test('normalizes SQL timestamps as UTC datetimes', () => {
        const formatted = formatLocalDateTime('2026-05-03 12:34:56', {
            timeZone: 'UTC',
            hour12: false,
        });

        expect(formatted).toContain('May 3, 2026');
        expect(formatted).toContain('12:34:56');
    });

    test('formats date-only strings and rejects invalid input', () => {
        expect(formatLocalDate('2026-05-03', { timeZone: 'UTC' })).toBe('May 3, 2026');
        expect(formatLocalDate(null)).toBeNull();
        expect(formatLocalDate('not-a-date')).toBeNull();
        expect(formatLocalDateTime(undefined)).toBeNull();
        expect(formatLocalDateTime('not-a-date')).toBeNull();
    });

    test('formats relative past timestamps', () => {
        vi.setSystemTime(new Date('2026-05-03T12:00:00Z'));

        expect(formatRelativeDateTime('2026-05-03T11:59:30Z')?.timeAgo).toBe('Just now');
        expect(formatRelativeDateTime('2026-05-03T11:30:00Z')?.timeAgo).toBe('30 minutes ago');
        expect(formatRelativeDateTime('2026-05-03T09:00:00Z')?.timeAgo).toBe('3 hours ago');
        expect(formatRelativeDateTime('2026-05-01T12:00:00Z')?.timeAgo).toBe('2 days ago');
        expect(formatRelativeDateTime('invalid')).toBeNull();
    });

    test('formats future and past event distances', () => {
        vi.setSystemTime(new Date('2026-05-03T12:00:00Z'));

        expect(formatFutureDateTime('2026-05-03T12:10:00Z')?.timeUntil).toBe('in 10 minutes');
        expect(formatFutureDateTime('2026-05-03T15:00:00Z')?.timeUntil).toBe('in 3 hours');
        expect(formatFutureDateTime('2026-05-05T12:00:00Z')?.timeUntil).toBe('in 2 days');
        expect(formatFutureDateTime('2026-05-03T11:00:00Z')?.timeUntil).toBe('1 hour ago');
        expect(formatFutureDateTime('invalid')).toBeNull();
    });

    test('adds timezone labels when requested', () => {
        const withoutTimezone = formatDateTimeWithTimezone('2026-05-03T12:00:00Z', false);
        const withTimezone = formatDateTimeWithTimezone('2026-05-03T12:00:00Z', true);

        expect(withoutTimezone).not.toBeNull();
        expect(withTimezone).toContain(withoutTimezone ?? '');
        expect(formatDateTimeWithTimezone('invalid')).toBeNull();
    });
});

describe('calendar dates and timestamps', () => {
    test('preserves calendar days and chart months in every timezone', () => {
        expect(formatCalendarDate('2026-05-03')).toBe('May 3, 2026');
        expect(formatCalendarDate('2026-05-03T00:00:00.000000Z')).toBe('May 3, 2026');
        expect(formatLocalDate('2026-05-03')).toBe('May 3, 2026');
        expect(formatCalendarDate('2026-05-01', { month: 'short', year: 'numeric', day: undefined })).toBe('May 2026');
        expect(formatCalendarDate('2026-02-30')).toBeNull();
        expect(formatCalendarDate('invalid')).toBeNull();
        expect(formatCalendarDate(null)).toBeNull();
    });

    test('localizes instants while treating unzoned backend timestamps as UTC', () => {
        const instant = '2026-05-03T00:30:00Z';
        const expected = new Date(instant).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        expect(formatLocalDate(instant)).toBe(expected);
        expect(formatLocalDate('2026-05-03 00:30:00')).toBe(expected);
        expect(formatLocalDateTime('2026-05-03T00:30:00')).toBe(formatLocalDateTime(instant));
        expect(formatLocalDateTime('2026-05-02T20:30:00-04:00')).toBe(formatLocalDateTime(instant));
        expect(formatLocalDateTime('2026-05-03T06:15:00+05:45')).toBe(formatLocalDateTime(instant));
    });

    test('uses the displayed date for daylight-saving labels', () => {
        vi.setSystemTime(new Date('2026-07-15T12:00:00Z'));
        const winter = '2026-01-15T12:00:00Z';
        const expected = new Date(winter).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZoneName: 'short',
        });
        expect(formatDateTimeWithTimezone(winter)).toBe(expected);
        expect(formatRelativeDateTime(winter)?.formattedDate).toBe(expected);
    });

    test.each(['2026-01-15T12:00', '2026-07-15T12:00'])('round-trips local release time %s using its own date offset', (local) => {
        const utc = localDateTimeToUtc(local);
        expect(utc).toBe(new Date(local).toISOString());
        expect(toLocalDateTimeInput(utc)).toBe(local);
        expect(toLocalDateTimeInput(null)).toBeNull();
        expect(toLocalDateTimeInput('invalid')).toBeNull();
        expect(localDateTimeToUtc(null)).toBeNull();
    });
});
