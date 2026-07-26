<?php

declare(strict_types=1);

namespace App\Services;

class RouteGraphPostProcessor
{
    public function __construct(
        private readonly RouteGraphConditionService $conditions = new RouteGraphConditionService
    ) {}

    public function addMissingEndpointNodes(array $nodes, array $edges): array
    {
        $nodeIds = [];
        foreach ($nodes as $node) {
            $nodeIds[$node['id']] = true;
        }

        foreach ($edges as $edge) {
            foreach (['source', 'target'] as $key) {
                $id = $edge[$key] ?? null;
                if (! is_string($id) || $id === '' || isset($nodeIds[$id])) {
                    continue;
                }

                $nodes[] = [
                    'id' => $id,
                    'label' => $id,
                    'node_type' => 'label',
                    'is_ending' => false,
                    'returns_to_caller' => false,
                    'is_start' => false,
                    'has_menu_choice' => false,
                    'file_path' => null,
                    'line_number' => 0,
                    'outgoing_count' => 0,
                    'word_count' => 0,
                    'choices' => [],
                    'variable_changes' => [],
                    'is_unresolved' => true,
                ];
                $nodeIds[$id] = true;
            }
        }

        return $nodes;
    }

    /**
     * Route maps should describe playable flow from the game's entry label.
     * Keep unrooted fixture graphs intact, but when a start node exists, hide
     * script fragments that Ren'Py cannot reach from that start path.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    public function filterReachableFromStart(array $nodes, array $edges): array
    {
        $nodeIds = [];
        $startIds = [];

        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }

            $nodeIds[$id] = true;
            // Entry points are roots alongside start: the engine and game code
            // enter them by name, so no edge leads to them and everything they
            // reach would otherwise be dropped as unreachable.
            if (($node['is_start'] ?? false)
                || ($node['is_entry_point'] ?? false)
                || $id === 'start'
                || $id === 'labels.start'
            ) {
                $startIds[] = $id;
            }
        }

        if (empty($startIds)) {
            return [$nodes, $edges];
        }

        $adjacency = [];
        foreach ($edges as $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (! is_string($source) || ! is_string($target)) {
                continue;
            }

            if (! isset($nodeIds[$source], $nodeIds[$target])) {
                continue;
            }

            $adjacency[$source][] = $target;
        }

        $visited = [];
        $queue = [];
        foreach (array_unique($startIds) as $startId) {
            $visited[$startId] = true;
            $queue[] = $startId;
        }

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($adjacency[$current] ?? [] as $target) {
                if (isset($visited[$target])) {
                    continue;
                }

                $visited[$target] = true;
                $queue[] = $target;
            }
        }

        $filteredNodes = array_values(array_filter(
            $nodes,
            fn (array $node) => isset($visited[$node['id'] ?? null])
        ));

        $filteredEdges = array_values(array_filter(
            $edges,
            fn (array $edge) => isset($visited[$edge['source'] ?? null], $visited[$edge['target'] ?? null])
        ));

        return [$filteredNodes, $filteredEdges];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    public function factorConditionScopeEdges(array $nodes, array $edges): array
    {
        $scopesBySource = [];

        foreach ($edges as $edge) {
            $stack = $this->conditions->edgeConditionStack($edge);
            if (count($stack) < 2) {
                continue;
            }

            $source = (string) ($edge['source'] ?? '');
            if ($source === '') {
                continue;
            }

            $prefixStack = [];
            foreach ($stack as $localCondition) {
                $prefixStack[] = $localCondition;
                $prefixCondition = $this->conditions->combinedConditionStack($prefixStack);
                $normalizedPrefix = $this->conditions->normalizedConditionForComparison($prefixCondition);

                if ($normalizedPrefix === '') {
                    continue;
                }

                $scopesBySource[$source][$normalizedPrefix] = [
                    'id' => $this->conditions->conditionScopeNodeId($source, $prefixCondition),
                    'label' => $this->conditions->conditionScopeLabel($localCondition),
                    'condition' => $prefixCondition,
                    'file_path' => $edge['file_path'] ?? null,
                    'line_number' => (int) ($edge['line_number'] ?? 0),
                ];
            }
        }

        if ($scopesBySource === []) {
            foreach ($edges as &$edge) {
                unset($edge['condition_stack']);
            }
            unset($edge);

            return [$nodes, $edges];
        }

        $nodeIds = [];
        foreach ($nodes as $node) {
            $nodeIds[(string) ($node['id'] ?? '')] = true;
        }

        foreach ($scopesBySource as $scopes) {
            foreach ($scopes as $scope) {
                if (isset($nodeIds[$scope['id']])) {
                    continue;
                }

                $nodes[] = [
                    'id' => $scope['id'],
                    'label' => $scope['label'],
                    'node_type' => 'condition',
                    'is_ending' => false,
                    'returns_to_caller' => false,
                    'is_start' => false,
                    'has_menu_choice' => false,
                    'file_path' => $scope['file_path'],
                    'line_number' => $scope['line_number'],
                    'outgoing_count' => 0,
                    'word_count' => 0,
                    'choices' => [],
                    'variable_changes' => [],
                    'is_condition_scope' => true,
                ];
                $nodeIds[$scope['id']] = true;
            }
        }

        $transformedEdges = [];
        $scopeEdgeKeys = [];
        $addScopeEdge = function (string $source, string $target, array $edge) use (&$transformedEdges, &$scopeEdgeKeys): void {
            if ($source === $target) {
                return;
            }

            $key = $source . "\0" . $target;
            if (isset($scopeEdgeKeys[$key])) {
                return;
            }

            $scopeEdgeKeys[$key] = true;
            $transformedEdges[] = [
                'id' => 'condition_scope:' . md5($key),
                'source' => $source,
                'target' => $target,
                'edge_type' => 'condition_scope',
                'condition' => null,
                'file_path' => $edge['file_path'] ?? null,
                'line_number' => $edge['line_number'] ?? 0,
            ];
        };

        foreach ($edges as $edge) {
            $stack = $this->conditions->edgeConditionStack($edge);
            unset($edge['condition_stack']);

            $source = (string) ($edge['source'] ?? '');

            if (count($stack) >= 2 && $source !== '') {
                $previous = $source;
                $prefixStack = [];

                foreach ($stack as $localCondition) {
                    $prefixStack[] = $localCondition;
                    $prefixCondition = $this->conditions->combinedConditionStack($prefixStack);
                    $scope = $scopesBySource[$source][$this->conditions->normalizedConditionForComparison($prefixCondition)] ?? null;

                    if ($scope === null) {
                        continue;
                    }

                    $addScopeEdge($previous, $scope['id'], $edge);
                    $previous = $scope['id'];
                }

                $edge['id'] = ((string) ($edge['id'] ?? md5(json_encode($edge) ?: 'edge'))) . ':condition_scope_target';
                $edge['source'] = $previous;
                $edge['condition'] = null;
                $transformedEdges[] = $edge;

                continue;
            }

            $edgeCondition = $this->conditions->normalizedConditionForComparison($edge['condition'] ?? null);
            $scope = $source !== '' && $edgeCondition !== ''
                ? ($scopesBySource[$source][$edgeCondition] ?? null)
                : null;

            if ($scope !== null) {
                $addScopeEdge($source, $scope['id'], $edge);
                $edge['id'] = ((string) ($edge['id'] ?? md5(json_encode($edge) ?: 'edge'))) . ':condition_scope';
                $edge['source'] = $scope['id'];
                $edge['condition'] = null;
            }

            $transformedEdges[] = $edge;
        }

        return [$nodes, $transformedEdges];
    }

    /**
     * When start can only reach a small subset of the graph, bridge to
     * disconnected components via synthetic edges.
     * This handles games that use screen-based navigation we can't statically resolve.
     */
    public function bridgeDisconnectedComponents(array $nodes, array $edges): array
    {
        if (empty($nodes) || empty($edges)) {
            return $edges;
        }

        $startId = null;
        $nodeIndex = [];
        foreach ($nodes as $n) {
            $nodeIndex[$n['id']] = $n;
            if ($n['is_start'] ?? false) {
                $startId = $n['id'];
            }
        }

        if (! $startId) {
            return $edges;
        }

        // Early exit: if start node is not a proper label node, skip bridging
        $startNode = $nodeIndex[$startId] ?? null;
        if (! $startNode || ($startNode['node_type'] ?? '') !== 'label') {
            return $edges;
        }

        // Iteratively bridge: BFS from start, find unreachable entry points, bridge, repeat
        $maxBridges = 20;
        for ($attempt = 0; $attempt < $maxBridges; $attempt++) {
            $adjacency = [];
            foreach ($edges as $e) {
                $adjacency[$e['source']][] = $e['target'];
            }

            $visited = [$startId => true];
            $queue = [$startId];
            // Track the last reachable menu-choice label during BFS
            $lastReachableMenuNode = $startId;
            while (! empty($queue)) {
                $current = array_shift($queue);
                foreach ($adjacency[$current] ?? [] as $target) {
                    if (! isset($visited[$target])) {
                        $visited[$target] = true;
                        $queue[] = $target;
                        // Track menu-choice labels as potential bridge sources
                        $targetNode = $nodeIndex[$target] ?? null;
                        if ($targetNode && ($targetNode['node_type'] ?? '') === 'label' && ($targetNode['has_menu_choice'] ?? false)) {
                            $lastReachableMenuNode = $target;
                        }
                    }
                }
            }

            if (count($visited) > count($nodes) * 0.95) {
                break; // Good enough
            }

            // Find unreachable label nodes (not choice/hub/ending sub-nodes) that have outgoing edges
            // These are likely game entry points we can't see
            $bestBridge = null;
            $bestOutgoing = 0;

            // Pre-index incoming edges by target for O(1) lookup instead of O(edges) per node
            $unreachableIncoming = [];
            foreach ($edges as $e) {
                if (! isset($visited[$e['target']])) {
                    $unreachableIncoming[$e['target']] = ($unreachableIncoming[$e['target']] ?? 0) + 1;
                }
            }

            foreach ($nodes as $n) {
                $id = $n['id'];
                if (isset($visited[$id])) {
                    continue;
                }
                if (($n['node_type'] ?? '') !== 'label') {
                    continue;
                }

                $outCount = count($adjacency[$id] ?? []);
                if ($outCount <= 0) {
                    continue;
                }

                // Prefer nodes with no incoming edges from unreachable nodes (true entry points)
                $hasUnreachableIncoming = ($unreachableIncoming[$id] ?? 0) > 0;

                $score = $outCount + ($hasUnreachableIncoming ? 0 : 1000);
                if ($score > $bestOutgoing) {
                    $bestOutgoing = $score;
                    $bestBridge = $id;
                }
            }

            if (! $bestBridge) {
                break;
            }

            $bridgeSource = $lastReachableMenuNode;

            $edges[] = [
                'id' => $bridgeSource . ':' . $bestBridge . ':bridge',
                'source' => $bridgeSource,
                'target' => $bestBridge,
                'edge_type' => 'flow',
                'condition' => null,
            ];
        }

        return $edges;
    }

    public function inferElseConditions(array $edges): array
    {
        // Group edges by source
        $bySource = [];
        foreach ($edges as $i => $edge) {
            $bySource[$edge['source']][] = $i;
        }

        foreach ($bySource as $indices) {
            if (count($indices) < 2) {
                continue;
            }

            // Find conditions on conditional edges from this source
            $conditions = [];
            $unconditionalFlowIndices = [];
            foreach ($indices as $i) {
                $e = $edges[$i];
                $isUnconditional = empty($e['condition']) || $e['condition'] === 'True';
                if (! $isUnconditional) {
                    $conditions[] = $e['condition'];
                } elseif (in_array($e['edge_type'], ['flow', 'jump'])) {
                    $unconditionalFlowIndices[] = $i;
                }
            }

            if (empty($conditions) || empty($unconditionalFlowIndices)) {
                continue;
            }

            // Remove unconditional flow edges that duplicate conditional edges
            // only when the conditionals do not actually branch to another
            // target. If conditionals branch elsewhere, the fall-through edge
            // is the implicit else path and must remain visible.
            $conditionalTargets = [];
            foreach ($indices as $i) {
                $cond = $edges[$i]['condition'] ?? null;
                if (! empty($cond) && $cond !== 'True') {
                    $conditionalTargets[$edges[$i]['target']] = true;
                }
            }
            $hasConditionalBranch = count($conditionalTargets) > 1;
            foreach ($unconditionalFlowIndices as $j => $i) {
                if (! $hasConditionalBranch && isset($conditionalTargets[$edges[$i]['target']])) {
                    unset($edges[$i]);
                    unset($unconditionalFlowIndices[$j]);
                }
            }

            // For remaining unconditional flow edges, infer the else condition
            if (! empty($conditions) && ! empty($unconditionalFlowIndices)) {
                $conditionParts = array_map(fn (string $condition) => '(' . $condition . ')', array_values(array_unique($conditions)));
                $elseCondition = 'not (' . implode(' or ', $conditionParts) . ')';

                foreach ($unconditionalFlowIndices as $i) {
                    if (isset($edges[$i])) {
                        $edges[$i]['condition'] = $elseCondition;
                    }
                }
            }
        }

        return array_values($edges);
    }
}
