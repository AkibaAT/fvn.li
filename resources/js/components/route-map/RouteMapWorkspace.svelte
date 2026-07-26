<script lang="ts">
    import { SvelteFlow, Background, Controls, MiniMap } from '@xyflow/svelte';
    import '@xyflow/svelte/dist/style.css';
    import { Link } from '@inertiajs/svelte';
    import { Card } from '@/components/ui';
    import BranchEdge from '@/components/route-map/BranchEdge.svelte';
    import ChoiceNode from '@/components/route-map/ChoiceNode.svelte';
    import ConditionNode from '@/components/route-map/ConditionNode.svelte';
    import HubNode from '@/components/route-map/HubNode.svelte';
    import LabelNode from '@/components/route-map/LabelNode.svelte';
    import RouteMapFitView from '@/components/route-map/RouteMapFitView.svelte';
    import RouteMapMiniMapNode from '@/components/route-map/RouteMapMiniMapNode.svelte';
    import RouteMapPathPanel from '@/components/route-map/RouteMapPathPanel.svelte';
    import RouteMapPrioritiesPanel from '@/components/route-map/RouteMapPrioritiesPanel.svelte';
    import RouteMapSelectedNodePanel from '@/components/route-map/RouteMapSelectedNodePanel.svelte';
    import RouteMapSummaryListsPanel from '@/components/route-map/RouteMapSummaryListsPanel.svelte';
    import RouteMapToolbar from '@/components/route-map/RouteMapToolbar.svelte';
    import type { DisplayEdge, DisplayNode, RoutePreference } from '@/types/route-graph';

    const nodeTypes = { choice: ChoiceNode, condition: ConditionNode, hub: HubNode, label: LabelNode };
    const edgeTypes = { branch: BranchEdge };
    const ROUTE_MAP_MIN_ZOOM = 0.01;

    interface RouteMapWorkspaceProps {
        game: { slug: string; name: string };
        gameVersions: any;
        routeGraph: any;
        visibleLanguages: string[];
        selectedVersionId: number;
        selectedLanguage: string | null;
        searchQuery: string;
        canInspectFullRouteMap: boolean;
        includeUnreachable: boolean;
        isLoading: boolean;
        showSidebar: boolean;
        seenCount: number;
        endingsCount: number;
        isUploadingSave: boolean;
        saveUploadError: string | null;
        displayNodes: DisplayNode[];
        displayEdges: DisplayEdge[];
        colorMode: 'light' | 'dark';
        layoutVersion: number;
        navigationTarget: string | null;
        isCalculatingPath: boolean;
        hasNavigationPath: boolean;
        navigationSteps: any[];
        choiceCount: number;
        conditionedStepCount: number;
        routeWordCount: number;
        routePreferences: RoutePreference[];
        startNodeId: string | null;
        routePlanningVariables: any[];
        preferenceVariable: string;
        preferenceMode: RoutePreference['mode'];
        preferenceValue: string;
        selectedNodeData: any;
        seenNodeIds: any;
        endings: string[];
        variables: any[];
        getMiniMapNodeColor: (node: any) => string;
        getMiniMapNodeStrokeColor: (node: any) => string;
        onLoadVersion: (versionId: number) => void;
        onChangeLanguage: (language: string | null) => void;
        onSearch: (query: string) => void;
        onToggleUnreachable: (checked: boolean) => void;
        onToggleSidebar: () => void;
        onUploadSaveFile: (file: File) => void;
        onClearSeenData: () => void;
        onSelectNode: (nodeId: string | null) => void;
        onClearPath: () => void;
        onMovePreference: (fromIndex: number, toIndex: number) => void;
        onRemovePreference: (index: number) => void;
        onPreferenceVariableChange: (value: string) => void;
        onPreferenceModeChange: (value: RoutePreference['mode']) => void;
        onPreferenceValueChange: (value: string) => void;
        onAddPreference: () => void;
        onClearPreferences: () => void;
        onNavigateTo: (target: string) => void;
        onSelectEnding: (ending: string) => void;
    }

    let {
        game,
        gameVersions,
        routeGraph,
        visibleLanguages,
        selectedVersionId,
        selectedLanguage,
        searchQuery,
        canInspectFullRouteMap,
        includeUnreachable,
        isLoading,
        showSidebar,
        seenCount,
        endingsCount,
        isUploadingSave,
        saveUploadError,
        displayNodes,
        displayEdges,
        colorMode,
        layoutVersion,
        navigationTarget,
        isCalculatingPath,
        hasNavigationPath,
        navigationSteps,
        choiceCount,
        conditionedStepCount,
        routeWordCount,
        routePreferences,
        startNodeId,
        routePlanningVariables,
        preferenceVariable,
        preferenceMode,
        preferenceValue,
        selectedNodeData,
        seenNodeIds,
        endings,
        variables,
        getMiniMapNodeColor,
        getMiniMapNodeStrokeColor,
        onLoadVersion,
        onChangeLanguage,
        onSearch,
        onToggleUnreachable,
        onToggleSidebar,
        onUploadSaveFile,
        onClearSeenData,
        onSelectNode,
        onClearPath,
        onMovePreference,
        onRemovePreference,
        onPreferenceVariableChange,
        onPreferenceModeChange,
        onPreferenceValueChange,
        onAddPreference,
        onClearPreferences,
        onNavigateTo,
        onSelectEnding,
    }: RouteMapWorkspaceProps = $props();
</script>

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
        <RouteMapToolbar
            {gameVersions}
            {visibleLanguages}
            {selectedVersionId}
            {selectedLanguage}
            {searchQuery}
            {canInspectFullRouteMap}
            {includeUnreachable}
            {isLoading}
            {showSidebar}
            {seenCount}
            totalNodes={routeGraph.total_nodes}
            totalEdges={routeGraph.total_edges}
            {endingsCount}
            {isUploadingSave}
            {saveUploadError}
            {onLoadVersion}
            {onChangeLanguage}
            {onSearch}
            {onToggleUnreachable}
            {onToggleSidebar}
            {onUploadSaveFile}
            {onClearSeenData}
        />

        <div class="flex gap-6" style="height: calc(100vh - 200px);">
            <Card variant="outline" padding="none" class="flex-1 overflow-hidden dark:bg-gray-900" style="min-width: 0">
                <SvelteFlow
                    nodes={displayNodes as any[]}
                    edges={displayEdges as any[]}
                    {nodeTypes}
                    {edgeTypes}
                    {colorMode}
                    minZoom={ROUTE_MAP_MIN_ZOOM}
                    onnodeclick={(event: any) => {
                        if (event.node?.data?.node_type === 'condition') return;

                        onSelectNode(event.node?.id ?? null);
                    }}
                    onpaneclick={() => onSelectNode(null)}
                    class=""
                >
                    <RouteMapFitView {layoutVersion} />
                    <Background />
                    <Controls />
                    <MiniMap
                        class="route-map-minimap"
                        width={260}
                        height={180}
                        bgColor={colorMode === 'dark' ? '#0f172a' : '#f8fafc'}
                        maskColor={colorMode === 'dark' ? 'rgba(15, 23, 42, 0.48)' : 'rgba(15, 23, 42, 0.08)'}
                        maskStrokeColor={colorMode === 'dark' ? '#93c5fd' : '#2563eb'}
                        maskStrokeWidth={2}
                        nodeColor={getMiniMapNodeColor}
                        nodeStrokeColor={getMiniMapNodeStrokeColor}
                        nodeStrokeWidth={1.75}
                        nodeComponent={RouteMapMiniMapNode}
                        pannable
                        zoomable
                        ariaLabel="Route map overview"
                    />
                </SvelteFlow>
            </Card>

            {#if showSidebar}
                <Card variant="outline" padding="sm" class="w-72 shrink-0 overflow-y-auto dark:bg-gray-900">
                    {#if navigationTarget}
                        <RouteMapPathPanel
                            {navigationTarget}
                            {isCalculatingPath}
                            {hasNavigationPath}
                            {navigationSteps}
                            {choiceCount}
                            {conditionedStepCount}
                            {routeWordCount}
                            {routePreferences}
                            {startNodeId}
                            {onClearPath}
                            onSelectNode={(nodeId) => onSelectNode(nodeId)}
                        />
                    {/if}

                    <RouteMapPrioritiesPanel
                        {routePreferences}
                        {routePlanningVariables}
                        {preferenceVariable}
                        {preferenceMode}
                        {preferenceValue}
                        {onMovePreference}
                        {onRemovePreference}
                        {onPreferenceVariableChange}
                        {onPreferenceModeChange}
                        {onPreferenceValueChange}
                        {onAddPreference}
                        {onClearPreferences}
                    />

                    <RouteMapSelectedNodePanel
                        selectedNode={selectedNodeData}
                        {seenNodeIds}
                        {startNodeId}
                        {navigationTarget}
                        onSelectNode={(nodeId) => onSelectNode(nodeId)}
                        {onNavigateTo}
                    />

                    <RouteMapSummaryListsPanel {endings} {variables} {onSelectEnding} />
                </Card>
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
