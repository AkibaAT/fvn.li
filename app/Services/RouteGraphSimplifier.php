<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

class RouteGraphSimplifier
{
    public function build(Collection $labels, Collection $edges, array $wordCounts = []): array
    {
        // Pre-index edges for O(1) lookup instead of O(n) per label
        $outgoing = [];
        $incoming = [];
        foreach ($labels as $label) {
            $outgoing[$label->name] = [];
            $incoming[$label->name] = [];
        }
        foreach ($edges as $edge) {
            $outgoing[$edge->from_label][] = $edge->to_label;
            $incoming[$edge->to_label][] = $edge->from_label;
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
        $labelsByName = $labels->keyBy('name');

        foreach ($branchPoints as $name => $_) {
            $label = $labelsByName->get($name);
            $simplifiedNodes[] = [
                'id' => $name,
                'label' => $name,
                'type' => 'branch',
                'is_start' => $name === 'start' || $name === 'labels.start',
                'is_ending' => (bool) ($label->is_ending ?? false),
                'returns_to_caller' => (bool) ($label->returns_to_caller ?? false),
                'word_count' => $wordCounts[$name] ?? 0,
            ];
            $labelToSimplified[$name] = $name;
        }

        foreach ($chains as $chain) {
            $first = $chain[0];
            $collapsedId = 'chain_'.implode('_', array_slice($chain, 0, 3)).'_'.count($chain);
            $chainWordCount = array_sum(array_map(fn ($n) => $wordCounts[$n] ?? 0, $chain));

            $simplifiedNodes[] = [
                'id' => $collapsedId,
                'label' => count($chain).' nodes',
                'type' => 'chain',
                'chain_labels' => $chain,
                'first_label' => $first,
                'last_label' => $chain[count($chain) - 1],
                'is_start' => false,
                'is_ending' => false,
                'word_count' => $chainWordCount,
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
            $edgeKey = $sourceId.':'.$targetId.':'.$edge->edge_type;
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
