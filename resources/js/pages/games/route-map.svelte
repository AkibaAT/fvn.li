<script lang="ts">
    import { SvelteFlow, Background, Controls, MiniMap } from '@xyflow/svelte';
    import '@xyflow/svelte/dist/style.css';
    import ElkConstructor, { type ELK as ElkLayoutEngine, type ElkExtendedEdge, type ElkNode } from 'elkjs/lib/elk-api.js';
    import elkWorkerUrl from 'elkjs/lib/elk-worker.min.js?url';
    import { Link } from '@inertiajs/svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import BranchEdge from '@/components/route-map/BranchEdge.svelte';
    import ChoiceNode from '@/components/route-map/ChoiceNode.svelte';
    import HubNode from '@/components/route-map/HubNode.svelte';
    import LabelNode from '@/components/route-map/LabelNode.svelte';
    import RouteMapFitView from '@/components/route-map/RouteMapFitView.svelte';
    import type {
        DisplayEdge,
        DisplayNode,
        MenuChoice,
        RouteEdge,
        RouteGraphData,
        RouteMapPageProps,
        RouteNode,
        RoutePreference,
    } from '@/types/route-graph';
    import { usePathfinderWorker } from '@/hooks/usePathfinderWorker.svelte';
    import http from '@/utils/http';
    import { SvelteMap, SvelteSet } from 'svelte/reactivity';

    type VisualRouteEdge = RouteEdge & {
        edgeIds: string[];
        collapsed_edges: RouteEdge[];
    };

    type RouteLayout = {
        nodes: DisplayNode[];
    };

    const nodeTypes = { choice: ChoiceNode, hub: HubNode, label: LabelNode };
    const edgeTypes = { branch: BranchEdge };
    const TARGET_HANDLE_ID = 'in';
    const SOURCE_HANDLE_ID = 'out';
    const NODE_DIMENSIONS = {
        choiceWidth: 184,
        choiceBaseHeight: 34,
        choiceVariableHeight: 16,
        hubWidth: 140,
        hubHeight: 54,
        labelWidth: 220,
        labelBaseHeight: 42,
        promptLineHeight: 14,
    };
    let elkPromise: Promise<ElkLayoutEngine> | null = null;

    const getColorMode = (): 'light' | 'dark' =>
        typeof document !== 'undefined' && document.documentElement.classList.contains('dark') ? 'dark' : 'light';

    let colorMode = $state<'light' | 'dark'>(getColorMode());

    $effect(() => {
        if (typeof document === 'undefined' || typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => {
            colorMode = getColorMode();
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    });

    let {
        game,
        currentVersion,
        gameVersions,
        routeGraph: initialGraph,
        canInspectFullRouteMap,
        includeUnreachable: initialIncludeUnreachable,
        availableLanguages,
        currentLanguage,
        metaTags,
    }: RouteMapPageProps = $props();

    let routeGraph = $state<RouteGraphData>((() => $state.snapshot(initialGraph) as RouteGraphData)());
    let selectedVersionId = $state<number>((() => $state.snapshot(currentVersion)?.id ?? 0)());
    let selectedLanguage = $state<string | null>((() => $state.snapshot(currentLanguage) ?? null)());
    let visibleLanguages = $state<string[]>((() => [...($state.snapshot(availableLanguages) ?? [])])());
    let selectedNodeId = $state<string | null>(null);
    let isLoading = $state(false);
    let searchQuery = $state('');
    let showSidebar = $state(false);
    let includeUnreachable = $state<boolean>((() => Boolean($state.snapshot(initialIncludeUnreachable) || ($state.snapshot(initialGraph) as RouteGraphData)?.includes_unreachable))());
    let navigationTarget = $state<string | null>(typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('target') : null);
    let routePreferences = $state<RoutePreference[]>([]);
    let preferenceVariable = $state('');
    let preferenceMode = $state<RoutePreference['mode']>('maximize');
    let preferenceValue = $state('');
    let seenNodeIds = new SvelteSet<string>();
    let isUploadingSave = $state(false);

    // Web worker for pathfinding (prevents UI freezing on large graphs)
    const pathfinder = usePathfinderWorker();
    let navigationPath = $state<Array<{ nodeId: string; edge: RouteEdge | null }> | null>(null);
    let isCalculatingPath = $state(false);
    let saveUploadError = $state<string | null>(null);
    let pathRequestSequence = 0;
    let layoutNodes = $state<DisplayNode[]>([]);
    let layoutRequestSequence = 0;
    let layoutVersion = $state(0);

    const seenNodeStyle =
        'background:var(--rm-seen-bg);border:2px solid var(--rm-seen-border);border-radius:6px;box-shadow:0 0 0 1px var(--rm-seen-shadow);';
    const partiallySeenNodeStyle =
        'background:var(--rm-partial-bg);border:2px solid var(--rm-partial-border);border-radius:6px;box-shadow:0 0 0 1px var(--rm-partial-shadow);';
    const pathNodeStyle = 'background:var(--rm-path-bg);border:2px solid var(--rm-path-border);border-radius:6px;';
    const choiceNodeStyle =
        'background:var(--xy-node-choice-bg, #fef3c7);border:2px solid var(--xy-node-choice-border, #f59e0b);border-radius:16px;font-size:12px;';
    const dimmedNodeStyle = 'opacity:0.15;';
    const mutedNodeStyle = 'opacity:0.2;';
    const highlightedEdgeStyle = 'stroke:var(--rm-path-border);stroke-width:3;';
    const dimmedEdgeStyle = 'opacity:0.15;';
    const unresolvedEdgeStyle = 'stroke:#ef4444;stroke-width:2.5;stroke-dasharray:7 5;';
    const edgeLabelStyle =
        'background:var(--rm-edge-label-bg);color:var(--rm-edge-label-text);border:1px solid var(--rm-edge-label-border);border-radius:4px;padding:2px 6px;font-size:11px;line-height:1.2;white-space:pre-line;text-align:left;max-width:260px;box-shadow:0 1px 3px rgba(15,23,42,0.18);';
    const unresolvedEdgeLabelStyle =
        'background:#fef2f2;color:#b91c1c;border:1px solid #ef4444;border-radius:4px;padding:2px 6px;font-size:11px;line-height:1.2;white-space:pre-line;text-align:left;max-width:260px;box-shadow:0 1px 3px rgba(15,23,42,0.18);font-weight:600;';
    const UNKNOWN_VALUE = Symbol('unknown-value');

    // Debounce utility for search input
    function debounce<T extends (...args: Parameters<T>) => ReturnType<T>>(fn: T, delay: number): (...args: Parameters<T>) => void {
        let timeoutId: ReturnType<typeof setTimeout>;
        return (...args: Parameters<T>) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn(...args), delay);
        };
    }

    let debouncedSearchQuery = $state('');
    const updateDebouncedSearch = debounce((value: string) => {
        debouncedSearchQuery = value;
    }, 150);
    const minimapNodeStyles: Record<string, { fill: string; stroke: string }> = {
        default: { fill: '#475569', stroke: '#0f172a' },
        choice: { fill: '#f59e0b', stroke: '#d97706' },
        muted: { fill: '#cbd5e1', stroke: '#94a3b8' },
        seen: { fill: '#10b981', stroke: '#047857' },
        partiallySeen: { fill: '#34d399', stroke: '#059669' },
        path: { fill: '#2563eb', stroke: '#1d4ed8' },
        ending: { fill: '#ef4444', stroke: '#b91c1c' },
        start: { fill: '#16a34a', stroke: '#166534' },
    };

    function getMiniMapNodeCategory(node: any): string {
        if (navigationTarget && displayPathNodeIds.size > 0) {
            return displayPathNodeIds.has(node.id) ? 'path' : 'muted';
        }

        if (filteredNodeIds && !filteredNodeIds.has(node.id)) {
            return 'muted';
        }

        if (node.data?.node_type === 'choice') {
            return 'choice';
        }

        if (node.data?.is_ending) {
            return 'ending';
        }

        if (node.data?.is_start) {
            return 'start';
        }

        if (isFullySeenNode(node)) {
            return 'seen';
        }

        if (isSeenNode(node)) {
            return 'partiallySeen';
        }

        return 'default';
    }

    function getMiniMapNodeColor(node: any): string {
        return minimapNodeStyles[getMiniMapNodeCategory(node)]!.fill;
    }

    function getMiniMapNodeStrokeColor(node: any): string {
        return minimapNodeStyles[getMiniMapNodeCategory(node)]!.stroke;
    }

    type ResolvedValue = string | number | boolean | null;

    function formatReadingTime(words: number): string {
        const minutes = words / 200;
        if (minutes < 1) return '< 1 min';
        if (minutes < 60) return `~${Math.round(minutes)} min`;
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = Math.round(minutes % 60);
        return remainingMinutes > 0 ? `~${hours}h ${remainingMinutes}m` : `~${hours}h`;
    }

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

    function getElkPortId(nodeId: string, handleId: string): string {
        return `${nodeId}:${handleId}`;
    }

    function estimateNodeSize(node: DisplayNode): { width: number; height: number } {
        const isChoice = node.data?.node_type === 'choice';
        const varSummary = node.data?.var_summary ?? '';
        const prompt = node.data?.menu_prompt ?? '';

        if (isChoice) {
            return {
                width: NODE_DIMENSIONS.choiceWidth,
                height: NODE_DIMENSIONS.choiceBaseHeight + (varSummary ? NODE_DIMENSIONS.choiceVariableHeight : 0),
            };
        }

        if (node.data?.node_type === 'hub') {
            return {
                width: NODE_DIMENSIONS.hubWidth,
                height: NODE_DIMENSIONS.hubHeight,
            };
        }

        if (prompt) {
            const estimatedLines = Math.ceil(prompt.length / 30);
            return {
                width: NODE_DIMENSIONS.labelWidth,
                height: NODE_DIMENSIONS.labelBaseHeight + estimatedLines * NODE_DIMENSIONS.promptLineHeight,
            };
        }

        return {
            width: NODE_DIMENSIONS.labelWidth,
            height: NODE_DIMENSIONS.labelBaseHeight,
        };
    }

    async function getElk(): Promise<ElkLayoutEngine> {
        elkPromise ??= import.meta.env.DEV
            ? import('elkjs/lib/elk.bundled.js').then((module) => new module.default())
            : Promise.resolve(new ElkConstructor({ workerUrl: elkWorkerUrl }));

        return elkPromise;
    }

    async function computeLayout(nodes: DisplayNode[], edges: DisplayEdge[]): Promise<RouteLayout> {
        const nodeIds = new Set(nodes.map((node) => node.id));
        const layoutEdges = edges.filter((edge) => nodeIds.has(edge.source) && nodeIds.has(edge.target));

        const graph: ElkNode = {
            id: 'route-map',
            layoutOptions: {
                'elk.algorithm': 'layered',
                'elk.direction': 'DOWN',
                'elk.edgeRouting': 'SPLINES',
                'elk.layered.edgeRouting.splines.mode': 'CONSERVATIVE',
                'elk.layered.crossingMinimization.strategy': 'LAYER_SWEEP',
                'elk.layered.nodePlacement.strategy': 'NETWORK_SIMPLEX',
                'elk.layered.spacing.nodeNodeBetweenLayers': '100',
                'elk.layered.spacing.edgeEdgeBetweenLayers': '36',
                'elk.layered.spacing.edgeNodeBetweenLayers': '42',
                'elk.spacing.nodeNode': '72',
                'elk.spacing.edgeEdge': '26',
                'elk.spacing.edgeNode': '38',
                'elk.spacing.componentComponent': '90',
                'elk.padding': '[top=40,left=40,bottom=40,right=40]',
                'elk.separateConnectedComponents': 'true',
            },
            children: nodes.map((node) => {
                const size = estimateNodeSize(node);

                return {
                    id: node.id,
                    ...size,
                    layoutOptions: {
                        'elk.portConstraints': 'FIXED_POS',
                    },
                    ports: [
                        {
                            id: getElkPortId(node.id, TARGET_HANDLE_ID),
                            width: 1,
                            height: 1,
                            x: size.width / 2,
                            y: 0,
                            layoutOptions: {
                                'elk.port.side': 'NORTH',
                            },
                        },
                        {
                            id: getElkPortId(node.id, SOURCE_HANDLE_ID),
                            width: 1,
                            height: 1,
                            x: size.width / 2,
                            y: size.height,
                            layoutOptions: {
                                'elk.port.side': 'SOUTH',
                            },
                        },
                    ],
                };
            }),
            edges: layoutEdges.map((edge) => ({
                id: edge.id,
                source: edge.source,
                sourcePort: getElkPortId(edge.source, SOURCE_HANDLE_ID),
                target: edge.target,
                targetPort: getElkPortId(edge.target, TARGET_HANDLE_ID),
            })) as unknown as ElkExtendedEdge[],
        };

        const layout = await (await getElk()).layout(graph);
        const positionedNodes = new Map((layout.children ?? []).map((node) => [node.id, node]));

        return {
            nodes: nodes.map((node) => {
                const position = positionedNodes.get(node.id);
                if (!position) return node;

                return {
                    ...node,
                    position: {
                        x: position.x ?? 0,
                        y: position.y ?? 0,
                    },
                };
            }),
        };
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

    function formatRoutePreference(pref: RoutePreference): string {
        if (pref.mode === 'maximize') return `${pref.variable} max`;
        if (pref.mode === 'minimize') return `${pref.variable} min`;
        return `${pref.variable} = ${pref.value}`;
    }

    function formatEdgeLabel(edge: RouteEdge): string | undefined {
        const condition = edge.condition?.trim();
        const isElseCondition = condition?.startsWith('not (') ?? false;
        const conditionLabel = condition && condition !== 'True' ? (isElseCondition ? 'else' : `if ${condition}`) : null;
        const choiceText = edge.choice_text?.trim();

        if (choiceText && conditionLabel) return `${choiceText} · ${conditionLabel}`;
        return choiceText || conditionLabel || undefined;
    }

    function getVisualEdgeKey(edge: RouteEdge): string {
        return `${edge.source}\u0000${edge.target}`;
    }

    function getVisualEdgeId(edge: RouteEdge): string {
        return `connection:${encodeURIComponent(edge.source)}:${encodeURIComponent(edge.target)}`;
    }

    function formatCollapsedEdgeLabel(edges: RouteEdge[]): string | undefined {
        const labels = new SvelteSet<string>();

        for (const edge of edges) {
            const label = formatEdgeLabel(edge);
            if (label) labels.add(label);
        }

        return labels.size > 0 ? [...labels].join('\n') : undefined;
    }

    function collapseRouteEdges(edges: RouteEdge[]): VisualRouteEdge[] {
        const edgeGroups = new SvelteMap<string, RouteEdge[]>();

        for (const edge of edges) {
            const key = getVisualEdgeKey(edge);
            edgeGroups.set(key, [...(edgeGroups.get(key) ?? []), edge]);
        }

        return [...edgeGroups.values()].map((group) => {
            const primaryEdge = group.find((edge) => edge.edge_type === 'menu_choice') ?? group[0]!;
            const edgeIds = group.map((edge) => edge.id);
            const collapsedEdgeType = group.some((edge) => edge.edge_type === 'menu_choice') ? 'menu_choice' : primaryEdge.edge_type;

            return {
                ...primaryEdge,
                id: getVisualEdgeId(primaryEdge),
                edge_type: collapsedEdgeType,
                choice_text: null,
                condition: null,
                edgeIds,
                collapsed_edges: group,
            };
        });
    }

    function getParallelEdgeLanes(edges: VisualRouteEdge[]): SvelteMap<string, { index: number; total: number }> {
        const labeledSourceTotals = new SvelteMap<string, number>();
        const totals = new SvelteMap<string, number>();
        const counts = new SvelteMap<string, number>();
        const lanes = new SvelteMap<string, { index: number; total: number }>();

        for (const edge of edges) {
            if (!formatCollapsedEdgeLabel(edge.collapsed_edges)) continue;

            labeledSourceTotals.set(edge.source, (labeledSourceTotals.get(edge.source) ?? 0) + 1);
        }

        const getLaneKey = (edge: VisualRouteEdge) => {
            const labeledSourceTotal = labeledSourceTotals.get(edge.source) ?? 0;

            if (labeledSourceTotal > 1 && labeledSourceTotal <= 8) {
                return `source:${edge.source}`;
            }

            return `pair:${edge.source}\u0000${edge.target}`;
        };

        for (const edge of edges) {
            if (!formatCollapsedEdgeLabel(edge.collapsed_edges)) continue;

            const key = getLaneKey(edge);
            totals.set(key, (totals.get(key) ?? 0) + 1);
        }

        for (const edge of edges) {
            if (!formatCollapsedEdgeLabel(edge.collapsed_edges)) continue;

            const key = getLaneKey(edge);
            const index = counts.get(key) ?? 0;
            counts.set(key, index + 1);
            lanes.set(edge.id, { index, total: totals.get(key) ?? 1 });
        }

        return lanes;
    }

    let currentEdges = $derived.by(() => {
        if (!routeGraph?.has_graph_data) return [];

        const sourceEdges = routeGraph.edges;
        return Array.isArray(sourceEdges) ? sourceEdges : [];
    });

    let visualRouteEdges = $derived.by(() => collapseRouteEdges(currentEdges));

    let unresolvedNodeIds = $derived.by(() => {
        const ids = new SvelteSet<string>();
        const sourceNodes = routeGraph?.nodes;
        if (!Array.isArray(sourceNodes)) return ids;

        for (const node of sourceNodes) {
            if (node.is_unresolved) ids.add(node.id);
        }

        return ids;
    });

    let parallelEdgeLanes = $derived.by(() => getParallelEdgeLanes(visualRouteEdges));

    let baseActiveEdges = $derived.by((): DisplayEdge[] => {
        return visualRouteEdges.map((edge: VisualRouteEdge) => {
            const targetsUnresolvedNode = unresolvedNodeIds.has(edge.target);
            const label = targetsUnresolvedNode
                ? (formatCollapsedEdgeLabel(edge.collapsed_edges) ?? 'missing target')
                : formatCollapsedEdgeLabel(edge.collapsed_edges);
            const parallel = parallelEdgeLanes.get(edge.id);

            return {
                id: edge.id,
                source: edge.source,
                target: edge.target,
                type: 'branch',
                animated: edge.edge_type === 'menu_choice',
                label,
                labelStyle: label ? (targetsUnresolvedNode ? unresolvedEdgeLabelStyle : edgeLabelStyle) : undefined,
                interactionWidth: 20,
                zIndex: label ? 1 : 0,
                style: targetsUnresolvedNode ? unresolvedEdgeStyle : undefined,
                data: { ...edge, parallel, targets_unresolved_node: targetsUnresolvedNode },
            };
        });
    });

    let activeEdges = $derived.by((): DisplayEdge[] => baseActiveEdges);

    function tr(text: string | null | undefined, translations: Record<string, string> | null | undefined): string {
        if (!selectedLanguage || !translations) return text ?? '';
        return translations[selectedLanguage] ?? text ?? '';
    }

    let currentNodes = $derived.by(() => {
        if (!routeGraph?.has_graph_data) return [];

        const sourceNodes = routeGraph.nodes;
        if (!Array.isArray(sourceNodes)) return [];

        return sourceNodes.map((node: RouteNode) => {
            if (node.node_type === 'choice') {
                const translatedText = tr(node.choice_text, node.translations);
                return { ...node, label: translatedText, choice_text: translatedText };
            }
            if (node.menu_prompt) {
                return { ...node, menu_prompt: tr(node.menu_prompt, node.menu_prompt_translations) };
            }
            if (node.choices?.length > 0) {
                return {
                    ...node,
                    choices: node.choices.map((c: MenuChoice) => ({ ...c, text: tr(c.text, c.translations) })),
                };
            }
            return node;
        });
    });

    let layoutInputNodes = $derived.by((): DisplayNode[] => {
        return currentNodes.map((node: RouteNode) => {
            const nodeType: DisplayNode['type'] =
                node.node_type === 'choice' ? 'choice' : node.node_type === 'hub' ? 'hub' : node.menu_prompt ? 'label' : undefined;
            return {
                id: node.id,
                type: nodeType,
                data: {
                    ...node,
                    label: node.label,
                },
                position: { x: 0, y: 0 },
                style: node.node_type === 'choice' ? choiceNodeStyle : undefined,
            };
        });
    });

    $effect(() => {
        const nodes = layoutInputNodes;
        const edges = baseActiveEdges;
        const requestId = ++layoutRequestSequence;

        layoutNodes = nodes;

        if (nodes.length === 0) return;

        computeLayout(nodes, edges)
            .then((layout) => {
                if (requestId !== layoutRequestSequence) return;

                layoutNodes = layout.nodes;
                layoutVersion += 1;
            })
            .catch((error) => {
                if (requestId !== layoutRequestSequence) return;
                console.error('Route map layout failed:', error);
                layoutNodes = nodes;
                layoutVersion += 1;
            });
    });

    let activeNodes = $derived.by((): DisplayNode[] => {
        if (layoutNodes.length !== layoutInputNodes.length) return layoutInputNodes;

        const inputNodeIds = new Set(layoutInputNodes.map((node) => node.id));
        return layoutNodes.every((node) => inputNodeIds.has(node.id)) ? layoutNodes : layoutInputNodes;
    });

    // Pre-index nodes by id for O(1) lookup (rebuilt when graph changes)
    let nodeById = $derived.by(() => {
        const map = new SvelteMap<string, any>();
        for (const node of currentNodes as any[]) {
            map.set(node.id, node);
        }
        return map;
    });

    let selectedNodeData = $derived.by(() => {
        if (!selectedNodeId) return null;
        return nodeById.get(selectedNodeId) ?? null;
    });

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
        for (const [id, node] of nodeById) {
            if (node.is_start) return id;
        }
        return null;
    });

    // Calculate path using Web Worker when navigation target changes
    $effect(() => {
        if (!navigationTarget || !startNodeId) {
            pathRequestSequence++;
            navigationPath = null;
            return;
        }

        isCalculatingPath = true;

        const requestId = ++pathRequestSequence;

        pathfinder
            .findPath(
                startNodeId,
                navigationTarget,
                fullEdges,
                routePreferences.length > 0
                    ? {
                          nodes: currentNodes,
                          variables,
                          preferences: routePreferences,
                      }
                    : {},
            )
            .then((path) => {
                if (requestId !== pathRequestSequence) return;
                navigationPath = path;
            })
            .catch((error) => {
                if (requestId !== pathRequestSequence) return;
                console.error('Pathfinding failed:', error);
                navigationPath = null;
            })
            .finally(() => {
                if (requestId !== pathRequestSequence) return;
                isCalculatingPath = false;
            });
    });

    let displayPathNodeIds = $derived.by(() => {
        if (!navigationPath) return new Set<string>();
        return new SvelteSet(navigationPath.map((s) => s.nodeId));
    });

    let displayPathEdgeIds = $derived.by(() => {
        if (!navigationPath) return new Set<string>();
        return new SvelteSet(navigationPath.filter((s): s is { nodeId: string; edge: RouteEdge } => s.edge !== null).map((s) => s.edge.id));
    });

    let endingsSet = $derived.by(() => new Set(endings));

    let navigationSteps = $derived.by(() => {
        if (!navigationPath || navigationPath.length <= 1) return [];

        return navigationPath.slice(1).map((step, i) => {
            const edge = step.edge;
            const targetNode = nodeById.get(step.nodeId);
            const isChoice = edge?.edge_type === 'menu_choice' || edge?.edge_type === 'choice' || targetNode?.node_type === 'choice';
            const condition = edge?.condition?.trim() || null;

            return {
                step: i + 1,
                nodeId: step.nodeId,
                edgeType: edge?.edge_type ?? 'flow',
                isChoice,
                choiceText: edge?.choice_text ?? targetNode?.choice_text ?? (targetNode?.node_type === 'choice' ? targetNode.label : null),
                condition,
                targetIsEnding: endingsSet.has(step.nodeId),
            };
        });
    });

    let choiceCount = $derived(navigationSteps.filter((s) => s.isChoice).length);
    let conditionedStepCount = $derived(navigationSteps.filter((s) => s.condition).length);

    // Pre-index word counts by node id for O(1) lookup (rebuilt when graph changes)
    let wordCountByNodeId = $derived.by(() => {
        const map = new SvelteMap<string, number>();
        for (const n of (routeGraph?.nodes ?? []) as RouteNode[]) {
            map.set(n.id, n.word_count ?? 0);
        }
        return map;
    });

    let routeWordCount = $derived.by(() => {
        if (!navigationPath) return 0;
        return navigationPath.reduce((sum, step) => sum + (wordCountByNodeId.get(step.nodeId) ?? 0), 0);
    });

    async function loadGraph(versionId?: number, includeUnreachableOverride = includeUnreachable) {
        const targetVersion = versionId ?? selectedVersionId;
        const shouldIncludeUnreachable = canInspectFullRouteMap && includeUnreachableOverride;

        isLoading = true;

        try {
            const res = await http.get(
                route('react-api.games.version.route-graph', {
                    game: game.slug,
                    version: targetVersion,
                }),
                {
                    params: shouldIncludeUnreachable ? { include_unreachable: 1 } : undefined,
                },
            );

            routeGraph = res.data;
            if (Array.isArray(res.data.available_languages)) {
                visibleLanguages = res.data.available_languages;
                if (selectedLanguage && !visibleLanguages.includes(selectedLanguage)) {
                    selectedLanguage = null;
                }
            }
            includeUnreachable = Boolean(res.data.includes_unreachable);
            selectedVersionId = targetVersion;
            selectedNodeId = null;
            navigationTarget = null;
            routePreferences = [];
            preferenceVariable = '';
            preferenceMode = 'maximize';
            preferenceValue = '';
            seenNodeIds.clear();
            pathfinder.clearCache();
            saveUploadError = null;
        } finally {
            isLoading = false;
        }
    }

    async function loadVersion(versionId: number) {
        if (versionId === selectedVersionId) return;
        await loadGraph(versionId);
    }

    function changeLanguage(lang: string | null) {
        selectedLanguage = lang;
    }

    $effect(() => {
        // eslint-disable-next-line svelte/prefer-svelte-reactivity -- ephemeral, not reactive state
        const params = new URLSearchParams();
        if (selectedVersionId !== currentVersion?.id) params.set('version_id', String(selectedVersionId));
        if (selectedLanguage) params.set('lang', selectedLanguage);
        if (canInspectFullRouteMap && includeUnreachable) params.set('include_unreachable', '1');
        if (navigationTarget) params.set('target', navigationTarget);
        const qs = params.toString();
        const url = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState({}, '', url);
    });

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
        if (!debouncedSearchQuery.trim() || !routeGraph?.nodes) return null;

        const query = debouncedSearchQuery.toLowerCase();
        const ids = new SvelteSet<string>();

        for (const node of routeGraph.nodes) {
            if (node.label?.toLowerCase().includes(query)) {
                ids.add(node.id);
            }
        }

        return ids;
    });

    let displayNodes = $derived.by(() => {
        if (navigationTarget && displayPathNodeIds.size > 0) {
            return activeNodes.map((node: DisplayNode) => {
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
            return activeNodes.map((node: DisplayNode) => {
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
            return activeNodes.map((node: DisplayNode) => ({
                ...node,
                style: filteredNodeIds.has(node.id) ? undefined : mutedNodeStyle,
                class: filteredNodeIds.has(node.id) ? undefined : 'opacity-20',
            }));
        }

        if (seenNodeIds.size > 0) {
            return activeNodes.map((node: DisplayNode) => {
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

        return activeEdges.map((edge: DisplayEdge) => {
            if (edge.data.edgeIds.some((edgeId) => displayPathEdgeIds.has(edgeId))) {
                return {
                    ...edge,
                    style: edge.data.targets_unresolved_node ? `${highlightedEdgeStyle}${unresolvedEdgeStyle}` : highlightedEdgeStyle,
                    animated: true,
                };
            }
            return {
                ...edge,
                style: edge.data.targets_unresolved_node ? `${dimmedEdgeStyle}${unresolvedEdgeStyle}` : dimmedEdgeStyle,
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

            {#if visibleLanguages.length > 1}
                <select
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    value={selectedLanguage ?? ''}
                    onchange={(e) => {
                        const target = e.target as HTMLSelectElement;
                        changeLanguage(target.value || null);
                    }}
                    disabled={isLoading}
                >
                    <option value="">Original</option>
                    {#each visibleLanguages as lang (lang)}
                        <option value={lang} selected={lang === selectedLanguage}>
                            {lang.toUpperCase()}
                        </option>
                    {/each}
                </select>
            {/if}

            <div class="relative">
                <input
                    type="text"
                    placeholder="Search nodes..."
                    bind:value={searchQuery}
                    oninput={(e) => updateDebouncedSearch(e.currentTarget.value)}
                    class="w-48 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                />
            </div>

            {#if canInspectFullRouteMap}
                <label
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    title="Show labels that are present in the script but unreachable from start"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900"
                        checked={includeUnreachable}
                        disabled={isLoading}
                        onchange={(e) => {
                            const checked = (e.currentTarget as HTMLInputElement).checked;
                            includeUnreachable = checked;
                            loadGraph(undefined, checked);
                        }}
                    />
                    <span>Show unreachable</span>
                </label>
            {/if}

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
                    nodes={displayNodes as any[]}
                    edges={displayEdges as any[]}
                    {nodeTypes}
                    {edgeTypes}
                    {colorMode}
                    fitView
                    fitViewOptions={{ padding: 0.12, minZoom: 0.05, maxZoom: 1 }}
                    minZoom={0.05}
                    onnodeclick={(event: any) => {
                        selectedNodeId = event.node?.id ?? null;
                        showSidebar = true;
                    }}
                    class=""
                >
                    <RouteMapFitView {layoutVersion} />
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

                            {#if isCalculatingPath}
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Calculating path...</p>
                            {:else if navigationPath}
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {navigationSteps.length} steps{#if choiceCount > 0}
                                        &middot; {choiceCount} choice{choiceCount !== 1 ? 's' : ''}{/if}
                                    {#if conditionedStepCount > 0}
                                        &middot; {conditionedStepCount} condition{conditionedStepCount !== 1 ? 's' : ''}{/if}
                                </p>
                                {#if routeWordCount > 0}
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {routeWordCount.toLocaleString()} words &middot; {formatReadingTime(routeWordCount)}
                                    </p>
                                {/if}

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
                                {#if selectedNodeData.node_type === 'choice'}
                                    <span class="text-amber-600 dark:text-amber-400">Choice:</span>
                                {/if}
                                {selectedNodeData.label}
                            </h3>

                            {#if selectedNodeData.parent_label}
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    in <button
                                        class="font-mono text-blue-500 hover:underline"
                                        onclick={() => {
                                            selectedNodeId = selectedNodeData.parent_label ?? null;
                                        }}>{selectedNodeData.parent_label}</button
                                    >
                                </p>
                            {/if}

                            <div class="mt-2 flex flex-wrap gap-1">
                                {#if selectedNodeData.node_type === 'choice'}
                                    <span
                                        class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                    >
                                        choice
                                    </span>
                                {/if}
                                {#if selectedNodeData.node_type === 'hub'}
                                    <span
                                        class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400"
                                    >
                                        {selectedNodeData.hub_choice_count} routes
                                    </span>
                                {/if}
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

                            {#if selectedNodeData.word_count > 0}
                                <div class="mt-2 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                    <span>{selectedNodeData.word_count.toLocaleString()} words</span>
                                    <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                    <span>{formatReadingTime(selectedNodeData.word_count)}</span>
                                </div>
                            {/if}

                            {#if selectedNodeData.file_path}
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {selectedNodeData.file_path}:{selectedNodeData.line_number}
                                </p>
                            {/if}

                            {#if selectedNodeData.choices && selectedNodeData.choices.length > 0}
                                <div class="mt-3">
                                    <h4 class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">choices</h4>
                                    {#each selectedNodeData.choices as choice (choice.text)}
                                        {@const relatedChanges =
                                            selectedNodeData.variable_changes?.filter(
                                                (vc: { context: string | null }) => vc.context === `menu_choice:${choice.text}`,
                                            ) ?? []}
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            <span class="font-medium text-gray-800 dark:text-gray-200">{choice.text}</span>
                                            {#if choice.condition}
                                                <span class="ml-1 text-amber-600 dark:text-amber-400">(if {choice.condition})</span>
                                            {/if}
                                            {#if choice.target_label}
                                                <span class="ml-1 text-blue-500">&rarr; {choice.target_label}</span>
                                            {/if}
                                            {#if relatedChanges.length > 0}
                                                {#each relatedChanges as vc (vc.variable + vc.operation)}
                                                    <span class="ml-1 font-mono text-emerald-600 dark:text-emerald-400"
                                                        >{vc.variable} {vc.operation} {vc.value}</span
                                                    >
                                                {/each}
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            {/if}

                            {#if selectedNodeData.variable_changes && selectedNodeData.variable_changes.length > 0}
                                <div class="mt-3">
                                    <h4 class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">variable changes</h4>

                                    {#each selectedNodeData.variable_changes as vc, i (`${i}:${vc.variable}:${vc.operation}`)}
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
    :global(.svelte-flow) {
        --xy-node-choice-bg: #fef3c7;
        --xy-node-choice-border: #f59e0b;
        --rm-path-bg: #eff6ff;
        --rm-path-border: #3b82f6;
        --rm-seen-bg: #ecfdf5;
        --rm-seen-border: #10b981;
        --rm-seen-shadow: rgba(16, 185, 129, 0.12);
        --rm-partial-bg: #f0fdf4;
        --rm-partial-border: #34d399;
        --rm-partial-shadow: rgba(52, 211, 153, 0.12);
        --rm-edge-label-bg: rgba(255, 255, 255, 0.95);
        --rm-edge-label-text: #334155;
        --rm-edge-label-border: rgba(148, 163, 184, 0.75);
    }

    :global(.svelte-flow.dark) {
        --xy-node-choice-bg: #451a03;
        --xy-node-choice-border: #d97706;
        --xy-node-hub-bg: #1e1b4b;
        --xy-node-hub-border: #818cf8;
        --xy-node-border-default: 1px solid #64748b;
        --xy-edge-stroke-default: #94a3b8;
        --rm-path-bg: #1e3a5f;
        --rm-path-border: #60a5fa;
        --rm-seen-bg: #064e3b;
        --rm-seen-border: #34d399;
        --rm-seen-shadow: rgba(52, 211, 153, 0.2);
        --rm-partial-bg: #052e16;
        --rm-partial-border: #6ee7b7;
        --rm-partial-shadow: rgba(110, 231, 183, 0.15);
        --rm-edge-label-bg: rgba(15, 23, 42, 0.92);
        --rm-edge-label-text: #e2e8f0;
        --rm-edge-label-border: rgba(100, 116, 139, 0.9);
    }

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
