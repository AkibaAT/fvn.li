import { describe, expect, test, vi } from 'vitest';
import {
    formatDateTimeWithTimezone,
    formatFutureDateTime,
    formatLocalDate,
    formatLocalDateTime,
    formatRelativeDateTime,
    getUserTimezone,
} from './date-formatting';

describe('date formatting utilities', () => {
    test('normalizes SQL timestamps as UTC datetimes', () => {
        const formatted = formatLocalDateTime('2026-05-03 12:34:56', {
            timeZone: 'UTC',
            hour12: false,
        });

        expect(formatted).toContain('05/03/2026');
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
        expect(getUserTimezone()).toBeTruthy();
        expect(formatDateTimeWithTimezone('invalid')).toBeNull();
    });
});
