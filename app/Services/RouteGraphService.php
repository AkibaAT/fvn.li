<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use Illuminate\Support\Collection;

class RouteGraphService
{
    public function buildGraph(GameVersion $version): array
    {
        $labels = $version->routeLabels()->get();
        $edges = $version->routeEdges()->get();
        $menuChoices = $version->routeMenuChoices()->get();
        $variables = $version->routeVariables()->get();
        $variableChanges = $version->routeVariableChanges()->get();

        $edgeMapByFrom = $edges->groupBy('from_label');
        $varChangesByLabel = $variableChanges->groupBy('label');

        $nodes = $labels->map(function ($label) use ($edgeMapByFrom, $varChangesByLabel, $menuChoices) {
            $name = $label->name;
            $outgoingEdges = $edgeMapByFrom->get($name, collect());
            $isStart = $name === 'labels.start' || $name === 'start';
            $varChanges = $varChangesByLabel->get($name, collect());

            $hasMenuChoice = $menuChoices->filter(function ($mc) use ($name) {
                return $mc->from_label === $name && ! empty($mc->target_label);
            })->isNotEmpty();

            return [
                'id' => $name,
                'label' => $name,
                'is_ending' => (bool) $label->is_ending,
                'is_start' => $isStart,
                'has_menu_choice' => $hasMenuChoice,
                'file_path' => $label->file_path,
                'line_number' => $label->line_number,
                'outgoing_count' => $outgoingEdges->count(),
                'variable_changes' => $varChanges->map(function ($vc) {
                    return [
                        'variable' => $vc->variable_name,
                        'operation' => $vc->operation,
                        'value' => $vc->value,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        $processedEdges = $edges->map(function ($edge) use ($menuChoices) {
            $edgeData = [
                'id' => $edge->from_label . ':' . $edge->to_label . ':' . $edge->edge_type,
                'source' => $edge->from_label,
                'target' => $edge->to_label,
                'edge_type' => $edge->edge_type,
                'condition' => $edge->condition,
                'file_path' => $edge->file_path,
                'line_number' => $edge->line_number,
            ];

            if ($edge->edge_type === 'menu_choice') {
                $matchingChoice = $menuChoices->first(function ($mc) use ($edge) {
                    return $mc->from_label === $edge->from_label
                        && $mc->target_label === $edge->to_label;
                });
                if ($matchingChoice) {
                    $edgeData['choice_text'] = $matchingChoice->text;
                    $edgeData['condition'] = $matchingChoice->condition;
                }
            }

            return $edgeData;
        })->values()->toArray();

        $variableData = $variables->map(function ($v) use ($variableChanges) {
            return [
                'name' => $v->name,
                'default_value' => $v->default_value,
                'type' => $v->type,
                'change_count' => $variableChanges->where('variable_name', $v->name)->count(),
            ];
        })->values()->toArray();

        $endings = $labels->where('is_ending', true)->pluck('name')->values()->toArray();

        $simplified = $this->buildSimplifiedGraph($labels, $edges);

        return [
            'nodes' => $nodes,
            'edges' => $processedEdges,
            'variables' => $variableData,
            'endings' => $endings,
            'total_nodes' => $labels->count(),
            'total_edges' => $edges->count(),
            'simplified' => $simplified,
            'has_graph_data' => true,
        ];
    }

    public function buildSimplifiedGraph(Collection $labels, Collection $edges): array
    {
        $outgoing = [];
        foreach ($labels as $label) {
            $outgoing[$label->name] = $edges->where('from_label', $label->name)->pluck('to_label')->values()->toArray();
        }

        $incoming = [];
        foreach ($labels as $label) {
            $incoming[$label->name] = $edges->where('to_label', $label->name)->pluck('from_label')->values()->toArray();
        }

        $branchPoints = [];
        $chainNodes = [];

        foreach ($labels as $label) {
            $name = $label->name;
            $outCount = count($outgoing[$name] ?? []);
            $inCount = count($incoming[$name] ?? []);
            $isStart = $name === 'start' || $name === 'labels.start';
            $isEnding = (bool) $label->is_ending;

            if ($isStart || $isEnding || $outCount !== 1 || $inCount !== 1) {
                $branchPoints[$name] = true;
            } else {
                $chainNodes[$name] = true;
            }
        }

        $chains = [];
        $visited = [];

        foreach ($chainNodes as $name => $_) {
            if (isset($visited[$name])) {
                continue;
            }

            $chain = [$name];
            $visited[$name] = true;
            $current = $name;

            while (true) {
                $outs = $outgoing[$current] ?? [];
                if (count($outs) !== 1) {
                    break;
                }
                $next = $outs[0];
                if (isset($branchPoints[$next]) || isset($visited[$next])) {
                    break;
                }
                $chain[] = $next;
                $visited[$next] = true;
                $current = $next;
            }

            if (count($chain) > 1) {
                $chains[] = $chain;
            }
        }

        $simplifiedNodes = [];
        $labelToSimplified = [];

        foreach ($branchPoints as $name => $_) {
            $label = $labels->where('name', $name)->first();
            $simplifiedNodes[] = [
                'id' => $name,
                'label' => $name,
                'type' => 'branch',
                'is_start' => $name === 'start' || $name === 'labels.start',
                'is_ending' => (bool) ($label->is_ending ?? false),
            ];
            $labelToSimplified[$name] = $name;
        }

        foreach ($chains as $chain) {
            $first = $chain[0];
            $collapsedId = 'chain_' . md5(implode('|', $chain));

            $simplifiedNodes[] = [
                'id' => $collapsedId,
                'label' => count($chain) . ' nodes',
                'type' => 'chain',
                'chain_labels' => $chain,
                'first_label' => $first,
                'last_label' => $chain[count($chain) - 1],
                'is_start' => false,
                'is_ending' => false,
            ];

            foreach ($chain as $node) {
                $labelToSimplified[$node] = $collapsedId;
            }
        }

        $simplifiedEdges = [];
        $seen = [];

        foreach ($edges as $edge) {
            $sourceId = $labelToSimplified[$edge->from_label] ?? $edge->from_label;
            $targetId = $labelToSimplified[$edge->to_label] ?? $edge->to_label;

            if ($sourceId === $targetId) {
                continue;
            }
            $edgeKey = $sourceId . ':' . $targetId . ':' . $edge->edge_type;
            if (isset($seen[$edgeKey])) {
                continue;
            }
            $seen[$edgeKey] = true;
            $simplifiedEdges[] = [
                'id' => $edgeKey,
                'source' => $sourceId,
                'target' => $targetId,
                'edge_type' => $edge->edge_type,
            ];
        }

        return [
            'nodes' => $simplifiedNodes,
            'edges' => $simplifiedEdges,
            'chain_count' => count($chains),
        ];
    }
}
