import { beforeEach, describe, expect, test, vi } from 'vitest';

const http = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@/utils/http', () => ({ default: http }));

import { fetchRouteGraph, fetchVersionComparison, parseSaveFile } from './game-data';

const route = vi.fn((name: string) => `/${name}`);

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', route);
});

describe('game-data API', () => {
    test('fetches a version comparison with all version params', async () => {
        const comparison = { fromVersion: { id: 1 }, toVersion: { id: 2 } };
        http.get.mockResolvedValueOnce({ data: comparison });

        await expect(fetchVersionComparison({ gameId: 10, fromVersionId: 1, toVersionId: 2 })).resolves.toEqual(comparison);
        expect(route).toHaveBeenCalledWith('api.games.compare-versions', { game: 10, fromVersionId: 1, toVersionId: 2 });
        expect(http.get).toHaveBeenCalledWith('/api.games.compare-versions');
    });

    test('requests unreachable nodes via the include_unreachable param', async () => {
        const graph = { nodes: [], edges: [] };
        http.get.mockResolvedValueOnce({ data: graph });

        await expect(fetchRouteGraph({ gameSlug: 'echoes', versionId: 3, includeUnreachable: true })).resolves.toEqual(graph);
        expect(route).toHaveBeenCalledWith('browser-api.games.version.route-graph', { game: 'echoes', version: 3 });
        expect(http.get).toHaveBeenCalledWith('/browser-api.games.version.route-graph', { params: { include_unreachable: 1 } });
    });

    test('omits query params when unreachable nodes are not requested', async () => {
        http.get.mockResolvedValueOnce({ data: { nodes: [], edges: [] } });

        await fetchRouteGraph({ gameSlug: 'echoes', versionId: 3 });
        expect(http.get).toHaveBeenCalledWith('/browser-api.games.version.route-graph', { params: undefined });
    });

    test('posts the save file as form data and unwraps seen_labels', async () => {
        const file = new File(['save-bytes'], 'save.dat');
        http.post.mockResolvedValueOnce({ data: { seen_labels: ['start', 'ch1'] } });

        await expect(parseSaveFile('echoes', 5, file)).resolves.toEqual(['start', 'ch1']);
        expect(route).toHaveBeenCalledWith('browser-api.games.version.parse-save', { game: 'echoes', version: 5 });

        const [url, body] = http.post.mock.calls[0] as [string, FormData];
        expect(url).toBe('/browser-api.games.version.parse-save');
        expect(body).toBeInstanceOf(FormData);
        expect(body.get('file')).toBe(file);
    });
});
