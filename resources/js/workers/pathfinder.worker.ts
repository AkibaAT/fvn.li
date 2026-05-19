import type { RouteEdge } from '@/types/route-graph';
import { findPath, type PathfinderOptions, type RoutePath } from '@/utils/pathfinder';

const MAX_CACHE_SIZE = 20;
const pathCache = new Map<string, RoutePath>();

function getCacheKey(startId: string, targetId: string, preferences: PathfinderOptions['preferences']): string {
    return `${startId}:${targetId}:${JSON.stringify(preferences || [])}`;
}

function rememberPath(cacheKey: string, path: RoutePath): void {
    if (pathCache.size >= MAX_CACHE_SIZE) {
        const firstKey = pathCache.keys().next().value;
        if (firstKey) pathCache.delete(firstKey);
    }

    pathCache.set(cacheKey, path);
}

self.onmessage = (event: MessageEvent) => {
    const message = event.data as {
        type: 'findPath' | 'clearCache';
        startNodeId?: string;
        targetNodeId?: string;
        edges?: RouteEdge[];
        options?: PathfinderOptions;
        requestId?: string;
    };

    if (message.type === 'clearCache') {
        pathCache.clear();
        return;
    }

    if (message.type !== 'findPath') return;

    const { startNodeId, targetNodeId, edges, options = {}, requestId } = message;
    if (!startNodeId || !targetNodeId || !edges || !requestId) return;

    try {
        const cacheKey = getCacheKey(startNodeId, targetNodeId, options.preferences);
        const cached = pathCache.get(cacheKey);
        const path = cached ?? findPath(startNodeId, targetNodeId, edges, options);

        if (path && !cached) rememberPath(cacheKey, path);

        self.postMessage({
            type: 'pathResult',
            requestId,
            path,
        });
    } catch (error) {
        self.postMessage({
            type: 'pathResult',
            requestId,
            path: null,
            error: error instanceof Error ? error.message : 'Unknown error',
        });
    }
};
