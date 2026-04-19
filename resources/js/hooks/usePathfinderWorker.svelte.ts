/**
 * Hook for using the pathfinder Web Worker
 * Provides async pathfinding that runs off the main thread
 */

import type { RouteEdge } from '@/types/route-graph';

interface PendingRequest {
    resolve: (path: Array<{ nodeId: string; edge: RouteEdge | null }> | null) => void;
    reject: (error: Error) => void;
}

// Worker code as a string - embedded to avoid CORS issues in dev
const WORKER_CODE = `
// BFS pathfinding worker
const MAX_CACHE_SIZE = 20;
const pathCache = new Map();

function getCacheKey(startId, targetId) {
    return startId + ':' + targetId;
}

function findPath(startNodeId, targetNodeId, edges) {
    const cacheKey = getCacheKey(startNodeId, targetNodeId);
    const cached = pathCache.get(cacheKey);
    if (cached) return cached;

    // Build adjacency list
    const adjacency = new Map();
    for (const edge of edges) {
        if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
        adjacency.get(edge.source).push({ target: edge.target, edge });
    }

    // BFS
    const visited = new Set([startNodeId]);
    const queue = [
        { nodeId: startNodeId, path: [{ nodeId: startNodeId, edge: null }] },
    ];

    while (queue.length > 0) {
        const current = queue.shift();

        if (current.nodeId === targetNodeId) {
            // Cache with LRU eviction
            if (pathCache.size >= MAX_CACHE_SIZE) {
                const firstKey = pathCache.keys().next().value;
                if (firstKey) pathCache.delete(firstKey);
            }
            pathCache.set(cacheKey, current.path);
            return current.path;
        }

        for (const { target, edge } of adjacency.get(current.nodeId) ?? []) {
            if (!visited.has(target)) {
                visited.add(target);
                queue.push({
                    nodeId: target,
                    path: [...current.path, { nodeId: target, edge }],
                });
            }
        }
    }

    return null;
}

// Handle messages from main thread
self.onmessage = (event) => {
    const message = event.data;

    if (message.type === 'findPath') {
        const { startNodeId, targetNodeId, edges, requestId } = message;

        try {
            const path = findPath(startNodeId, targetNodeId, edges);
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
    } else if (message.type === 'clearCache') {
        pathCache.clear();
    }
};
`;

class PathfinderWorkerManager {
    private worker: Worker | null = null;
    private pendingRequests = new Map<string, PendingRequest>();
    private requestIdCounter = 0;

    constructor() {
        this.initWorker();
    }

    private initWorker() {
        try {
            // Create worker from inline blob to avoid CORS issues
            const blob = new Blob([WORKER_CODE], { type: 'application/javascript' });
            const workerUrl = URL.createObjectURL(blob);
            this.worker = new Worker(workerUrl);

            // Clean up the blob URL after worker is created
            URL.revokeObjectURL(workerUrl);

            this.worker.onmessage = (event) => {
                const { type, requestId, path, error } = event.data;

                if (type === 'pathResult') {
                    const pending = this.pendingRequests.get(requestId);
                    if (pending) {
                        this.pendingRequests.delete(requestId);
                        if (error) {
                            pending.reject(new Error(error));
                        } else {
                            pending.resolve(path);
                        }
                    }
                }
            };

            this.worker.onerror = (error) => {
                console.error('Pathfinder worker error:', error);
                // Reject all pending requests
                this.pendingRequests.forEach((pending) => {
                    pending.reject(new Error('Worker error'));
                });
                this.pendingRequests.clear();
            };
        } catch (error) {
            console.error('Failed to initialize pathfinder worker:', error);
            this.worker = null;
        }
    }

    async findPath(startNodeId: string, targetNodeId: string, edges: RouteEdge[]): Promise<Array<{ nodeId: string; edge: RouteEdge | null }> | null> {
        // Fallback to synchronous if worker failed to initialize
        if (!this.worker) {
            return this.findPathSync(startNodeId, targetNodeId, edges);
        }

        const requestId = String(++this.requestIdCounter);

        return new Promise((resolve, reject) => {
            this.pendingRequests.set(requestId, { resolve, reject });

            // Post message to worker
            this.worker!.postMessage({
                type: 'findPath',
                startNodeId,
                targetNodeId,
                edges,
                requestId,
            });

            // Timeout after 5 seconds
            setTimeout(() => {
                const pending = this.pendingRequests.get(requestId);
                if (pending) {
                    this.pendingRequests.delete(requestId);
                    pending.reject(new Error('Pathfinding timeout'));
                }
            }, 5000);
        });
    }

    clearCache() {
        if (this.worker) {
            this.worker.postMessage({ type: 'clearCache' });
        }
    }

    terminate() {
        if (this.worker) {
            // Reject all pending requests
            this.pendingRequests.forEach((pending) => {
                pending.reject(new Error('Worker terminated'));
            });
            this.pendingRequests.clear();

            this.worker.terminate();
            this.worker = null;
        }
    }

    // Synchronous fallback (runs on main thread)
    private findPathSync(startNodeId: string, targetNodeId: string, edges: RouteEdge[]): Array<{ nodeId: string; edge: RouteEdge | null }> | null {
        const adjacency = new Map<string, Array<{ target: string; edge: RouteEdge }>>();
        for (const edge of edges) {
            if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
            adjacency.get(edge.source)!.push({ target: edge.target, edge });
        }

        const visited = new Set<string>([startNodeId]);
        const queue: Array<{ nodeId: string; path: Array<{ nodeId: string; edge: RouteEdge | null }> }> = [
            { nodeId: startNodeId, path: [{ nodeId: startNodeId, edge: null }] },
        ];

        while (queue.length > 0) {
            const current = queue.shift()!;

            if (current.nodeId === targetNodeId) {
                return current.path;
            }

            for (const { target, edge } of adjacency.get(current.nodeId) ?? []) {
                if (!visited.has(target)) {
                    visited.add(target);
                    queue.push({
                        nodeId: target,
                        path: [...current.path, { nodeId: target, edge }],
                    });
                }
            }
        }

        return null;
    }
}

// Singleton instance
let workerManager: PathfinderWorkerManager | null = null;

export function usePathfinderWorker() {
    if (!workerManager) {
        workerManager = new PathfinderWorkerManager();
    }

    return {
        findPath: (startNodeId: string, targetNodeId: string, edges: RouteEdge[]) => workerManager!.findPath(startNodeId, targetNodeId, edges),
        clearCache: () => workerManager!.clearCache(),
        terminate: () => {
            workerManager?.terminate();
            workerManager = null;
        },
    };
}
