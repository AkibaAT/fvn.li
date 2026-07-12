import type { DisplayEdge, DisplayNode } from '@/types/route-graph';

type RouteLayoutElements = {
    nodes: DisplayNode[];
    edges: DisplayEdge[];
};

type StoredLayoutPosition = {
    x: number;
    y: number;
};

function getConditionNodeId(edge: DisplayEdge, label: string): string {
    return `condition:${encodeURIComponent(edge.source)}:${encodeURIComponent(label)}`;
}

export function getLayoutPosition(nodeId: string, layoutNodes: Record<string, StoredLayoutPosition> | undefined): StoredLayoutPosition {
    const position = layoutNodes?.[nodeId];

    if (!position) {
        // A missing stored position (e.g. server/client id derivation drift)
        // degrades that node's placement instead of crashing the whole page.
        console.warn(`Route map node [${nodeId}] has no stored layout position; falling back to origin.`);

        return { x: 0, y: 0 };
    }

    return { x: position.x, y: position.y };
}

function createConditionNode(edge: DisplayEdge, label: string, layoutNodes: Record<string, StoredLayoutPosition> | undefined): DisplayNode {
    const id = getConditionNodeId(edge, label);

    return {
        id,
        type: 'condition',
        data: {
            id,
            label,
            node_type: 'condition',
            is_ending: false,
            is_start: false,
            has_menu_choice: false,
            file_path: edge.data.file_path ?? null,
            line_number: edge.data.line_number ?? 0,
            outgoing_count: 1,
            word_count: 0,
            choices: [],
            variable_changes: [],
            edgeIds: edge.data.edgeIds,
            targets_unresolved_node: edge.data.targets_unresolved_node,
        },
        position: getLayoutPosition(id, layoutNodes),
        selectable: false,
        connectable: false,
        focusable: false,
        ariaLabel: `Route condition: ${label}`,
        zIndex: 2,
    };
}

function createSplitEdge(edge: DisplayEdge, idSuffix: string, source: string, target: string): DisplayEdge {
    return {
        ...edge,
        id: `${edge.id}:${idSuffix}`,
        source,
        target,
        label: undefined,
        labelStyle: undefined,
        zIndex: 0,
    };
}

export function buildRouteLayoutElements(
    nodes: DisplayNode[],
    edges: DisplayEdge[],
    layoutNodes: Record<string, StoredLayoutPosition> | undefined,
): RouteLayoutElements {
    const displayNodes = [...nodes];
    const displayEdges: DisplayEdge[] = [];
    const conditionNodes = new Map<string, DisplayNode>();
    const conditionInEdges = new Map<string, DisplayEdge>();

    for (const edge of edges) {
        const label = edge.label?.trim();

        if (!label) {
            displayEdges.push(edge);
            continue;
        }

        const conditionNodeId = getConditionNodeId(edge, label);
        let conditionNode = conditionNodes.get(conditionNodeId);
        if (!conditionNode) {
            conditionNode = createConditionNode(edge, label, layoutNodes);
            conditionNodes.set(conditionNodeId, conditionNode);
            displayNodes.push(conditionNode);
            const conditionInEdge = createSplitEdge(edge, 'condition-in', edge.source, conditionNode.id);
            conditionInEdges.set(conditionNodeId, conditionInEdge);
            displayEdges.push(conditionInEdge);
        } else {
            conditionNode.data.edgeIds = [...(conditionNode.data.edgeIds ?? []), ...edge.data.edgeIds];
            conditionNode.data.targets_unresolved_node = Boolean(conditionNode.data.targets_unresolved_node || edge.data.targets_unresolved_node);

            // The shared incoming split edge must reflect every merged edge,
            // otherwise path/selection highlighting misses all but the first.
            const conditionInEdge = conditionInEdges.get(conditionNodeId);
            if (conditionInEdge) {
                conditionInEdge.data = {
                    ...conditionInEdge.data,
                    edgeIds: conditionNode.data.edgeIds,
                    targets_unresolved_node: conditionNode.data.targets_unresolved_node,
                };
            }
        }

        displayEdges.push(createSplitEdge(edge, 'condition-out', conditionNode.id, edge.target));
    }

    return { nodes: displayNodes, edges: displayEdges };
}
