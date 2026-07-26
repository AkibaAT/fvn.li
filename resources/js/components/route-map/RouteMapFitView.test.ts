import { render, waitFor } from '@testing-library/svelte';
import { beforeEach, describe, expect, test, vi } from 'vitest';

const flow = vi.hoisted(() => ({
    fitView: vi.fn(),
    getNodes: vi.fn(),
    getNodesBounds: vi.fn(),
    getViewport: vi.fn(),
    setViewport: vi.fn(),
}));

vi.mock('@xyflow/svelte', () => ({
    useSvelteFlow: () => flow,
}));

import RouteMapFitView from './RouteMapFitView.svelte';

describe('RouteMapFitView', () => {
    beforeEach(() => {
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
            callback(0);
            return 1;
        });

        flow.fitView.mockResolvedValue(true);
        flow.getNodes.mockReturnValue([{ id: 'start', data: { is_start: true } }]);
        flow.getNodesBounds
            .mockReturnValueOnce({ x: -100, y: -50, width: 48000, height: 5300 })
            .mockReturnValueOnce({ x: 40000, y: -50, width: 140, height: 54 });
        flow.getViewport.mockReturnValue({ x: 47, y: 303, zoom: 0.02 });
        flow.setViewport.mockResolvedValue(true);
    });

    test('fits the route depth from top to bottom and centers the start node', async () => {
        render(RouteMapFitView, { props: { layoutVersion: 1 } });

        await waitFor(() => {
            expect(flow.setViewport).toHaveBeenCalledWith(
                expect.objectContaining({
                    x: expect.closeTo(-4479.97, 2),
                    y: expect.closeTo(30.25, 2),
                    zoom: expect.closeTo(0.12491, 4),
                }),
            );
        });

        expect(flow.fitView).toHaveBeenCalledWith({ padding: 0.12, minZoom: 0.01, maxZoom: 1 });
        expect(flow.getNodesBounds).toHaveBeenCalledWith([{ id: 'start', data: { is_start: true } }]);
    });
});
