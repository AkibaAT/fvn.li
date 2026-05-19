<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import RouteMapWorkspace from '@/components/route-map/RouteMapWorkspace.svelte';
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
    import { buildRouteLayoutElements, getLayoutPosition } from '@/utils/route-map-layout';
    import {
        appendStyle,
        collapseRouteEdges,
        debounce,
        formatCollapsedEdgeLabel,
        getParallelEdgeLanes,
        type VisualRouteEdge,
    } from '@/utils/route-map';
    import { SvelteMap, SvelteSet } from 'svelte/reactivity';

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
    let includeUnreachable = $state<boolean>(
        (() => Boolean($state.snapshot(initialIncludeUnreachable) || ($state.snapshot(initialGraph) as RouteGraphData)?.includes_unreachable))(),
    );
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
    let layoutVersion = $state(0);

    const seenNodeStyle =
        'background:var(--rm-seen-bg);border:2px solid var(--rm-seen-border);border-radius:6px;box-shadow:0 0 0 1px var(--rm-seen-shadow);';
    const partiallySeenNodeStyle =
        'background:var(--rm-partial-bg);border:2px solid var(--rm-partial-border);border-radius:6px;box-shadow:0 0 0 1px var(--rm-partial-shadow);';
    const pathNodeStyle = 'background:var(--rm-path-bg);border:2px solid var(--rm-path-border);border-radius:6px;';
    const choiceNodeStyle =
        'background:var(--xy-node-choice-bg, #fef3c7);border:2px solid var(--xy-node-choice-border, #f59e0b);border-radius:16px;font-size:12px;';
    const selectedNodeStyle = 'box-shadow:0 0 0 3px rgba(14, 165, 233, 0.35);border:2px solid #0ea5e9;';
    const connectedNodeStyle = 'box-shadow:0 0 0 2px rgba(14, 165, 233, 0.18);border:2px solid rgba(14, 165, 233, 0.75);';
    const dimmedNodeStyle = 'opacity:0.15;';
    const mutedNodeStyle = 'opacity:0.2;';
    const highlightedEdgeStyle = 'stroke:var(--rm-path-border);stroke-width:3;';
    const connectedEdgeStyle = 'stroke:#0ea5e9;stroke-width:3;';
    const dimmedEdgeStyle = 'opacity:0.15;';
    const unresolvedEdgeStyle = 'stroke:#ef4444;stroke-width:2.5;stroke-dasharray:7 5;';

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
    const darkMinimapNodeStyles: Record<string, { fill: string; stroke: string }> = {
        default: { fill: '#94a3b8', stroke: '#cbd5e1' },
        choice: { fill: '#f59e0b', stroke: '#fbbf24' },
        muted: { fill: '#334155', stroke: '#475569' },
        seen: { fill: '#10b981', stroke: '#6ee7b7' },
        partiallySeen: { fill: '#34d399', stroke: '#a7f3d0' },
        path: { fill: '#60a5fa', stroke: '#bfdbfe' },
        ending: { fill: '#f87171', stroke: '#fecaca' },
        start: { fill: '#4ade80', stroke: '#bbf7d0' },
    };

    function isPathConditionNode(node: any): boolean {
        const edgeIds = node.data?.edgeIds;
        return Array.isArray(edgeIds) && edgeIds.some((edgeId: string) => displayPathEdgeIds.has(edgeId));
    }

    function getMiniMapNodeCategory(node: any): string {
        if (navigationTarget && displayPathNodeIds.size > 0) {
            return displayPathNodeIds.has(node.id) || isPathConditionNode(node) ? 'path' : 'muted';
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
        const styles = colorMode === 'dark' ? darkMinimapNodeStyles : minimapNodeStyles;

        return styles[getMiniMapNodeCategory(node)]!.fill;
    }

    function getMiniMapNodeStrokeColor(node: any): string {
        const styles = colorMode === 'dark' ? darkMinimapNodeStyles : minimapNodeStyles;

        return styles[getMiniMapNodeCategory(node)]!.stroke;
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
                interactionWidth: 20,
                zIndex: label ? 1 : 0,
                style: targetsUnresolvedNode ? unresolvedEdgeStyle : undefined,
                data: { ...edge, parallel, targets_unresolved_node: targetsUnresolvedNode },
            };
        });
    });

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
                node.node_type === 'choice'
                    ? 'choice'
                    : node.node_type === 'condition'
                      ? 'condition'
                      : node.node_type === 'hub'
                        ? 'hub'
                        : node.node_type === 'label' && (node.menu_prompt || node.returns_to_caller || node.is_unresolved)
                          ? 'label'
                          : undefined;
            return {
                id: node.id,
                type: nodeType,
                data: {
                    ...node,
                    label: node.label,
                },
                position: getLayoutPosition(node.id, routeGraph?.layout?.nodes),
                style: node.node_type === 'choice' ? choiceNodeStyle : undefined,
            };
        });
    });

    let layoutElements = $derived.by(() => buildRouteLayoutElements(layoutInputNodes, baseActiveEdges, routeGraph?.layout?.nodes));
    let activeEdges = $derived.by((): DisplayEdge[] => layoutElements.edges);
    let activeNodes = $derived.by((): DisplayNode[] => layoutElements.nodes);

    let selectedConnection = $derived.by(() => {
        const edgeIds = new SvelteSet<string>();
        const nodeIds = new SvelteSet<string>();

        if (!selectedNodeId) {
            return { edgeIds, nodeIds };
        }

        nodeIds.add(selectedNodeId);

        for (const edge of activeEdges) {
            const originalSource = (edge.data?.source as string | undefined) ?? edge.source;
            const originalTarget = (edge.data?.target as string | undefined) ?? edge.target;
            const isConnected = originalSource === selectedNodeId || originalTarget === selectedNodeId;

            if (!isConnected) continue;

            edgeIds.add(edge.id);
            nodeIds.add(edge.source);
            nodeIds.add(edge.target);
            nodeIds.add(originalSource);
            nodeIds.add(originalTarget);
        }

        return { edgeIds, nodeIds };
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
                route('browser-api.games.version.route-graph', {
                    game: game.slug,
                    version: targetVersion,
                }),
                {
                    params: shouldIncludeUnreachable ? { include_unreachable: 1 } : undefined,
                },
            );

            routeGraph = res.data;
            layoutVersion += 1;
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
                route('browser-api.games.version.parse-save', {
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
                if (displayPathNodeIds.has(node.id) || isPathConditionNode(node)) {
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

        if (selectedNodeId) {
            return activeNodes.map((node: DisplayNode) => {
                if (node.id === selectedNodeId) {
                    return {
                        ...node,
                        style: appendStyle(node.style, selectedNodeStyle),
                    };
                }

                if (selectedConnection.nodeIds.has(node.id)) {
                    return {
                        ...node,
                        style: appendStyle(node.style, connectedNodeStyle),
                    };
                }

                return {
                    ...node,
                    style: appendStyle(node.style, dimmedNodeStyle),
                };
            });
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
        if (navigationTarget && displayPathEdgeIds.size > 0) {
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
        }

        if (selectedNodeId && selectedConnection.edgeIds.size > 0) {
            return activeEdges.map((edge: DisplayEdge) => {
                if (selectedConnection.edgeIds.has(edge.id)) {
                    return {
                        ...edge,
                        style: edge.data.targets_unresolved_node ? `${connectedEdgeStyle}${unresolvedEdgeStyle}` : connectedEdgeStyle,
                        animated: true,
                        zIndex: 3,
                    };
                }

                return {
                    ...edge,
                    style: edge.data.targets_unresolved_node ? `${dimmedEdgeStyle}${unresolvedEdgeStyle}` : dimmedEdgeStyle,
                };
            });
        }

        return activeEdges;
    });

    function handleSearch(query: string) {
        searchQuery = query;
        updateDebouncedSearch(query);
    }

    function handleToggleUnreachable(checked: boolean) {
        includeUnreachable = checked;
        loadGraph(undefined, checked);
    }

    function handleClearSeenData() {
        seenNodeIds.clear();
        saveUploadError = null;
    }

    function handleMovePreference(fromIndex: number, toIndex: number) {
        if (toIndex < 0 || toIndex >= routePreferences.length) return;
        const next = [...routePreferences];
        [next[fromIndex], next[toIndex]] = [next[toIndex], next[fromIndex]];
        routePreferences = next;
    }

    function handleAddPreference() {
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
    }

    function handleClearPreferences() {
        routePreferences = [];
        preferenceVariable = '';
        preferenceMode = 'maximize';
        preferenceValue = '';
    }

    function handleSelectEnding(ending: string) {
        selectedNodeId = ending;
        showSidebar = true;
    }
</script>

<SeoHead {metaTags} />

<RouteMapWorkspace
    {game}
    {gameVersions}
    {routeGraph}
    {visibleLanguages}
    {selectedVersionId}
    {selectedLanguage}
    {searchQuery}
    {canInspectFullRouteMap}
    {includeUnreachable}
    {isLoading}
    {showSidebar}
    seenCount={seenNodeIds.size}
    endingsCount={endings.length}
    {isUploadingSave}
    {saveUploadError}
    {displayNodes}
    {displayEdges}
    {colorMode}
    {layoutVersion}
    {navigationTarget}
    {isCalculatingPath}
    hasNavigationPath={Boolean(navigationPath)}
    {navigationSteps}
    {choiceCount}
    {conditionedStepCount}
    {routeWordCount}
    {routePreferences}
    {startNodeId}
    {routePlanningVariables}
    {preferenceVariable}
    {preferenceMode}
    {preferenceValue}
    {selectedNodeData}
    {seenNodeIds}
    {endings}
    {variables}
    {getMiniMapNodeColor}
    {getMiniMapNodeStrokeColor}
    onLoadVersion={loadVersion}
    onChangeLanguage={changeLanguage}
    onSearch={handleSearch}
    onToggleUnreachable={handleToggleUnreachable}
    onToggleSidebar={() => (showSidebar = !showSidebar)}
    onUploadSaveFile={uploadSaveFile}
    onClearSeenData={handleClearSeenData}
    onSelectNode={(nodeId) => {
        selectedNodeId = nodeId;
        if (nodeId) showSidebar = true;
    }}
    onClearPath={() => (navigationTarget = null)}
    onMovePreference={handleMovePreference}
    onRemovePreference={(index) => {
        routePreferences = routePreferences.filter((_, currentIndex) => currentIndex !== index);
    }}
    onPreferenceVariableChange={(value) => (preferenceVariable = value)}
    onPreferenceModeChange={(value) => (preferenceMode = value)}
    onPreferenceValueChange={(value) => (preferenceValue = value)}
    onAddPreference={handleAddPreference}
    onClearPreferences={handleClearPreferences}
    onNavigateTo={(target) => (navigationTarget = target)}
    onSelectEnding={handleSelectEnding}
/>
