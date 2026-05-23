import type { RouteEdge } from '@/types/route-graph';
import { findPath, type PathfinderOptions, type RoutePath } from '@/utils/pathfinder';

interface PendingRequest {
    resolve: (path: RoutePath | null) => void;
    reject: (error: Error) => void;
}

class PathfinderWorkerManager {
    private worker: Worker | null = null;
    private pendingRequests = new Map<string, PendingRequest>();
    private requestIdCounter = 0;

    constructor() {
        this.initWorker();
    }

    private initWorker() {
        try {
            this.worker = new Worker(new URL('../workers/pathfinder.worker.ts', import.meta.url), { type: 'module' });

            this.worker.onmessage = (event) => {
                const { type, requestId, path, error } = event.data;
                if (type !== 'pathResult') return;

                const pending = this.pendingRequests.get(requestId);
                if (!pending) return;

                this.pendingRequests.delete(requestId);
                if (error) {
                    pending.reject(new Error(error));
                } else {
                    pending.resolve(path);
                }
            };

            this.worker.onerror = (error) => {
                console.error('Pathfinder worker error:', error);
                this.rejectPending(new Error('Worker error'));
            };
        } catch (error) {
            console.error('Failed to initialize pathfinder worker:', error);
            this.worker = null;
        }
    }

    async findPath(
        startNodeId: string,
        targetNodeId: string,
        edges: RouteEdge[],
        options: PathfinderOptions = {},
    ): Promise<RoutePath | null> {
        if (!this.worker) {
            return findPath(startNodeId, targetNodeId, edges, options);
        }

        const requestId = String(++this.requestIdCounter);

        return new Promise((resolve, reject) => {
            this.pendingRequests.set(requestId, { resolve, reject });
            this.worker!.postMessage({
                type: 'findPath',
                startNodeId,
                targetNodeId,
                edges,
                options,
                requestId,
            });

            window.setTimeout(() => {
                const pending = this.pendingRequests.get(requestId);
                if (!pending) return;

                this.pendingRequests.delete(requestId);
                pending.reject(new Error('Pathfinding timeout'));
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
            this.rejectPending(new Error('Worker terminated'));
            this.worker.terminate();
            this.worker = null;
        }
    }

    private rejectPending(error: Error) {
        this.pendingRequests.forEach((pending) => {
            pending.reject(error);
        });
        this.pendingRequests.clear();
    }
}

let workerManager: PathfinderWorkerManager | null = null;

export function usePathfinderWorker() {
    if (!workerManager) {
        workerManager = new PathfinderWorkerManager();
    }

    return {
        findPath: (startNodeId: string, targetNodeId: string, edges: RouteEdge[], options: PathfinderOptions = {}) =>
            workerManager!.findPath(startNodeId, targetNodeId, edges, options),
        clearCache: () => workerManager!.clearCache(),
        terminate: () => {
            workerManager?.terminate();
            workerManager = null;
        },
    };
}
