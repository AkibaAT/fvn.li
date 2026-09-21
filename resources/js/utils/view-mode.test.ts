import { describe, expect, test } from 'vitest';
import { VIEW_MODE_COOKIES, parseViewMode, writeViewModeCookie } from './view-mode';

describe('parseViewMode', () => {
    test('accepts only the list mode as an opt-in', () => {
        expect(parseViewMode('list')).toBe('list');
        expect(parseViewMode('grid')).toBe('grid');
        expect(parseViewMode(undefined)).toBe('grid');
        expect(parseViewMode(null)).toBe('grid');
        expect(parseViewMode('')).toBe('grid');
        expect(parseViewMode('LIST')).toBe('grid');
    });
});

describe('writeViewModeCookie', () => {
    test('persists the chosen mode where the server can read it back', () => {
        writeViewModeCookie(VIEW_MODE_COOKIES.games, 'list');

        expect(document.cookie).toContain(`${VIEW_MODE_COOKIES.games}=list`);
    });

    test('switches the stored value when the visitor picks the other layout', () => {
        writeViewModeCookie(VIEW_MODE_COOKIES.home, 'list');
        writeViewModeCookie(VIEW_MODE_COOKIES.home, 'grid');

        expect(document.cookie).toContain(`${VIEW_MODE_COOKIES.home}=grid`);
        expect(document.cookie).not.toContain(`${VIEW_MODE_COOKIES.home}=list`);
    });

    test('keeps each catalogue on its own cookie', () => {
        writeViewModeCookie(VIEW_MODE_COOKIES.home, 'grid');
        writeViewModeCookie(VIEW_MODE_COOKIES.games, 'list');

        expect(document.cookie).toContain(`${VIEW_MODE_COOKIES.home}=grid`);
        expect(document.cookie).toContain(`${VIEW_MODE_COOKIES.games}=list`);
    });
});
