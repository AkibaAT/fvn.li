/**
 * Hook for using the pathfinder Web Worker
 * Provides async pathfinding that runs off the main thread
 */

import type { RouteEdge, RouteNode, RoutePreference, RouteVariable } from '@/types/route-graph';

/* eslint-disable svelte/prefer-svelte-reactivity -- this hook uses Maps for worker request bookkeeping and local pathfinding state, not Svelte component state. */

interface PendingRequest {
    resolve: (path: Array<{ nodeId: string; edge: RouteEdge | null }> | null) => void;
    reject: (error: Error) => void;
}

interface PathfinderOptions {
    nodes?: RouteNode[];
    variables?: RouteVariable[];
    preferences?: RoutePreference[];
}

// Worker code as a string - embedded to avoid CORS issues in dev
const WORKER_CODE = `
// BFS pathfinding worker
const MAX_CACHE_SIZE = 20;
const pathCache = new Map();

// Cached adjacency list so we don't rebuild it on every findPath call
let cachedEdgesRef = null;
let adjacencyCache = new Map();

function getCacheKey(startId, targetId, preferences) {
    return startId + ':' + targetId + ':' + JSON.stringify(preferences || []);
}

function buildAdjacency(edges) {
    const adj = new Map();
    for (const edge of edges) {
        if (!adj.has(edge.source)) adj.set(edge.source, []);
        adj.get(edge.source).push({ target: edge.target, edge });
    }
    return adj;
}

function getAdjacency(edges) {
    // Rebuild only if the edges reference changed (new graph loaded)
    if (edges !== cachedEdgesRef) {
        adjacencyCache = buildAdjacency(edges);
        cachedEdgesRef = edges;
        pathCache.clear();
    }
    return adjacencyCache;
}

function findShortestPath(startNodeId, targetNodeId, edges) {
    // BFS with parent-pointer backtracking (avoids O(n) array copies per step)
    // parent maps: nodeId -> { nodeId: parentName, edge: edgeObj } | null  (null = start)
    const adjacency = getAdjacency(edges);
    const parent = new Map([[startNodeId, null]]);
    const queue = [startNodeId];

    while (queue.length > 0) {
        const current = queue.shift();

        if (current === targetNodeId) {
            // Reconstruct path by backtracking through parent pointers
            const path = [];
            let node = current;
            while (node !== null) {
                const parentInfo = parent.get(node);
                path.push({
                    nodeId: node,
                    edge: parentInfo ? parentInfo.edge : null,
                });
                node = parentInfo ? parentInfo.nodeId : null;
            }
            path.reverse();

            return path;
        }

        for (const { target, edge } of adjacency.get(current) ?? []) {
            if (!parent.has(target)) {
                parent.set(target, { nodeId: current, edge });
                queue.push(target);
            }
        }
    }

    return null;
}

function parseRouteValue(raw) {
    if (raw == null) return null;

    let value = String(raw).trim();
    const constantMatch = value.match(/^Constant\\(value=(.*)\\)$/);
    if (constantMatch) value = constantMatch[1].trim();

    if (!value) return null;

    if ((value.startsWith("'") && value.endsWith("'")) || (value.startsWith('"') && value.endsWith('"'))) {
        value = value.slice(1, -1).replace(/\\\\(.)/g, function (_match, escaped) {
            if (escaped === 'n') return '\\n';
            if (escaped === 'r') return '\\r';
            if (escaped === 't') return '\\t';
            return escaped;
        });
    }

    if (value === 'True' || value.toLowerCase() === 'true') return true;
    if (value === 'False' || value.toLowerCase() === 'false') return false;
    if (value === 'None' || value.toLowerCase() === 'null') return null;

    if (/^-?\\d+$/.test(value)) return Number.parseInt(value, 10);
    if (/^-?\\d+\\.\\d+$/.test(value)) return Number.parseFloat(value);

    return value;
}

function valueAsNumber(value) {
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    if (typeof value === 'boolean') return value ? 1 : 0;
    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }
    return 0;
}

function normalizeComparable(value) {
    if (value == null) return 'null';
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    return String(value).trim().toLowerCase();
}

function buildNodeMap(nodes) {
    const map = new Map();
    for (const node of nodes || []) {
        map.set(node.id, node);
    }
    return map;
}

function getInitialValues(variables, preferences) {
    const values = {};
    const preferenceNames = new Set((preferences || []).map(function (pref) { return pref.variable; }));

    for (const variable of variables || []) {
        if (preferenceNames.has(variable.name)) {
            values[variable.name] = parseRouteValue(variable.default_value);
        }
    }

    for (const pref of preferences || []) {
        if (!(pref.variable in values)) values[pref.variable] = null;
    }

    return values;
}

function applyNodeChanges(values, node, preferenceNames) {
    const next = { ...values };
    if (!node || !Array.isArray(node.variable_changes)) return next;

    for (const change of node.variable_changes) {
        const variable = change.variable;
        if (!preferenceNames.has(variable)) continue;

        const rawValue = parseRouteValue(change.value);
        const operation = change.operation || '=';

        if (operation === '+=') {
            next[variable] = valueAsNumber(next[variable]) + valueAsNumber(rawValue);
        } else if (operation === '-=') {
            next[variable] = valueAsNumber(next[variable]) - valueAsNumber(rawValue);
        } else if (operation === '=') {
            next[variable] = rawValue;
        }
    }

    return next;
}

function evaluatePreferences(values, preferences) {
    return (preferences || []).map(function (pref) {
        if (pref.mode === 'equals') {
            return normalizeComparable(values[pref.variable]) === normalizeComparable(parseRouteValue(pref.value)) ? 1 : 0;
        }

        const numericValue = valueAsNumber(values[pref.variable]);
        return pref.mode === 'minimize' ? -numericValue : numericValue;
    });
}

function compareScores(left, right) {
    const length = Math.max(left.length, right.length);
    for (let i = 0; i < length; i++) {
        const leftValue = left[i] ?? 0;
        const rightValue = right[i] ?? 0;
        if (leftValue !== rightValue) return leftValue - rightValue;
    }
    return 0;
}

function isBetterState(candidate, existing) {
    if (!existing) return true;

    const scoreDelta = compareScores(candidate.score, existing.score);
    if (scoreDelta !== 0) return scoreDelta > 0;

    return candidate.path.length < existing.path.length;
}

function findPreferredPath(startNodeId, targetNodeId, edges, nodes, variables, preferences) {
    const adjacency = getAdjacency(edges);
    const nodeMap = buildNodeMap(nodes);
    const preferenceNames = new Set((preferences || []).map(function (pref) { return pref.variable; }));

    const startValues = applyNodeChanges(getInitialValues(variables, preferences), nodeMap.get(startNodeId), preferenceNames);
    const startState = {
        nodeId: startNodeId,
        values: startValues,
        score: evaluatePreferences(startValues, preferences),
        path: [{ nodeId: startNodeId, edge: null }],
    };

    const bestByNode = new Map([[startNodeId, startState]]);
    const maxIterations = Math.max(1, (nodes || []).length || edges.length || 1);

    for (let iteration = 0; iteration < maxIterations; iteration++) {
        let changed = false;
        const states = Array.from(bestByNode.values());

        for (const state of states) {
            for (const { target, edge } of adjacency.get(state.nodeId) ?? []) {
                if (state.path.some(function (step) { return step.nodeId === target; })) continue;

                const values = applyNodeChanges(state.values, nodeMap.get(target), preferenceNames);
                const candidate = {
                    nodeId: target,
                    values,
                    score: evaluatePreferences(values, preferences),
                    path: [...state.path, { nodeId: target, edge }],
                };
                const existing = bestByNode.get(target);

                if (isBetterState(candidate, existing)) {
                    bestByNode.set(target, candidate);
                    changed = true;
                }
            }
        }

        if (!changed) break;
    }

    return bestByNode.get(targetNodeId)?.path ?? findShortestPath(startNodeId, targetNodeId, edges);
}

function findPath(startNodeId, targetNodeId, edges, options) {
    const preferences = options?.preferences || [];
    getAdjacency(edges);

    const cacheKey = getCacheKey(startNodeId, targetNodeId, preferences);
    const cached = pathCache.get(cacheKey);
    if (cached) return cached;

    const path = preferences.length > 0
        ? findPreferredPath(startNodeId, targetNodeId, edges, options?.nodes || [], options?.variables || [], preferences)
        : findShortestPath(startNodeId, targetNodeId, edges);

    if (path) {
        // Cache with LRU eviction
        if (pathCache.size >= MAX_CACHE_SIZE) {
            const firstKey = pathCache.keys().next().value;
            if (firstKey) pathCache.delete(firstKey);
        }
        pathCache.set(cacheKey, path);
    }

    return path;
}

// Handle messages from main thread
self.onmessage = (event) => {
    const message = event.data;

    if (message.type === 'findPath') {
        const { startNodeId, targetNodeId, edges, options, requestId } = message;

        try {
            const path = findPath(startNodeId, targetNodeId, edges, options || {});
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
        cachedEdgesRef = null;
        adjacencyCache = new Map();
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

    async findPath(
        startNodeId: string,
        targetNodeId: string,
        edges: RouteEdge[],
        options: PathfinderOptions = {},
    ): Promise<Array<{ nodeId: string; edge: RouteEdge | null }> | null> {
        // Fallback to synchronous if worker failed to initialize
        if (!this.worker) {
            return this.findPathSync(startNodeId, targetNodeId, edges, options);
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
                options,
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
    private findPathSync(
        startNodeId: string,
        targetNodeId: string,
        edges: RouteEdge[],
        options: PathfinderOptions = {},
    ): Array<{ nodeId: string; edge: RouteEdge | null }> | null {
        if (options.preferences && options.preferences.length > 0) {
            return this.findPreferredPathSync(startNodeId, targetNodeId, edges, options);
        }

        return this.findShortestPathSync(startNodeId, targetNodeId, edges);
    }

    private findShortestPathSync(
        startNodeId: string,
        targetNodeId: string,
        edges: RouteEdge[],
    ): Array<{ nodeId: string; edge: RouteEdge | null }> | null {
        const adjacency = new Map<string, Array<{ target: string; edge: RouteEdge }>>();
        for (const edge of edges) {
            if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
            adjacency.get(edge.source)!.push({ target: edge.target, edge });
        }

        // BFS with parent-pointer backtracking
        const parent = new Map<string, { nodeId: string; edge: RouteEdge } | null>([[startNodeId, null]]);
        const queue: string[] = [startNodeId];

        while (queue.length > 0) {
            const current = queue.shift()!;

            if (current === targetNodeId) {
                const path: Array<{ nodeId: string; edge: RouteEdge | null }> = [];
                let node: string | null = current;
                while (node !== null) {
                    const parentInfo = parent.get(node);
                    path.push({ nodeId: node, edge: parentInfo ? parentInfo.edge : null });
                    node = parentInfo ? parentInfo.nodeId : null;
                }
                path.reverse();
                return path;
            }

            for (const { target, edge } of adjacency.get(current) ?? []) {
                if (!parent.has(target)) {
                    parent.set(target, { nodeId: current, edge });
                    queue.push(target);
                }
            }
        }

        return null;
    }

    private findPreferredPathSync(
        startNodeId: string,
        targetNodeId: string,
        edges: RouteEdge[],
        options: PathfinderOptions,
    ): Array<{ nodeId: string; edge: RouteEdge | null }> | null {
        const preferences = options.preferences ?? [];
        const adjacency = new Map<string, Array<{ target: string; edge: RouteEdge }>>();
        for (const edge of edges) {
            if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
            adjacency.get(edge.source)!.push({ target: edge.target, edge });
        }

        const nodeMap = new Map<string, RouteNode>();
        for (const node of options.nodes ?? []) {
            nodeMap.set(node.id, node);
        }

        const preferenceNames = new Set(preferences.map((pref) => pref.variable));
        const startValues = this.applyNodeChanges(
            this.getInitialValues(options.variables ?? [], preferences),
            nodeMap.get(startNodeId),
            preferenceNames,
        );
        const bestByNode = new Map<
            string,
            {
                values: Record<string, unknown>;
                score: number[];
                path: Array<{ nodeId: string; edge: RouteEdge | null }>;
            }
        >([
            [
                startNodeId,
                {
                    values: startValues,
                    score: this.evaluatePreferences(startValues, preferences),
                    path: [{ nodeId: startNodeId, edge: null }],
                },
            ],
        ]);
        const maxIterations = Math.max(1, (options.nodes ?? []).length || edges.length || 1);

        for (let iteration = 0; iteration < maxIterations; iteration++) {
            let changed = false;
            const states = [...bestByNode.entries()];

            for (const [nodeId, state] of states) {
                for (const { target, edge } of adjacency.get(nodeId) ?? []) {
                    if (state.path.some((step) => step.nodeId === target)) continue;

                    const values = this.applyNodeChanges(state.values, nodeMap.get(target), preferenceNames);
                    const candidate = {
                        values,
                        score: this.evaluatePreferences(values, preferences),
                        path: [...state.path, { nodeId: target, edge }],
                    };
                    const existing = bestByNode.get(target);

                    if (this.isBetterState(candidate, existing)) {
                        bestByNode.set(target, candidate);
                        changed = true;
                    }
                }
            }

            if (!changed) break;
        }

        return bestByNode.get(targetNodeId)?.path ?? this.findShortestPathSync(startNodeId, targetNodeId, edges);
    }

    private getInitialValues(variables: RouteVariable[], preferences: RoutePreference[]): Record<string, unknown> {
        const values: Record<string, unknown> = {};
        const preferenceNames = new Set(preferences.map((pref) => pref.variable));

        for (const variable of variables) {
            if (preferenceNames.has(variable.name)) {
                values[variable.name] = this.parseRouteValue(variable.default_value);
            }
        }

        for (const pref of preferences) {
            if (!(pref.variable in values)) values[pref.variable] = null;
        }

        return values;
    }

    private applyNodeChanges(values: Record<string, unknown>, node: RouteNode | undefined, preferenceNames: Set<string>): Record<string, unknown> {
        const next = { ...values };
        if (!node?.variable_changes) return next;

        for (const change of node.variable_changes) {
            if (!preferenceNames.has(change.variable)) continue;

            const value = this.parseRouteValue(change.value);
            if (change.operation === '+=') {
                next[change.variable] = this.valueAsNumber(next[change.variable]) + this.valueAsNumber(value);
            } else if (change.operation === '-=') {
                next[change.variable] = this.valueAsNumber(next[change.variable]) - this.valueAsNumber(value);
            } else if (change.operation === '=') {
                next[change.variable] = value;
            }
        }

        return next;
    }

    private evaluatePreferences(values: Record<string, unknown>, preferences: RoutePreference[]): number[] {
        return preferences.map((pref) => {
            if (pref.mode === 'equals') {
                return this.normalizeComparable(values[pref.variable]) === this.normalizeComparable(this.parseRouteValue(pref.value)) ? 1 : 0;
            }

            const numericValue = this.valueAsNumber(values[pref.variable]);
            return pref.mode === 'minimize' ? -numericValue : numericValue;
        });
    }

    private isBetterState(
        candidate: { score: number[]; path: Array<{ nodeId: string; edge: RouteEdge | null }> },
        existing: { score: number[]; path: Array<{ nodeId: string; edge: RouteEdge | null }> } | undefined,
    ): boolean {
        if (!existing) return true;

        const scoreDelta = this.compareScores(candidate.score, existing.score);
        if (scoreDelta !== 0) return scoreDelta > 0;

        return candidate.path.length < existing.path.length;
    }

    private compareScores(left: number[], right: number[]): number {
        const length = Math.max(left.length, right.length);
        for (let i = 0; i < length; i++) {
            const leftValue = left[i] ?? 0;
            const rightValue = right[i] ?? 0;
            if (leftValue !== rightValue) return leftValue - rightValue;
        }

        return 0;
    }

    private parseRouteValue(raw: string | null | undefined): unknown {
        if (raw == null) return null;

        let value = String(raw).trim();
        const constantMatch = value.match(/^Constant\(value=(.*)\)$/);
        if (constantMatch) value = constantMatch[1].trim();

        if (!value) return null;

        if ((value.startsWith("'") && value.endsWith("'")) || (value.startsWith('"') && value.endsWith('"'))) {
            value = value.slice(1, -1).replace(/\\(.)/g, (_match, escaped: string) => {
                if (escaped === 'n') return '\n';
                if (escaped === 'r') return '\r';
                if (escaped === 't') return '\t';
                return escaped;
            });
        }

        if (value === 'True' || value.toLowerCase() === 'true') return true;
        if (value === 'False' || value.toLowerCase() === 'false') return false;
        if (value === 'None' || value.toLowerCase() === 'null') return null;
        if (/^-?\d+$/.test(value)) return Number.parseInt(value, 10);
        if (/^-?\d+\.\d+$/.test(value)) return Number.parseFloat(value);

        return value;
    }

    private valueAsNumber(value: unknown): number {
        if (typeof value === 'number' && Number.isFinite(value)) return value;
        if (typeof value === 'boolean') return value ? 1 : 0;
        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        return 0;
    }

    private normalizeComparable(value: unknown): string {
        if (value == null) return 'null';
        if (typeof value === 'boolean') return value ? 'true' : 'false';

        return String(value).trim().toLowerCase();
    }
}

// Singleton instance
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
