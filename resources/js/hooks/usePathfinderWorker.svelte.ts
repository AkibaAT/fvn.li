import type { RouteEdge } from '@/types/route-graph';
import { findPath, type PathfinderOptions, type RoutePath } from '@/utils/pathfinder';

interface PendingRequest {
    resolve: (path: RoutePath | null) => void;
    reject: (error: Error) => void;
    timeoutId: ReturnType<typeof setTimeout>;
}

class PathfinderWorkerManager {
    private worker: Worker | null = null;
    private pendingRequests = new Map<string, PendingRequest>();
    private requestIdCounter = 0;

    constructor() {
        this.initWorker();
    }

    private initWorker() {
        if (typeof Worker === 'undefined') {
            this.worker = null;
            return;
        }

        try {
            this.worker = new Worker(new URL('../workers/pathfinder.worker.ts', import.meta.url), { type: 'module' });

            this.worker.onmessage = (event) => {
                const { type, requestId, path, error } = event.data;
                if (type !== 'pathResult') return;

                const pending = this.pendingRequests.get(requestId);
                if (!pending) return;

                clearTimeout(pending.timeoutId);
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

    async findPath(startNodeId: string, targetNodeId: string, edges: RouteEdge[], options: PathfinderOptions = {}): Promise<RoutePath | null> {
        if (!this.worker) {
            return findPath(startNodeId, targetNodeId, edges, options);
        }

        const requestId = String(++this.requestIdCounter);

        return new Promise((resolve, reject) => {
            const timeoutId = setTimeout(() => {
                const pending = this.pendingRequests.get(requestId);
                if (!pending) return;

                this.pendingRequests.delete(requestId);
                pending.reject(new Error('Pathfinding timeout'));
            }, 5000);

            this.pendingRequests.set(requestId, { resolve, reject, timeoutId });
            // Callers typically pass $state/$derived proxies; postMessage's
            // structured clone rejects them, so snapshot to plain values.
            this.worker!.postMessage({
                type: 'findPath',
                startNodeId,
                targetNodeId,
                edges: $state.snapshot(edges),
                options: $state.snapshot(options),
                requestId,
            });
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
            clearTimeout(pending.timeoutId);
            pending.reject(error);
        });
        this.pendingRequests.clear();
    }
}

let workerManager: PathfinderWorkerManager | null = null;

function getWorkerManager(): PathfinderWorkerManager | null {
    if (typeof Worker === 'undefined') {
        return null;
    }

    if (!workerManager) {
        workerManager = new PathfinderWorkerManager();
    }

    return workerManager;
}

export function usePathfinderWorker() {
    return {
        findPath: (startNodeId: string, targetNodeId: string, edges: RouteEdge[], options: PathfinderOptions = {}) => {
            const manager = getWorkerManager();
            if (!manager) {
                return Promise.resolve(findPath(startNodeId, targetNodeId, edges, options));
            }

            return manager.findPath(startNodeId, targetNodeId, edges, options);
        },
        clearCache: () => getWorkerManager()?.clearCache(),
        terminate: () => {
            workerManager?.terminate();
            workerManager = null;
        },
    };
}
