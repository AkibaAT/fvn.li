<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

class RouteGraphResultBuilder
{
    public function build(
        array $nodes,
        array $edges,
        Collection $variables,
        Collection $variableChanges,
        Collection $labels,
        array $expandedEndingReplacements,
        bool $includeUnreachable,
        int $revision
    ): array {
        $variableData = $this->variableData($variables, $variableChanges);
        $endings = $this->endings($labels, $nodes, $expandedEndingReplacements);
        $layout = app(RouteGraphLayoutService::class)->layout($nodes, $edges);

        return [
            'graph_revision' => $revision,
            'includes_unreachable' => $includeUnreachable,
            'nodes' => $nodes,
            'edges' => $edges,
            'variables' => $variableData,
            'endings' => $endings,
            'layout' => $layout,
            'total_nodes' => collect($nodes)->reject(fn (array $node) => (bool) ($node['is_condition_scope'] ?? false))->count(),
            'total_edges' => collect($edges)->reject(fn (array $edge) => ($edge['edge_type'] ?? null) === 'condition_scope')->count(),
            'has_graph_data' => true,
        ];
    }

    private function variableData(Collection $variables, Collection $variableChanges): array
    {
        $varChangeCounts = [];
        foreach ($variableChanges as $vc) {
            $varChangeCounts[$vc->variable_name] = ($varChangeCounts[$vc->variable_name] ?? 0) + 1;
        }

        return $variables->map(fn ($v) => [
            'name' => $v->name,
            'default_value' => $v->default_value,
            'type' => $v->type,
            'change_count' => $varChangeCounts[$v->name] ?? 0,
        ])->values()->toArray();
    }

    private function endings(Collection $labels, array $nodes, array $expandedEndingReplacements): array
    {
        $visibleNodeIds = collect($nodes)->pluck('id')->flip();
        $endings = $labels
            ->where('is_ending', true)
            ->pluck('name')
            ->reject(fn (string $name) => isset($expandedEndingReplacements[$name]))
            ->filter(fn (string $name) => isset($visibleNodeIds[$name]))
            ->values()
            ->toArray();

        foreach ($expandedEndingReplacements as $endingId) {
            if (isset($visibleNodeIds[$endingId]) && ! in_array($endingId, $endings, true)) {
                $endings[] = $endingId;
            }
        }

        return $endings;
    }
}
