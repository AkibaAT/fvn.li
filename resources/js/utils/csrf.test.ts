import { beforeEach, describe, expect, test, vi } from 'vitest';

const httpMock = vi.hoisted(() => ({
    defaults: {
        headers: {
            common: {} as Record<string, string>,
        },
    },
}));

vi.mock('@/utils/http', () => ({
    default: httpMock,
}));

describe('csrf utilities', () => {
    beforeEach(() => {
        vi.resetModules();
        httpMock.defaults.headers.common = {};
        document.head.innerHTML = '<meta name="csrf-token" content="initial-token">';
    });

    test('reads csrf tokens and builds authenticated headers', async () => {
        const { getAuthHeaders, getCsrfToken } = await import('./csrf');

        expect(getCsrfToken()).toBe('initial-token');
        expect(getAuthHeaders()).toMatchObject({
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': 'initial-token',
        });
    });

    test('refreshes csrf tokens into meta and axios defaults', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ token: 'fresh-token' }),
            }),
        );

        const { refreshCsrfToken } = await import('./csrf');

        await expect(refreshCsrfToken()).resolves.toBe('fresh-token');
        expect(document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')).toBe('fresh-token');
        expect(httpMock.defaults.headers.common['X-CSRF-TOKEN']).toBe('fresh-token');
    });

    test('retries authenticated fetch after csrf mismatch', async () => {
        const firstResponse = new Response('expired', { status: 419 });
        const secondResponse = new Response('ok', { status: 200 });
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(firstResponse)
            .mockResolvedValueOnce({
                json: () => Promise.resolve({ token: 'fresh-token' }),
            })
            .mockResolvedValueOnce(secondResponse);
        vi.stubGlobal('fetch', fetchMock);

        const { authenticatedFetch } = await import('./csrf');

        await expect(authenticatedFetch('/browser-api/test', { headers: { Accept: 'application/json' } })).resolves.toBe(secondResponse);
        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(fetchMock.mock.calls[2][1].headers['X-CSRF-TOKEN']).toBe('fresh-token');
    });

    test('keeps form data content type browser-managed', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response('ok', { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);
        const body = new FormData();

        const { authenticatedFetch } = await import('./csrf');
        await authenticatedFetch('/upload', { body });

        expect(fetchMock.mock.calls[0][1].headers['Content-Type']).toBeUndefined();
    });
});
