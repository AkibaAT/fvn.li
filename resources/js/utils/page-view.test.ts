import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/svelte', () => ({ router: { on: vi.fn() } }));

import { recordGamePageView, visitorId } from './page-view';

describe('game page view beacon', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.stubGlobal('route', (name: string, id: number) => `/api/games/${id}/view#${name}`);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    test('keeps the visitor id for two hours after the last view', () => {
        const first = visitorId(0);

        expect(visitorId(60 * 60 * 1000)).toBe(first);
        expect(visitorId(3 * 60 * 60 * 1000 - 1)).toBe(first);
        expect(visitorId(6 * 60 * 60 * 1000)).not.toBe(first);
    });

    test('generates a v4 uuid without a secure context', () => {
        vi.stubGlobal('crypto', { getRandomValues: globalThis.crypto.getRandomValues.bind(globalThis.crypto) });

        expect(visitorId()).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    });

    test('posts the view without credentials so it never sets a cookie', () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }));
        vi.stubGlobal('fetch', fetchMock);

        recordGamePageView(42);

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/api/games/42/view#api.games.view');
        expect(init).toMatchObject({ method: 'POST', credentials: 'omit', keepalive: true });
        expect(JSON.parse(init.body)).toEqual({ visitor_id: visitorId(), referrer: null });
    });
});
