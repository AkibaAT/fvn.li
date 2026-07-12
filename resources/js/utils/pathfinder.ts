import type { RouteEdge, RouteNode, RoutePreference, RouteVariable } from '@/types/route-graph';

export interface PathfinderOptions {
    nodes?: RouteNode[];
    variables?: RouteVariable[];
    preferences?: RoutePreference[];
}

export type RoutePath = Array<{ nodeId: string; edge: RouteEdge | null }>;

type AdjacencyList = Map<string, Array<{ target: string; edge: RouteEdge }>>;

interface PreferredPathState {
    values: Record<string, unknown>;
    score: number[];
    path: RoutePath;
}

export function buildAdjacency(edges: RouteEdge[]): AdjacencyList {
    const adjacency = new Map<string, Array<{ target: string; edge: RouteEdge }>>();

    for (const edge of edges) {
        if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
        adjacency.get(edge.source)!.push({ target: edge.target, edge });
    }

    return adjacency;
}

export function findPath(startNodeId: string, targetNodeId: string, edges: RouteEdge[], options: PathfinderOptions = {}): RoutePath | null {
    if (options.preferences && options.preferences.length > 0) {
        return findPreferredPath(startNodeId, targetNodeId, edges, options);
    }

    return findShortestPath(startNodeId, targetNodeId, edges);
}

export function findShortestPath(startNodeId: string, targetNodeId: string, edges: RouteEdge[]): RoutePath | null {
    const adjacency = buildAdjacency(edges);
    const parent = new Map<string, { nodeId: string; edge: RouteEdge } | null>([[startNodeId, null]]);
    const queue: string[] = [startNodeId];

    while (queue.length > 0) {
        const current = queue.shift()!;

        if (current === targetNodeId) {
            return reconstructPath(current, parent);
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

function findPreferredPath(startNodeId: string, targetNodeId: string, edges: RouteEdge[], options: PathfinderOptions): RoutePath | null {
    const preferences = options.preferences ?? [];
    const adjacency = buildAdjacency(edges);
    const nodeMap = new Map((options.nodes ?? []).map((node) => [node.id, node]));
    const preferenceNames = new Set(preferences.map((pref) => pref.variable));
    const startValues = applyNodeChanges(getInitialValues(options.variables ?? [], preferences), nodeMap.get(startNodeId), preferenceNames);
    const bestByNode = new Map<string, PreferredPathState>([
        [
            startNodeId,
            {
                values: startValues,
                score: evaluatePreferences(startValues, preferences),
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

                const values = applyNodeChanges(state.values, nodeMap.get(target), preferenceNames);
                const candidate = {
                    values,
                    score: evaluatePreferences(values, preferences),
                    path: [...state.path, { nodeId: target, edge }],
                };

                if (isBetterState(candidate, bestByNode.get(target))) {
                    bestByNode.set(target, candidate);
                    changed = true;
                }
            }
        }

        if (!changed) break;
    }

    return bestByNode.get(targetNodeId)?.path ?? findShortestPath(startNodeId, targetNodeId, edges);
}

function reconstructPath(targetNodeId: string, parent: Map<string, { nodeId: string; edge: RouteEdge } | null>): RoutePath {
    const path: RoutePath = [];
    let node: string | null = targetNodeId;

    while (node !== null) {
        const parentInfo = parent.get(node);
        path.push({ nodeId: node, edge: parentInfo ? parentInfo.edge : null });
        node = parentInfo ? parentInfo.nodeId : null;
    }

    path.reverse();

    return path;
}

function getInitialValues(variables: RouteVariable[], preferences: RoutePreference[]): Record<string, unknown> {
    const values: Record<string, unknown> = {};
    const preferenceNames = new Set(preferences.map((pref) => pref.variable));

    for (const variable of variables) {
        if (preferenceNames.has(variable.name)) {
            values[variable.name] = parseRouteValue(variable.default_value);
        }
    }

    for (const pref of preferences) {
        if (!(pref.variable in values)) values[pref.variable] = null;
    }

    return values;
}

function applyNodeChanges(values: Record<string, unknown>, node: RouteNode | undefined, preferenceNames: Set<string>): Record<string, unknown> {
    const next = { ...values };
    if (!node?.variable_changes) return next;

    for (const change of node.variable_changes) {
        if (!preferenceNames.has(change.variable)) continue;

        const value = parseRouteValue(change.value);
        if (change.operation === '+=') {
            next[change.variable] = valueAsNumber(next[change.variable]) + valueAsNumber(value);
        } else if (change.operation === '-=') {
            next[change.variable] = valueAsNumber(next[change.variable]) - valueAsNumber(value);
        } else if (change.operation === '=') {
            next[change.variable] = value;
        }
    }

    return next;
}

function evaluatePreferences(values: Record<string, unknown>, preferences: RoutePreference[]): number[] {
    return preferences.map((pref) => {
        if (pref.mode === 'equals') {
            return normalizeComparable(values[pref.variable]) === normalizeComparable(parseRouteValue(pref.value)) ? 1 : 0;
        }

        const numericValue = valueAsNumber(values[pref.variable]);
        return pref.mode === 'minimize' ? -numericValue : numericValue;
    });
}

function isBetterState(candidate: PreferredPathState, existing: PreferredPathState | undefined): boolean {
    if (!existing) return true;

    const scoreDelta = compareScores(candidate.score, existing.score);
    if (scoreDelta !== 0) return scoreDelta > 0;

    return candidate.path.length < existing.path.length;
}

function compareScores(left: number[], right: number[]): number {
    const length = Math.max(left.length, right.length);
    for (let i = 0; i < length; i++) {
        const leftValue = left[i] ?? 0;
        const rightValue = right[i] ?? 0;
        if (leftValue !== rightValue) return leftValue - rightValue;
    }

    return 0;
}

function parseRouteValue(raw: string | null | undefined): unknown {
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

function valueAsNumber(value: unknown): number {
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    if (typeof value === 'boolean') return value ? 1 : 0;
    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    return 0;
}

function normalizeComparable(value: unknown): string {
    if (value == null) return 'null';
    if (typeof value === 'boolean') return value ? 'true' : 'false';

    return String(value).trim().toLowerCase();
}
