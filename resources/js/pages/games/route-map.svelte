<script lang="ts">
    import { SvelteFlow, Background, Controls, MiniMap } from '@xyflow/svelte';
    import '@xyflow/svelte/dist/style.css';
    import dagre from '@dagrejs/dagre';
    import { Link } from '@inertiajs/svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { RouteEdge, RouteGraphData, RouteMapPageProps, RouteNode, RouteVariable } from '@/types/route-graph';
    import http from '@/utils/http';
    import { SvelteMap, SvelteSet } from 'svelte/reactivity';

    type RoutePreference = {
        variable: string;
        mode: 'equals' | 'maximize' | 'minimize';
        value: string | null;
    };

    let { game, currentVersion, gameVersions, routeGraph: initialGraph, metaTags }: RouteMapPageProps = $props();

    let routeGraph = $state<RouteGraphData>((() => $state.snapshot(initialGraph) as RouteGraphData)());
    let selectedVersionId = $state<number>((() => $state.snapshot(currentVersion)?.id ?? 0)());
    let showSimplified = $state((() => (($state.snapshot(initialGraph) as RouteGraphData)?.total_nodes ?? 0) > 500)());
    let selectedNodeId = $state<string | null>(null);
    let isLoading = $state(false);
    let searchQuery = $state('');
    let showSidebar = $state(false);
    let navigationTarget = $state<string | null>(null);
    let routePreferences = $state<RoutePreference[]>([]);
    let preferenceVariable = $state('');
    let preferenceMode = $state<RoutePreference['mode']>('maximize');
    let preferenceValue = $state('');
    let seenNodeIds = new SvelteSet<string>();
    let isUploadingSave = $state(false);
    let saveUploadError = $state<string | null>(null);

    const seenNodeStyle = 'background:#ecfdf5;border:2px solid #10b981;border-radius:6px;box-shadow:0 0 0 1px rgba(16, 185, 129, 0.12);';
    const partiallySeenNodeStyle = 'background:#f0fdf4;border:2px solid #34d399;border-radius:6px;box-shadow:0 0 0 1px rgba(52, 211, 153, 0.12);';
    const pathNodeStyle = 'background:#eff6ff;border:2px solid #3b82f6;border-radius:6px;';
    const dimmedNodeStyle = 'opacity:0.15;';
    const mutedNodeStyle = 'opacity:0.2;';
    const highlightedEdgeStyle = 'stroke:#3b82f6;stroke-width:3;';
    const dimmedEdgeStyle = 'opacity:0.15;';
    const UNKNOWN_VALUE = Symbol('unknown-value');
    const minimapDefaultNodeColor = '#475569';
    const minimapDefaultNodeStrokeColor = '#0f172a';
    const minimapMutedNodeColor = '#cbd5e1';
    const minimapMutedNodeStrokeColor = '#94a3b8';
    const minimapSeenNodeColor = '#10b981';
    const minimapSeenNodeStrokeColor = '#047857';
    const minimapPartiallySeenNodeColor = '#34d399';
    const minimapPartiallySeenNodeStrokeColor = '#059669';
    const minimapPathNodeColor = '#2563eb';
    const minimapPathNodeStrokeColor = '#1d4ed8';
    const minimapEndingNodeColor = '#ef4444';
    const minimapEndingNodeStrokeColor = '#b91c1c';
    const minimapStartNodeColor = '#16a34a';
    const minimapStartNodeStrokeColor = '#166534';

    type ResolvedValue = string | number | boolean | null;
    type ConditionResult = true | false | 'unknown';
    type ConditionToken =
        | { type: 'paren'; value: '(' | ')' }
        | { type: 'operator'; value: '==' | '!=' | '>=' | '<=' | '>' | '<' }
        | { type: 'keyword'; value: 'and' | 'or' | 'not' }
        | { type: 'name'; value: string }
        | { type: 'literal'; value: ResolvedValue };
    type ConditionNode =
        | { type: 'literal'; value: ResolvedValue }
        | { type: 'variable'; name: string }
        | { type: 'unary'; op: 'not'; expr: ConditionNode }
        | { type: 'logical'; op: 'and' | 'or'; left: ConditionNode; right: ConditionNode }
        | { type: 'comparison'; op: '==' | '!=' | '>=' | '<=' | '>' | '<'; left: ConditionNode; right: ConditionNode };
    type ComparisonOperator = '==' | '!=' | '>=' | '<=' | '>' | '<';
    type PreferenceScore =
        | { kind: 'ordered'; value: ResolvedValue | typeof UNKNOWN_VALUE; direction: 'asc' | 'desc' }
        | { kind: 'equals'; matched: boolean };

    function isSeenNode(node: any): boolean {
        if (seenNodeIds.has(node.id)) {
            return true;
        }

        if (Array.isArray(node.data?.chain_labels)) {
            return node.data.chain_labels.some((label: string) => seenNodeIds.has(label));
        }

        return false;
    }

    function isFullySeenNode(node: any): boolean {
        if (seenNodeIds.has(node.id)) {
            return true;
        }

        if (Array.isArray(node.data?.chain_labels) && node.data.chain_labels.length > 0) {
            return node.data.chain_labels.every((label: string) => seenNodeIds.has(label));
        }

        return false;
    }

    function getSeenNodeStyle(node: any) {
        return isFullySeenNode(node) ? seenNodeStyle : partiallySeenNodeStyle;
    }

    function getMiniMapNodeColor(node: any): string {
        if (navigationTarget && displayPathNodeIds.size > 0) {
            return displayPathNodeIds.has(node.id) ? minimapPathNodeColor : minimapMutedNodeColor;
        }

        if (filteredNodeIds && !filteredNodeIds.has(node.id)) {
            return minimapMutedNodeColor;
        }

        if (node.data?.is_ending) {
            return minimapEndingNodeColor;
        }

        if (node.data?.is_start) {
            return minimapStartNodeColor;
        }

        if (isFullySeenNode(node)) {
            return minimapSeenNodeColor;
        }

        if (isSeenNode(node)) {
            return minimapPartiallySeenNodeColor;
        }

        return minimapDefaultNodeColor;
    }

    function getMiniMapNodeStrokeColor(node: any): string {
        if (navigationTarget && displayPathNodeIds.size > 0) {
            return displayPathNodeIds.has(node.id) ? minimapPathNodeStrokeColor : minimapMutedNodeStrokeColor;
        }

        if (filteredNodeIds && !filteredNodeIds.has(node.id)) {
            return minimapMutedNodeStrokeColor;
        }

        if (node.data?.is_ending) {
            return minimapEndingNodeStrokeColor;
        }

        if (node.data?.is_start) {
            return minimapStartNodeStrokeColor;
        }

        if (isFullySeenNode(node)) {
            return minimapSeenNodeStrokeColor;
        }

        if (isSeenNode(node)) {
            return minimapPartiallySeenNodeStrokeColor;
        }

        return minimapDefaultNodeStrokeColor;
    }

    function computeLayout(nodes: any[], edges: any[]): any[] {
        const g = new dagre.graphlib.Graph();
        g.setDefaultEdgeLabel(() => ({}));
        g.setGraph({ rankdir: 'TB', nodesep: 60, ranksep: 80 });

        for (const node of nodes) {
            const label = node.data?.label ?? node.id;
            const width = Math.max(180, Math.min(300, label.length * 8 + 40));
            g.setNode(node.id, { width, height: 40 });
        }

        for (const edge of edges) {
            g.setEdge(edge.source, edge.target);
        }

        dagre.layout(g);

        return nodes.map((node) => {
            const pos = g.node(node.id);

            return {
                ...node,
                position: {
                    x: pos.x - pos.width / 2,
                    y: pos.y - pos.height / 2,
                },
            };
        });
    }

    function decodeQuotedString(raw: string): string {
        const quote = raw[0];
        const inner = raw.slice(1, -1);

        return inner.replace(/\\(.)/g, (_match, escaped) => {
            if (escaped === quote || escaped === '\\') return escaped;
            if (escaped === 'n') return '\n';
            if (escaped === 'r') return '\r';
            if (escaped === 't') return '\t';
            return escaped;
        });
    }

    function parseLiteralValue(raw: string | null | undefined): ResolvedValue | typeof UNKNOWN_VALUE {
        if (raw == null) return null;

        const constantMatch = raw.match(/^Constant\(value=(.*)\)$/);
        const value = (constantMatch ? constantMatch[1] : raw).trim();

        if (!value.length) return UNKNOWN_VALUE;

        if ((value.startsWith("'") && value.endsWith("'")) || (value.startsWith('"') && value.endsWith('"'))) {
            return decodeQuotedString(value);
        }

        if (value === 'True') return true;
        if (value === 'False') return false;
        if (value === 'None') return null;

        if (/^-?\d+$/.test(value)) return Number.parseInt(value, 10);
        if (/^-?\d+\.\d+$/.test(value)) return Number.parseFloat(value);

        return UNKNOWN_VALUE;
    }

    function _normalizeVariableValue(raw: string | null | undefined): string | null {
        const parsed = parseLiteralValue(raw);
        if (parsed === UNKNOWN_VALUE) return raw?.trim() ?? null;
        if (parsed == null) return null;
        return String(parsed);
    }

    function valuesEqual(left: ResolvedValue | typeof UNKNOWN_VALUE, right: ResolvedValue | typeof UNKNOWN_VALUE): boolean {
        if (left === UNKNOWN_VALUE || right === UNKNOWN_VALUE) return false;
        return left === right;
    }

    function compareResolvedValues(left: ResolvedValue | typeof UNKNOWN_VALUE, right: ResolvedValue | typeof UNKNOWN_VALUE): number {
        if (left === UNKNOWN_VALUE && right === UNKNOWN_VALUE) return 0;
        if (left === UNKNOWN_VALUE) return -1;
        if (right === UNKNOWN_VALUE) return 1;
        if (left === right) return 0;

        if (typeof left === 'number' && typeof right === 'number') {
            return left - right;
        }

        return String(left).localeCompare(String(right), undefined, { sensitivity: 'base' });
    }

    function formatRoutePreference(pref: RoutePreference): string {
        if (pref.mode === 'maximize') return `${pref.variable} max`;
        if (pref.mode === 'minimize') return `${pref.variable} min`;
        return `${pref.variable} = ${pref.value}`;
    }

    function serializeResolvedValue(value: ResolvedValue | typeof UNKNOWN_VALUE): string {
        if (value === UNKNOWN_VALUE) return '?';
        if (value === null) return 'null';
        return String(value);
    }

    function tokenizeCondition(condition: string): ConditionToken[] | null {
        const tokens: ConditionToken[] = [];
        let index = 0;

        while (index < condition.length) {
            const remaining = condition.slice(index);
            const whitespace = remaining.match(/^\s+/);
            if (whitespace) {
                index += whitespace[0].length;
                continue;
            }

            const operator = remaining.match(/^(==|!=|>=|<=|>|<)/);
            if (operator) {
                tokens.push({ type: 'operator', value: operator[1] as ComparisonOperator });
                index += operator[1].length;
                continue;
            }

            const paren = remaining.match(/^[()]/);
            if (paren) {
                tokens.push({ type: 'paren', value: paren[0] as '(' | ')' });
                index += 1;
                continue;
            }

            const stringLiteral = remaining.match(/^'(?:\\.|[^'\\])*'|^"(?:\\.|[^"\\])*"/);
            if (stringLiteral) {
                const value = parseLiteralValue(stringLiteral[0]);
                if (value === UNKNOWN_VALUE) return null;
                tokens.push({ type: 'literal', value });
                index += stringLiteral[0].length;
                continue;
            }

            const numberLiteral = remaining.match(/^-?\d+(?:\.\d+)?/);
            if (numberLiteral) {
                const value = parseLiteralValue(numberLiteral[0]);
                if (value === UNKNOWN_VALUE) return null;
                tokens.push({ type: 'literal', value });
                index += numberLiteral[0].length;
                continue;
            }

            const identifier = remaining.match(/^[A-Za-z_][A-Za-z0-9_.]*/);
            if (identifier) {
                const value = identifier[0];
                if (value === 'True' || value === 'False' || value === 'None') {
                    const literal = parseLiteralValue(value);
                    if (literal === UNKNOWN_VALUE) return null;
                    tokens.push({ type: 'literal', value: literal });
                } else if (value === 'and' || value === 'or' || value === 'not') {
                    tokens.push({ type: 'keyword', value });
                } else {
                    tokens.push({ type: 'name', value });
                }
                index += value.length;
                continue;
            }

            return null;
        }

        return tokens;
    }

    function parseCondition(condition: string): ConditionNode | null {
        const tokens = tokenizeCondition(condition);
        if (!tokens?.length) return null;

        let index = 0;

        const parseExpression = (): ConditionNode | null => parseOr();

        const parseOr = (): ConditionNode | null => {
            let left = parseAnd();
            if (!left) return null;

            while (tokens[index]?.type === 'keyword' && tokens[index].value === 'or') {
                index += 1;
                const right = parseAnd();
                if (!right) return null;
                left = { type: 'logical', op: 'or', left, right };
            }

            return left;
        };

        const parseAnd = (): ConditionNode | null => {
            let left = parseNot();
            if (!left) return null;

            while (tokens[index]?.type === 'keyword' && tokens[index].value === 'and') {
                index += 1;
                const right = parseNot();
                if (!right) return null;
                left = { type: 'logical', op: 'and', left, right };
            }

            return left;
        };

        const parseNot = (): ConditionNode | null => {
            if (tokens[index]?.type === 'keyword' && tokens[index].value === 'not') {
                index += 1;
                const expr = parseNot();
                if (!expr) return null;
                return { type: 'unary', op: 'not', expr };
            }

            return parseComparison();
        };

        const parseComparison = (): ConditionNode | null => {
            const left = parsePrimary();
            if (!left) return null;

            if (tokens[index]?.type === 'operator') {
                const op = tokens[index].value as ComparisonOperator;
                index += 1;
                const right = parsePrimary();
                if (!right) return null;
                return { type: 'comparison', op, left, right };
            }

            return left;
        };

        const parsePrimary = (): ConditionNode | null => {
            const token = tokens[index];
            if (!token) return null;

            if (token.type === 'paren' && token.value === '(') {
                index += 1;
                const expr = parseExpression();
                if (!expr || tokens[index]?.type !== 'paren' || tokens[index]?.value !== ')') {
                    return null;
                }
                index += 1;
                return expr;
            }

            index += 1;

            if (token.type === 'literal') {
                return { type: 'literal', value: token.value };
            }

            if (token.type === 'name') {
                return { type: 'variable', name: token.value };
            }

            return null;
        };

        const parsed = parseExpression();
        if (!parsed || index !== tokens.length) return null;
        return parsed;
    }

    function collectConditionVariables(node: ConditionNode, names: Set<string>): void {
        if (node.type === 'variable') {
            names.add(node.name);
            return;
        }

        if (node.type === 'unary') {
            collectConditionVariables(node.expr, names);
            return;
        }

        if (node.type === 'logical' || node.type === 'comparison') {
            collectConditionVariables(node.left, names);
            collectConditionVariables(node.right, names);
        }
    }

    function truthyState(value: ResolvedValue | typeof UNKNOWN_VALUE): boolean | null {
        if (value === UNKNOWN_VALUE) return null;
        return Boolean(value);
    }

    function compareValues(
        left: ResolvedValue | typeof UNKNOWN_VALUE,
        right: ResolvedValue | typeof UNKNOWN_VALUE,
        operator: '==' | '!=' | '>=' | '<=' | '>' | '<',
    ): boolean | null {
        if (left === UNKNOWN_VALUE || right === UNKNOWN_VALUE) return null;

        switch (operator) {
            case '==':
                return left === right;
            case '!=':
                return left !== right;
            case '>':
                return typeof left === 'number' && typeof right === 'number' ? left > right : null;
            case '<':
                return typeof left === 'number' && typeof right === 'number' ? left < right : null;
            case '>=':
                return typeof left === 'number' && typeof right === 'number' ? left >= right : null;
            case '<=':
                return typeof left === 'number' && typeof right === 'number' ? left <= right : null;
        }
    }

    function evaluateConditionNode(node: ConditionNode, assignments: Map<string, ResolvedValue>): ConditionResult {
        if (node.type === 'literal') {
            const result = truthyState(node.value);
            return result === null ? 'unknown' : result;
        }

        if (node.type === 'variable') {
            const result = truthyState(assignments.has(node.name) ? assignments.get(node.name)! : UNKNOWN_VALUE);
            return result === null ? 'unknown' : result;
        }

        if (node.type === 'unary') {
            const result = evaluateConditionNode(node.expr, assignments);
            return result === 'unknown' ? 'unknown' : !result;
        }

        if (node.type === 'comparison') {
            const leftValue = evaluateConditionValue(node.left, assignments);
            const rightValue = evaluateConditionValue(node.right, assignments);
            const result = compareValues(leftValue, rightValue, node.op);
            return result === null ? 'unknown' : result;
        }

        const left = evaluateConditionNode(node.left, assignments);
        const right = evaluateConditionNode(node.right, assignments);

        if (node.op === 'and') {
            if (left === false || right === false) return false;
            if (left === 'unknown' || right === 'unknown') return 'unknown';
            return true;
        }

        if (left === true || right === true) return true;
        if (left === 'unknown' || right === 'unknown') return 'unknown';
        return false;
    }

    function evaluateConditionValue(node: ConditionNode, assignments: Map<string, ResolvedValue>): ResolvedValue | typeof UNKNOWN_VALUE {
        if (node.type === 'literal') return node.value;
        if (node.type === 'variable') return assignments.has(node.name) ? assignments.get(node.name)! : UNKNOWN_VALUE;

        const booleanResult = evaluateConditionNode(node, assignments);
        if (booleanResult === 'unknown') return UNKNOWN_VALUE;
        return booleanResult;
    }

    function _evaluateEdgeCondition(edge: RouteEdge, assignments: Map<string, ResolvedValue>): ConditionResult {
        if (!edge.condition?.trim()) return true;

        const parsed = parseCondition(edge.condition);
        if (!parsed) return 'unknown';

        return evaluateConditionNode(parsed, assignments);
    }

    function findPreferredPath(
        nodes: RouteNode[],
        edges: RouteEdge[],
        graphVariables: RouteVariable[],
        fromId: string,
        toId: string,
    ): Array<{ nodeId: string; edge: RouteEdge | null }> | null {
        if (fromId === toId) return [{ nodeId: fromId, edge: null }];
        if (!nodes?.length || !edges?.length) return null;

        const nodeMap = new SvelteMap(nodes.map((node) => [node.id, node]));
        const adjacency = new SvelteMap<string, Array<{ target: string; edge: RouteEdge }>>();
        const parsedConditionCache = new SvelteMap<string, ConditionNode | null>();
        const trackedVariableNames = new SvelteSet(routePreferences.map((pref) => pref.variable));

        for (const edge of edges) {
            if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
            adjacency.get(edge.source)!.push({ target: edge.target, edge });

            if (edge.condition?.trim()) {
                const parsed = parseCondition(edge.condition);
                parsedConditionCache.set(edge.condition, parsed);
                if (parsed) {
                    collectConditionVariables(parsed, trackedVariableNames);
                }
            }
        }

        const initialAssignments = new SvelteMap<string, ResolvedValue>();
        const trackedVariableList = [...trackedVariableNames].sort((left, right) => left.localeCompare(right, undefined, { sensitivity: 'base' }));

        for (const variable of graphVariables) {
            if (!trackedVariableNames.has(variable.name)) {
                continue;
            }
            const parsedDefault = parseLiteralValue(variable.default_value);
            if (parsedDefault !== UNKNOWN_VALUE) {
                initialAssignments.set(variable.name, parsedDefault);
            }
        }

        const applyNodeChanges = (nodeId: string, previous: Map<string, ResolvedValue>) => {
            const next = new SvelteMap(previous);
            const node = nodeMap.get(nodeId);

            for (const change of node?.variable_changes ?? []) {
                if (!trackedVariableNames.has(change.variable)) {
                    continue;
                }
                if (change.operation !== '=') {
                    next.delete(change.variable);
                    continue;
                }

                const parsedValue = parseLiteralValue(change.value);
                if (parsedValue === UNKNOWN_VALUE) {
                    next.delete(change.variable);
                    continue;
                }

                next.set(change.variable, parsedValue);
            }

            return next;
        };

        const getPreferenceVector = (assignments: Map<string, ResolvedValue>): PreferenceScore[] =>
            routePreferences.map((pref) => {
                const assignedValue = assignments.has(pref.variable) ? assignments.get(pref.variable)! : UNKNOWN_VALUE;

                if (pref.mode === 'maximize') {
                    return {
                        kind: 'ordered' as const,
                        value: assignedValue,
                        direction: 'desc' as const,
                    };
                }

                if (pref.mode === 'minimize') {
                    return {
                        kind: 'ordered' as const,
                        value: assignedValue,
                        direction: 'asc' as const,
                    };
                }

                const preferredValue = parseLiteralValue(pref.value);
                return {
                    kind: 'equals' as const,
                    matched: valuesEqual(assignedValue, preferredValue),
                };
            });

        const serializeAssignments = (assignments: Map<string, ResolvedValue>) =>
            trackedVariableList
                .map((name) => `${name}:${serializeResolvedValue(assignments.has(name) ? assignments.get(name)! : UNKNOWN_VALUE)}`)
                .join('|');

        const seededAssignments = applyNodeChanges(fromId, initialAssignments);

        const queue: Array<{
            nodeId: string;
            assignments: Map<string, ResolvedValue>;
            unknownConditionCount: number;
            path: Array<{ nodeId: string; edge: RouteEdge | null }>;
        }> = [{ nodeId: fromId, assignments: seededAssignments, unknownConditionCount: 0, path: [{ nodeId: fromId, edge: null }] }];

        const bestStateCosts = new SvelteMap<string, { unknownConditionCount: number; steps: number }>([
            [`${fromId}|${serializeAssignments(seededAssignments)}`, { unknownConditionCount: 0, steps: 0 }],
        ]);

        let bestTarget: {
            preferences: PreferenceScore[];
            unknownConditionCount: number;
            steps: number;
            path: Array<{ nodeId: string; edge: RouteEdge | null }>;
        } | null = null;

        let queueIndex = 0;

        while (queueIndex < queue.length) {
            const current = queue[queueIndex++]!;
            const steps = current.path.length - 1;

            if (current.nodeId === toId) {
                const preferences = getPreferenceVector(current.assignments);

                if (
                    !bestTarget ||
                    comparePreferenceVectors(preferences, bestTarget.preferences) < 0 ||
                    (comparePreferenceVectors(preferences, bestTarget.preferences) === 0 &&
                        (current.unknownConditionCount < bestTarget.unknownConditionCount ||
                            (current.unknownConditionCount === bestTarget.unknownConditionCount && steps < bestTarget.steps)))
                ) {
                    bestTarget = {
                        preferences,
                        unknownConditionCount: current.unknownConditionCount,
                        steps,
                        path: current.path,
                    };
                }

                continue;
            }

            for (const { target, edge } of adjacency.get(current.nodeId) ?? []) {
                let conditionResult: ConditionResult = true;

                if (edge.condition?.trim()) {
                    const parsed = parsedConditionCache.get(edge.condition) ?? null;
                    conditionResult = parsed ? evaluateConditionNode(parsed, current.assignments) : 'unknown';
                }

                if (conditionResult === false) {
                    continue;
                }

                const nextAssignments = applyNodeChanges(target, current.assignments);
                const nextPath = [...current.path, { nodeId: target, edge }];
                const nextSteps = nextPath.length - 1;
                const nextUnknownConditionCount = current.unknownConditionCount + (conditionResult === 'unknown' ? 1 : 0);
                const stateKey = `${target}|${serializeAssignments(nextAssignments)}`;
                const previousBest = bestStateCosts.get(stateKey);

                if (
                    previousBest !== undefined &&
                    (previousBest.unknownConditionCount < nextUnknownConditionCount ||
                        (previousBest.unknownConditionCount === nextUnknownConditionCount && previousBest.steps <= nextSteps))
                ) {
                    continue;
                }

                bestStateCosts.set(stateKey, {
                    unknownConditionCount: nextUnknownConditionCount,
                    steps: nextSteps,
                });
                queue.push({
                    nodeId: target,
                    assignments: nextAssignments,
                    unknownConditionCount: nextUnknownConditionCount,
                    path: nextPath,
                });
            }
        }

        return bestTarget?.path ?? null;
    }

    function comparePreferenceVectors(left: PreferenceScore[], right: PreferenceScore[]): number {
        const maxLength = Math.max(left.length, right.length);

        for (let i = 0; i < maxLength; i++) {
            const leftItem = left[i];
            const rightItem = right[i];

            if (!leftItem || !rightItem) continue;

            if (leftItem.kind === 'equals' && rightItem.kind === 'equals') {
                if (leftItem.matched !== rightItem.matched) {
                    return leftItem.matched ? -1 : 1;
                }
                continue;
            }

            if (leftItem.kind === 'ordered' && rightItem.kind === 'ordered') {
                const comparison = compareResolvedValues(leftItem.value, rightItem.value);
                if (comparison !== 0) {
                    return leftItem.direction === 'desc' ? -comparison : comparison;
                }
            }
        }

        return 0;
    }

    let currentEdges = $derived.by(() => {
        if (!routeGraph?.has_graph_data) return [];

        const sourceEdges = showSimplified ? routeGraph.simplified?.edges : routeGraph.edges;
        return Array.isArray(sourceEdges) ? sourceEdges : [];
    });

    let activeEdges = $derived.by(() => {
        return currentEdges.map((edge: any) => ({
            id: edge.id,
            source: edge.source,
            target: edge.target,
            type: edge.edge_type === 'menu_choice' ? 'smoothstep' : 'default',
            animated: edge.edge_type === 'menu_choice',
            label: edge.choice_text || undefined,
            data: { ...edge },
        }));
    });

    let currentNodes = $derived.by(() => {
        if (!routeGraph?.has_graph_data) return [];

        const sourceNodes = showSimplified ? routeGraph.simplified?.nodes : routeGraph.nodes;
        return Array.isArray(sourceNodes) ? sourceNodes : [];
    });

    let activeNodes = $derived.by(() => {
        const nodes = currentNodes.map((node: any) => ({
            id: node.id,
            data: {
                ...node,
                label: node.label,
            },
            position: { x: 0, y: 0 },
        }));

        return computeLayout(nodes, activeEdges);
    });

    let selectedNodeData = $derived.by(() => {
        if (!selectedNodeId) return null;
        return (currentNodes as any[]).find((node: any) => node.id === selectedNodeId) ?? null;
    });

    let fullNodes = $derived(routeGraph?.nodes ?? []);
    let fullEdges = $derived(routeGraph?.edges ?? []);

    let endings = $derived(routeGraph?.endings ?? []);
    let variables = $derived(routeGraph?.variables ?? []);

    let routePlanningVariables = $derived.by(() => {
        const ignoredPrefixPatterns = [/^_/, /^(persistent|config|gui|preferences|renpy|store)\b/i];
        const ignoredExactNames = new SvelteSet(['quick_menu', 'main_menu', 'tooltip', 'history', 'confirm', 'skip_indicator', 'nvl_mode']);

        return [...variables]
            .filter((variable) => {
                const name = variable.name?.trim();
                if (!name) return false;
                if (variable.change_count <= 0) return false;
                if (ignoredExactNames.has(name)) return false;
                if (ignoredPrefixPatterns.some((pattern) => pattern.test(name))) return false;
                return true;
            })
            .sort((left, right) => left.name.localeCompare(right.name, undefined, { sensitivity: 'base' }));
    });

    let startNodeId = $derived.by(() => {
        return (routeGraph?.nodes as any[] | undefined)?.find((n) => n.is_start)?.id ?? null;
    });

    let navigationPath = $derived.by(() => {
        if (!navigationTarget || !startNodeId) return null;
        return findPreferredPath(fullNodes, fullEdges, variables, startNodeId, navigationTarget);
    });

    let displayPathNodeIds = $derived.by(() => {
        if (!navigationPath) return new Set<string>();

        if (!showSimplified) {
            return new SvelteSet(navigationPath.map((s) => s.nodeId));
        }

        const ids = new SvelteSet<string>();
        for (const step of navigationPath) {
            for (const node of currentNodes as any[]) {
                if (node.id === step.nodeId || node.chain_labels?.includes(step.nodeId)) {
                    ids.add(node.id);
                }
            }
        }

        return ids;
    });

    let displayPathEdgeIds = $derived.by(() => {
        if (!navigationPath) return new Set<string>();

        if (!showSimplified) {
            return new SvelteSet(navigationPath.filter((s): s is { nodeId: string; edge: RouteEdge } => s.edge !== null).map((s) => s.edge.id));
        }

        const labelToDisplayId = new SvelteMap<string, string>();
        for (const node of currentNodes as any[]) {
            if (node.id) {
                labelToDisplayId.set(node.id, node.id);
            }
            for (const label of node.chain_labels ?? []) {
                labelToDisplayId.set(label, node.id);
            }
        }

        const mappedNodeIds = navigationPath
            .map((step) => labelToDisplayId.get(step.nodeId))
            .filter((id): id is string => Boolean(id))
            .filter((id, index, arr) => index === 0 || arr[index - 1] !== id);

        const ids = new SvelteSet<string>();
        for (let i = 1; i < mappedNodeIds.length; i++) {
            const source = mappedNodeIds[i - 1];
            const target = mappedNodeIds[i];

            const edge = activeEdges.find((candidate: any) => candidate.source === source && candidate.target === target);
            if (edge) {
                ids.add(edge.id);
            }
        }

        return ids;
    });

    let navigationSteps = $derived.by(() => {
        if (!navigationPath || navigationPath.length <= 1) return [];

        return navigationPath.slice(1).map((step, i) => {
            const edge = step.edge;
            const isChoice = edge?.edge_type === 'menu_choice';
            const condition = edge?.condition?.trim() || null;

            return {
                step: i + 1,
                nodeId: step.nodeId,
                edgeType: edge?.edge_type ?? 'flow',
                isChoice,
                choiceText: edge?.choice_text ?? null,
                condition,
                targetIsEnding: routeGraph?.endings?.includes(step.nodeId) ?? false,
            };
        });
    });

    let choiceCount = $derived(navigationSteps.filter((s) => s.isChoice).length);
    let conditionedStepCount = $derived(navigationSteps.filter((s) => s.condition).length);

    async function loadVersion(versionId: number) {
        if (versionId === selectedVersionId) return;

        isLoading = true;

        try {
            const res = await http.get(
                route('react-api.games.version.route-graph', {
                    game: game.slug,
                    version: versionId,
                }),
            );

            routeGraph = res.data;
            selectedVersionId = versionId;
            showSimplified = (res.data?.total_nodes ?? 0) > 500;
            selectedNodeId = null;
            navigationTarget = null;
            routePreferences = [];
            preferenceVariable = '';
            preferenceMode = 'maximize';
            preferenceValue = '';
            seenNodeIds.clear();
            saveUploadError = null;
        } finally {
            isLoading = false;
        }
    }

    async function uploadSaveFile(file: File) {
        isUploadingSave = true;
        saveUploadError = null;

        try {
            const formData = new FormData();
            formData.append('file', file);

            const res = await http.post(
                route('react-api.games.version.parse-save', {
                    game: game.slug,
                    version: selectedVersionId,
                }),
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } },
            );

            seenNodeIds.clear();
            for (const label of res.data.seen_labels) {
                seenNodeIds.add(label);
            }
        } catch (e: any) {
            saveUploadError = e?.response?.data?.message ?? 'Failed to parse save file';
        } finally {
            isUploadingSave = false;
        }
    }

    let filteredNodeIds = $derived.by(() => {
        if (!searchQuery.trim() || !routeGraph?.nodes) return null;

        const query = searchQuery.toLowerCase();
        const ids = new SvelteSet<string>();

        for (const node of routeGraph.nodes as any[]) {
            if (node.label?.toLowerCase().includes(query)) {
                ids.add(node.id);
            }
        }

        return ids;
    });

    let displayNodes = $derived.by(() => {
        if (navigationTarget && displayPathNodeIds.size > 0) {
            return activeNodes.map((node: any) => {
                if (displayPathNodeIds.has(node.id)) {
                    return {
                        ...node,
                        style: pathNodeStyle,
                    };
                }
                return {
                    ...node,
                    style: dimmedNodeStyle,
                };
            });
        }

        if (seenNodeIds.size > 0 && filteredNodeIds) {
            return activeNodes.map((node: any) => {
                const matchesSearch = filteredNodeIds.has(node.id);
                const isSeen = isSeenNode(node);

                if (!matchesSearch) {
                    return { ...node, style: mutedNodeStyle, class: 'opacity-20' };
                }

                if (isSeen) {
                    return {
                        ...node,
                        style: getSeenNodeStyle(node),
                    };
                }

                return node;
            });
        }

        if (filteredNodeIds) {
            return activeNodes.map((node: any) => ({
                ...node,
                style: filteredNodeIds.has(node.id) ? undefined : mutedNodeStyle,
                class: filteredNodeIds.has(node.id) ? undefined : 'opacity-20',
            }));
        }

        if (seenNodeIds.size > 0) {
            return activeNodes.map((node: any) => {
                if (isSeenNode(node)) {
                    return {
                        ...node,
                        style: getSeenNodeStyle(node),
                    };
                }
                return node;
            });
        }

        return activeNodes;
    });

    let displayEdges = $derived.by(() => {
        if (!navigationTarget || displayPathEdgeIds.size === 0) return activeEdges;

        return activeEdges.map((edge: any) => {
            if (displayPathEdgeIds.has(edge.id)) {
                return {
                    ...edge,
                    style: highlightedEdgeStyle,
                    animated: true,
                };
            }
            return {
                ...edge,
                style: dimmedEdgeStyle,
            };
        });
    });
</script>

<SeoHead {metaTags} />

<div class="px-4 py-4 sm:px-6">
    <div class="mb-6 flex items-center gap-4">
        <Link href={route('games.show', { game: game.slug })} class="text-sm text-gray-500 transition-colors hover:text-gray-300">
            &larr; Back to {game.name}
        </Link>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Route Map</h1>
    </div>

    {#if !routeGraph?.has_graph_data}
        <div class="flex flex-col items-center justify-center py-20">
            <div class="text-lg text-gray-500 dark:text-gray-400">Route graph data is generated when the game is parsed.</div>
            <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">This view will appear after the parser has produced route graph data.</p>
        </div>
    {:else}
        <div class="mb-4 flex flex-wrap items-center gap-3">
            {#if gameVersions && gameVersions.length > 1}
                <select
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    value={selectedVersionId}
                    onchange={(e) => {
                        const target = e.target as HTMLSelectElement;
                        loadVersion(Number(target.value));
                    }}
                    disabled={isLoading}
                >
                    {#each gameVersions as v (v.id)}
                        <option value={v.id} selected={v.id === selectedVersionId}>
                            v{v.version}
                        </option>
                    {/each}
                </select>
            {/if}

            {#if (routeGraph.total_nodes ?? 0) > 500}
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={showSimplified}
                        onchange={() => {
                            showSimplified = !showSimplified;
                        }}
                        class="rounded border-gray-300"
                    />
                    <span class="text-gray-600 dark:text-gray-400">Simplified</span>
                </label>
            {/if}

            <div class="relative">
                <input
                    type="text"
                    placeholder="Search nodes..."
                    bind:value={searchQuery}
                    class="w-48 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                />
            </div>

            <button
                class="rounded-lg border px-2 py-1.5 transition-colors {showSidebar
                    ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'}"
                onclick={() => (showSidebar = !showSidebar)}
                title={showSidebar ? 'Hide details' : 'Show details'}
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5z" />
                    <path d="M15 3v18" />
                </svg>
            </button>

            <div class="relative flex items-center gap-2">
                <button
                    class="rounded-lg border px-2 py-1.5 transition-colors {seenNodeIds.size > 0
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'
                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'}"
                    onclick={() => document.getElementById('save-upload')?.click()}
                    disabled={isUploadingSave}
                    title="Upload Ren'Py save or persistent file to mark seen nodes"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                </button>
                <input
                    id="save-upload"
                    type="file"
                    accept="*"
                    class="hidden"
                    onchange={(e) => {
                        const target = e.target as HTMLInputElement;
                        if (target.files?.[0]) {
                            uploadSaveFile(target.files[0]);
                            target.value = '';
                        }
                    }}
                />

                {#if seenNodeIds.size > 0}
                    <span class="text-xs text-emerald-600 dark:text-emerald-400">
                        {seenNodeIds.size}/{routeGraph.total_nodes} seen
                    </span>
                    <button
                        class="text-xs text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300"
                        onclick={() => {
                            seenNodeIds.clear();
                            saveUploadError = null;
                        }}
                        title="Clear seen data"
                    >
                        clear
                    </button>
                {/if}

                {#if saveUploadError}
                    <span class="text-xs text-red-500 dark:text-red-400">{saveUploadError}</span>
                {/if}
            </div>

            <div class="flex gap-3 text-xs">
                <span class="text-gray-500 dark:text-gray-400">
                    {routeGraph.total_nodes} nodes, {routeGraph.total_edges} edges
                </span>

                {#if endings.length > 0}
                    <span class="text-gray-500 dark:text-gray-400">&middot;</span>
                    <span class="text-red-500 dark:text-red-400">{endings.length} endings</span>
                {/if}

                {#if isLoading}
                    <span class="text-xs text-gray-400">Loading...</span>
                {/if}
            </div>
        </div>

        <div class="flex gap-6" style="height: calc(100vh - 200px);">
            <div class="flex-1 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" style="min-width: 0">
                <SvelteFlow
                    nodes={displayNodes}
                    edges={displayEdges}
                    fitView
                    onnodeclick={(event: any) => {
                        selectedNodeId = event.node?.id ?? null;
                        showSidebar = true;
                    }}
                    class="bg-gray-50 dark:bg-gray-800"
                >
                    <Background />
                    <Controls />
                    <MiniMap
                        class="route-map-minimap"
                        width={260}
                        height={180}
                        bgColor="#f8fafc"
                        maskColor="rgba(15, 23, 42, 0.08)"
                        maskStrokeColor="#2563eb"
                        maskStrokeWidth={2}
                        nodeColor={getMiniMapNodeColor}
                        nodeStrokeColor={getMiniMapNodeStrokeColor}
                        nodeStrokeWidth={3}
                        nodeBorderRadius={3}
                        pannable
                        zoomable
                        ariaLabel="Route map overview"
                    />
                </SvelteFlow>
            </div>

            {#if showSidebar}
                <div class="w-72 shrink-0 overflow-y-auto rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    {#if navigationTarget}
                        <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Path to <span class="font-mono text-xs">{navigationTarget}</span>
                                </h3>
                                <button
                                    onclick={() => (navigationTarget = null)}
                                    class="rounded p-0.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                                    title="Clear path"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {#if navigationPath}
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {navigationSteps.length} steps{#if choiceCount > 0}
                                        &middot; {choiceCount} choice{choiceCount !== 1 ? 's' : ''}{/if}
                                    {#if conditionedStepCount > 0}
                                        &middot; {conditionedStepCount} condition{conditionedStepCount !== 1 ? 's' : ''}{/if}
                                </p>

                                {#if routePreferences.length > 0}
                                    <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                        prioritizing {routePreferences.map((pref) => formatRoutePreference(pref)).join(', ')}
                                    </p>
                                {/if}

                                <div class="mt-3 max-h-72 space-y-0.5 overflow-y-auto">
                                    <button
                                        class="flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-xs text-gray-600 transition-colors hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800"
                                        onclick={() => (selectedNodeId = startNodeId)}
                                    >
                                        <span
                                            class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700 dark:bg-green-900/40 dark:text-green-400"
                                            >S</span
                                        >
                                        <span class="font-mono">{startNodeId}</span>
                                    </button>

                                    {#each navigationSteps as step (step.nodeId)}
                                        <div class="flex items-stretch gap-1.5">
                                            <div class="flex w-4 shrink-0 justify-center">
                                                <div class="w-px bg-gray-200 dark:bg-gray-700"></div>
                                            </div>
                                            <button
                                                class="flex-1 rounded px-2 py-1 text-left text-xs transition-colors {step.isChoice
                                                    ? 'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50'
                                                    : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'}"
                                                onclick={() => (selectedNodeId = step.nodeId)}
                                            >
                                                {#if step.isChoice && step.choiceText}
                                                    <span class="font-medium">Select &ldquo;{step.choiceText}&rdquo;</span>
                                                {:else if step.edgeType === 'jump'}
                                                    <span class="text-gray-400">↪</span> <span class="font-mono">{step.nodeId}</span>
                                                {:else if step.edgeType === 'call'}
                                                    <span class="text-gray-400">↩</span> <span class="font-mono">{step.nodeId}</span>
                                                {:else}
                                                    <span class="text-gray-400">→</span> <span class="font-mono">{step.nodeId}</span>
                                                {/if}

                                                {#if step.targetIsEnding}
                                                    <span
                                                        class="ml-1 rounded bg-red-100 px-1 py-0.5 text-[10px] font-medium text-red-600 dark:bg-red-900/30 dark:text-red-400"
                                                        >ending</span
                                                    >
                                                {/if}

                                                {#if step.condition}
                                                    <div
                                                        class="mt-1 rounded bg-amber-50 px-1.5 py-1 text-[10px] text-amber-700 dark:bg-amber-900/20 dark:text-amber-300"
                                                    >
                                                        requires: <span class="font-mono">{step.condition}</span>
                                                    </div>
                                                {/if}
                                            </button>
                                        </div>
                                    {/each}
                                </div>
                            {:else if startNodeId}
                                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                    No path found from {startNodeId} to {navigationTarget}
                                </p>
                            {:else}
                                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">No start node found in this graph</p>
                            {/if}
                        </div>
                    {/if}

                    <div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Route Priorities</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Earlier preferences win over later ones. Path length is only used as a tiebreaker.
                        </p>

                        <div class="mt-3 space-y-2">
                            {#each routePreferences as pref, index (`${pref.variable}:${pref.mode}:${pref.value ?? ''}:${index}`)}
                                <div class="rounded border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-gray-700 dark:text-gray-300">
                                            {formatRoutePreference(pref)}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <button
                                                class="rounded px-1 py-0.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                                                onclick={() => {
                                                    if (index === 0) return;
                                                    const next = [...routePreferences];
                                                    [next[index - 1], next[index]] = [next[index], next[index - 1]];
                                                    routePreferences = next;
                                                }}
                                                title="Increase priority"
                                            >
                                                ↑
                                            </button>
                                            <button
                                                class="rounded px-1 py-0.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                                                onclick={() => {
                                                    if (index === routePreferences.length - 1) return;
                                                    const next = [...routePreferences];
                                                    [next[index], next[index + 1]] = [next[index + 1], next[index]];
                                                    routePreferences = next;
                                                }}
                                                title="Decrease priority"
                                            >
                                                ↓
                                            </button>
                                            <button
                                                class="rounded px-1 py-0.5 text-red-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                                                onclick={() => {
                                                    routePreferences = routePreferences.filter((_, currentIndex) => currentIndex !== index);
                                                }}
                                                title="Remove priority"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            {/each}
                        </div>

                        <div class="mt-3 space-y-2">
                            <select
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                bind:value={preferenceVariable}
                            >
                                <option value="">Select variable…</option>
                                {#each routePlanningVariables as variable (variable.name)}
                                    <option value={variable.name}>{variable.name}</option>
                                {/each}
                            </select>

                            <select
                                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                bind:value={preferenceMode}
                            >
                                <option value="maximize">Maximize value</option>
                                <option value="minimize">Minimize value</option>
                                <option value="equals">Match exact value</option>
                            </select>

                            {#if preferenceMode === 'equals'}
                                <input
                                    type="text"
                                    class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                    placeholder="Desired value"
                                    bind:value={preferenceValue}
                                />
                            {/if}

                            <div class="flex gap-2">
                                <button
                                    class="flex-1 rounded bg-emerald-500 px-2 py-1.5 text-xs font-medium text-white transition-colors hover:bg-emerald-600 disabled:opacity-50"
                                    onclick={() => {
                                        const variable = preferenceVariable.trim();
                                        const value = preferenceValue.trim();
                                        if (!variable) return;
                                        if (preferenceMode === 'equals' && !value) return;

                                        routePreferences = [
                                            ...routePreferences.filter((pref) => pref.variable !== variable),
                                            { variable, mode: preferenceMode, value: preferenceMode === 'equals' ? value : null },
                                        ];
                                        preferenceVariable = '';
                                        preferenceMode = 'maximize';
                                        preferenceValue = '';
                                    }}
                                    disabled={!preferenceVariable.trim() || (preferenceMode === 'equals' && !preferenceValue.trim())}
                                >
                                    Add priority
                                </button>

                                {#if routePreferences.length > 0}
                                    <button
                                        class="rounded border border-gray-300 px-2 py-1.5 text-xs text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                        onclick={() => {
                                            routePreferences = [];
                                            preferenceVariable = '';
                                            preferenceMode = 'maximize';
                                            preferenceValue = '';
                                        }}
                                    >
                                        Clear
                                    </button>
                                {/if}
                            </div>
                        </div>
                    </div>

                    {#if selectedNodeData}
                        <div>
                            <h3 class="border-b border-gray-200 pb-3 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                                {selectedNodeData.label}
                            </h3>

                            <div class="mt-2 flex flex-wrap gap-1">
                                {#if selectedNodeData.is_start}
                                    <span
                                        class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                    >
                                        START
                                    </span>
                                {/if}

                                {#if selectedNodeData.is_ending}
                                    <span
                                        class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                    >
                                        ending
                                    </span>
                                {/if}

                                {#if seenNodeIds.has(selectedNodeData.id)}
                                    <span
                                        class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                                    >
                                        seen
                                    </span>
                                {/if}
                            </div>

                            {#if selectedNodeData.file_path}
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {selectedNodeData.file_path}:{selectedNodeData.line_number}
                                </p>
                            {/if}

                            {#if selectedNodeData.variable_changes && selectedNodeData.variable_changes.length > 0}
                                <div class="mt-3">
                                    <h4 class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">variable changes</h4>

                                    {#each selectedNodeData.variable_changes as vc (`${vc.variable}:${vc.operation}:${vc.value}`)}
                                        <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                            <span class="font-mono">{vc.variable}</span>
                                            <span class="text-gray-400">{vc.operation}</span>
                                            <span class="font-mono">{vc.value}</span>
                                        </div>
                                    {/each}
                                </div>
                            {/if}

                            {#if selectedNodeData.outgoing_count > 0}
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {selectedNodeData.outgoing_count} outgoing path{selectedNodeData.outgoing_count > 1 ? 's' : ''}
                                </p>
                            {/if}

                            {#if startNodeId && selectedNodeData.id !== startNodeId}
                                <button
                                    class="mt-3 w-full rounded-lg bg-blue-500 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-600 disabled:opacity-50"
                                    onclick={() => (navigationTarget = selectedNodeData.last_label ?? selectedNodeData.id)}
                                    disabled={navigationTarget === (selectedNodeData.last_label ?? selectedNodeData.id)}
                                >
                                    {#if navigationTarget === (selectedNodeData.last_label ?? selectedNodeData.id)}
                                        Viewing path
                                    {:else}
                                        Navigate here
                                    {/if}
                                </button>
                            {/if}
                        </div>
                    {:else}
                        <div class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">click a node to see details</div>
                    {/if}

                    {#if endings.length > 0}
                        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <h3 class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                endings ({endings.length})
                            </h3>

                            <div class="flex flex-wrap gap-1">
                                {#each endings as ending (ending)}
                                    <button
                                        class="rounded bg-red-50 px-2 py-1 text-xs text-red-600 transition-colors hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/40"
                                        onclick={() => {
                                            navigationTarget = ending;
                                            selectedNodeId = ending;
                                            showSidebar = true;
                                        }}
                                    >
                                        {ending}
                                    </button>
                                {/each}
                            </div>
                        </div>
                    {/if}

                    {#if variables.length > 0}
                        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <h3 class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                variables ({variables.length})
                            </h3>

                            <div class="space-y-1">
                                {#each variables as v (v.name)}
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                        <span class="font-mono font-medium">{v.name}</span>

                                        {#if v.default_value}
                                            <span class="text-gray-400"> = {v.default_value}</span>
                                        {/if}

                                        <span class="text-gray-400"> ({v.change_count} changes)</span>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}
                </div>
            {/if}
        </div>
    {/if}
</div>

<style>
    :global(.route-map-minimap) {
        border: 1px solid rgba(148, 163, 184, 0.65);
        border-radius: 14px;
        box-shadow:
            0 10px 24px rgba(15, 23, 42, 0.16),
            0 2px 6px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        backdrop-filter: blur(6px);
    }

    :global(.route-map-minimap .svelte-flow__minimap-svg) {
        border-radius: 14px;
    }
</style>
