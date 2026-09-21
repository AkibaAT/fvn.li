<script lang="ts">
    import { SvelteFlow, Background, Controls, MiniMap } from '@xyflow/svelte';
    import '@xyflow/svelte/dist/style.css';
    import { Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';
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
    <PageHeader title="Route Map" backHref={route('games.show', { game: game.slug })} backLabel={`Back to ${game.name}`} class="mb-6" />

    {#if !routeGraph?.has_graph_data}
        <div class="flex flex-col items-center justify-center py-20">
            <div class="text-lg text-fg-muted">Route graph data is generated when the game is parsed.</div>
            <p class="mt-2 text-sm text-fg-faint">This view will appear after the parser has produced route graph data.</p>
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
            <Card variant="flat" padding="none" class="flex-1 overflow-hidden" style="min-width: 0">
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
                >
                    <RouteMapFitView {layoutVersion} />
                    <Background />
                    <Controls />
                    <MiniMap
                        class="route-map-minimap"
                        width={260}
                        height={180}
                        bgColor="var(--surface)"
                        maskColor={colorMode === 'dark' ? 'rgba(15, 23, 42, 0.48)' : 'rgba(15, 23, 42, 0.08)'}
                        maskStrokeColor="var(--text-muted)"
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
                <Card variant="flat" padding="sm" class="w-72 shrink-0 overflow-y-auto">
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
        --rm-partial-bg: #f0fdf4;
        --rm-partial-border: #34d399;
        /* Edge-label (condition) nodes ride the flat surface tokens. */
        --rm-edge-label-bg: var(--surface);
        --rm-edge-label-text: var(--text);
        --rm-edge-label-border: var(--border-strong);
    }

    :global(.svelte-flow.dark) {
        --xy-node-choice-bg: #451a03;
        --xy-node-choice-border: #d97706;
        --xy-node-hub-bg: #1e1b4b;
        --xy-node-hub-border: #818cf8;
        --xy-edge-stroke-default: #94a3b8;
        --rm-path-bg: #1e3a5f;
        --rm-path-border: #60a5fa;
        --rm-seen-bg: #064e3b;
        --rm-seen-border: #34d399;
        --rm-partial-bg: #052e16;
        --rm-partial-border: #6ee7b7;
    }

    :global(.route-map-minimap) {
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }

    :global(.route-map-minimap .svelte-flow__minimap-svg) {
        border-radius: 8px;
    }
</style>
