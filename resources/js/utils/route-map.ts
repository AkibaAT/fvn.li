import type { RouteEdge, RoutePreference } from '@/types/route-graph';

export type VisualRouteEdge = RouteEdge & {
    edgeIds: string[];
    collapsed_edges: RouteEdge[];
};

export function debounce<T extends (...args: Parameters<T>) => ReturnType<T>>(fn: T, delay: number): (...args: Parameters<T>) => void {
    let timeoutId: ReturnType<typeof setTimeout>;

    return (...args: Parameters<T>) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

export function appendStyle(base: unknown, extra: string): string {
    const baseStyle = typeof base === 'string' ? base.trim() : '';

    return baseStyle ? `${baseStyle};${extra}` : extra;
}

export function formatReadingTime(words: number): string {
    const minutes = words / 200;
    if (minutes < 1) return '< 1 min';
    if (minutes < 60) return `~${Math.round(minutes)} min`;

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = Math.round(minutes % 60);

    return remainingMinutes > 0 ? `~${hours}h ${remainingMinutes}m` : `~${hours}h`;
}

export function formatRoutePreference(pref: RoutePreference): string {
    if (pref.mode === 'maximize') return `${pref.variable} max`;
    if (pref.mode === 'minimize') return `${pref.variable} min`;

    return `${pref.variable} = ${pref.value}`;
}

export function formatEdgeLabel(edge: RouteEdge): string | undefined {
    const condition = edge.condition?.trim();
    const isElseCondition = condition?.startsWith('not (') ?? false;
    const conditionLabel = condition && condition !== 'True' ? (isElseCondition ? 'else' : `if ${condition}`) : null;
    const choiceText = edge.choice_text?.trim();

    if (choiceText && conditionLabel) return `${choiceText} · ${conditionLabel}`;

    return choiceText || conditionLabel || undefined;
}

export function formatCollapsedEdgeLabel(edges: RouteEdge[]): string | undefined {
    const labels = new Set<string>();

    for (const edge of edges) {
        const label = formatEdgeLabel(edge);
        if (label) labels.add(label);
    }

    return labels.size > 0 ? [...labels].join('\n') : undefined;
}

export function collapseRouteEdges(edges: RouteEdge[]): VisualRouteEdge[] {
    const edgeGroups = new Map<string, RouteEdge[]>();

    for (const edge of edges) {
        const key = `${edge.source}\u0000${edge.target}`;
        edgeGroups.set(key, [...(edgeGroups.get(key) ?? []), edge]);
    }

    return [...edgeGroups.values()].map((group) => {
        const primaryEdge = group.find((edge) => edge.edge_type === 'menu_choice') ?? group[0]!;
        const edgeIds = group.map((edge) => edge.id);
        const collapsedEdgeType = group.some((edge) => edge.edge_type === 'menu_choice') ? 'menu_choice' : primaryEdge.edge_type;

        return {
            ...primaryEdge,
            id: `connection:${encodeURIComponent(primaryEdge.source)}:${encodeURIComponent(primaryEdge.target)}`,
            edge_type: collapsedEdgeType,
            choice_text: null,
            condition: null,
            edgeIds,
            collapsed_edges: group,
        };
    });
}

export function getParallelEdgeLanes(edges: VisualRouteEdge[]): Map<string, { index: number; total: number }> {
    const labeledSourceTotals = new Map<string, number>();
    const totals = new Map<string, number>();
    const counts = new Map<string, number>();
    const lanes = new Map<string, { index: number; total: number }>();

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
