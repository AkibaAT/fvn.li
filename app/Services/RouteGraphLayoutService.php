<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class RouteGraphLayoutService
{
    // dot gives the hierarchical shape a story flow reads best, but its running
    // time grows sharply with graph size. sfdp handles graphs dot cannot in a
    // fraction of the time, at the cost of a less structured arrangement, so it
    // stands in when dot cannot finish.
    private const PRIMARY_ENGINE = 'dot';

    private const FALLBACK_ENGINE = 'sfdp';

    private const REVISION = 1;

    private const POINTS_PER_INCH = 72.0;

    // A layout that cannot be produced in this budget is not one a reader would
    // wait for either. Failing quickly keeps an import from spending most of its
    // time on a view that is rebuildable afterwards.
    private const PROCESS_TIMEOUT_SECONDS = 120.0;

    private const PROCESS_MEMORY_BYTES = 1073741824;

    private const PROCESS_CPU_SECONDS = 120;

    private const NODE_DIMENSIONS = [
        'choiceWidth' => 184.0,
        'choiceBaseHeight' => 34.0,
        'choiceVariableHeight' => 16.0,
        'hubWidth' => 140.0,
        'hubHeight' => 54.0,
        'labelWidth' => 220.0,
        'labelBaseHeight' => 42.0,
        'promptLineHeight' => 14.0,
        'conditionWidth' => 260.0,
        'conditionBaseHeight' => 18.0,
        'conditionLineHeight' => 14.0,
    ];

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array{
     *     engine: string,
     *     revision: int,
     *     width: float,
     *     height: float,
     *     nodes: array<string, array{x: float, y: float, width: float, height: float}>
     * }
     */
    public function layout(array $nodes, array $edges): array
    {
        $layout = $this->buildLayoutElements($nodes, $edges);

        if ($layout['nodes'] === []) {
            return [
                'engine' => 'graphviz-' . self::PRIMARY_ENGINE,
                'revision' => self::REVISION,
                'width' => 0.0,
                'height' => 0.0,
                'nodes' => [],
            ];
        }

        $dot = $this->toDot($layout['nodes'], $layout['edges']);

        foreach ([self::PRIMARY_ENGINE, self::FALLBACK_ENGINE] as $engine) {
            $graph = $this->runEngine($engine, $dot);
            if ($graph === null) {
                continue;
            }

            try {
                return $this->extractLayout($graph, $layout['node_sizes'], $engine);
            } catch (RuntimeException $exception) {
                Log::info('Route map layout engine produced an unusable result', [
                    'engine' => $engine,
                    'error' => mb_substr($exception->getMessage(), 0, 300),
                ]);
            }
        }

        return $this->unpositioned('no GraphViz engine could lay out this graph');
    }

    /**
     * Run one GraphViz engine, or null when it cannot produce a layout.
     *
     * @return array<string, mixed>|null
     */
    private function runEngine(string $engine, string $dot): ?array
    {
        $process = new Process([
            '/usr/bin/prlimit',
            '--as=' . self::PROCESS_MEMORY_BYTES,
            '--cpu=' . self::PROCESS_CPU_SECONDS,
            '--',
            $engine,
            '-Tjson',
        ]);
        $process->setInput($dot);
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            Log::info('Route map layout engine timed out', ['engine' => $engine]);

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::info('Route map layout engine failed', [
                'engine' => $engine,
                'error' => mb_substr($process->getErrorOutput(), 0, 300),
            ]);

            return null;
        }

        try {
            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::info('Route map layout engine returned invalid JSON', [
                'engine' => $engine,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * A graph without coordinates.
     *
     * The nodes and edges are the substance of the map; positions are an
     * optimisation the viewer can do without. Returning this keeps a graph that
     * is too large or too slow to position from discarding the whole map.
     *
     * @return array{engine: string, revision: int, width: float, height: float, nodes: array<string, array{x: float, y: float, width: float, height: float}>}
     */
    private function unpositioned(string $reason): array
    {
        Log::warning('Route map layout unavailable; storing graph without positions', [
            'reason' => mb_substr($reason, 0, 500),
        ]);

        return [
            'engine' => 'graphviz-' . self::PRIMARY_ENGINE,
            'revision' => self::REVISION,
            'width' => 0.0,
            'height' => 0.0,
            'nodes' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array{
     *     nodes: array<int, array<string, mixed>>,
     *     edges: array<int, array{source: string, target: string}>,
     *     node_sizes: array<string, array{width: float, height: float}>
     * }
     */
    private function buildLayoutElements(array $nodes, array $edges): array
    {
        $layoutNodes = array_values($nodes);
        $layoutEdges = [];
        $nodeSizes = [];
        $unresolvedNodeIds = [];

        foreach ($layoutNodes as $node) {
            $nodeId = (string) $node['id'];
            $nodeSizes[$nodeId] = $this->estimateNodeSize($node);

            if (! empty($node['is_unresolved'])) {
                $unresolvedNodeIds[$nodeId] = true;
            }
        }

        $conditionNodeIds = [];

        foreach ($this->collapseRouteEdges($edges, $unresolvedNodeIds) as $edge) {
            $source = (string) $edge['source'];
            $target = (string) $edge['target'];
            $label = trim((string) ($edge['label'] ?? ''));

            if ($label === '') {
                $layoutEdges[] = ['source' => $source, 'target' => $target];

                continue;
            }

            $conditionId = $this->conditionNodeId($source, $label);
            if (! isset($conditionNodeIds[$conditionId])) {
                $conditionNode = [
                    'id' => $conditionId,
                    'label' => $label,
                    'node_type' => 'condition',
                ];

                $conditionNodeIds[$conditionId] = true;
                $layoutNodes[] = $conditionNode;
                $nodeSizes[$conditionId] = $this->estimateNodeSize($conditionNode);
                $layoutEdges[] = ['source' => $source, 'target' => $conditionId];
            }

            $layoutEdges[] = ['source' => $conditionId, 'target' => $target];
        }

        return [
            'nodes' => $layoutNodes,
            'edges' => $layoutEdges,
            'node_sizes' => $nodeSizes,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<string, true>  $unresolvedNodeIds
     * @return array<int, array<string, mixed>>
     */
    private function collapseRouteEdges(array $edges, array $unresolvedNodeIds): array
    {
        $groups = [];
        $groupOrder = [];

        foreach ($edges as $edge) {
            $source = (string) $edge['source'];
            $target = (string) $edge['target'];
            $key = $source . "\0" . $target;

            if (! isset($groups[$key])) {
                $groups[$key] = [];
                $groupOrder[] = $key;
            }

            $groups[$key][] = $edge;
        }

        $collapsed = [];

        foreach ($groupOrder as $key) {
            $group = $groups[$key];
            $primary = $group[0];

            foreach ($group as $edge) {
                if (($edge['edge_type'] ?? null) === 'menu_choice') {
                    $primary = $edge;
                    break;
                }
            }

            $labels = [];
            foreach ($group as $edge) {
                $label = $this->formatEdgeLabel($edge);
                if ($label !== null) {
                    $labels[$label] = true;
                }
            }

            $label = $labels === [] ? null : implode("\n", array_keys($labels));
            if ($label === null && isset($unresolvedNodeIds[(string) $primary['target']])) {
                $label = 'missing target';
            }

            $collapsed[] = [
                'id' => $this->visualEdgeId((string) $primary['source'], (string) $primary['target']),
                'source' => (string) $primary['source'],
                'target' => (string) $primary['target'],
                'label' => $label,
            ];
        }

        return $collapsed;
    }

    /**
     * @param  array<string, mixed>  $edge
     */
    private function formatEdgeLabel(array $edge): ?string
    {
        $condition = trim((string) ($edge['condition'] ?? ''));
        $conditionLabel = null;

        if ($condition !== '' && $condition !== 'True') {
            $conditionLabel = str_starts_with($condition, 'not (') ? 'else' : 'if ' . $condition;
        }

        $choiceText = trim((string) ($edge['choice_text'] ?? ''));

        if ($choiceText !== '' && $conditionLabel !== null) {
            return $choiceText . ' · ' . $conditionLabel;
        }

        return $choiceText !== '' ? $choiceText : $conditionLabel;
    }

    private function visualEdgeId(string $source, string $target): string
    {
        return 'connection:' . $this->encodeURIComponent($source) . ':' . $this->encodeURIComponent($target);
    }

    private function conditionNodeId(string $source, string $label): string
    {
        return 'condition:' . $this->encodeURIComponent($source) . ':' . $this->encodeURIComponent($label);
    }

    private function encodeURIComponent(string $value): string
    {
        return str_replace(
            ['%21', '%27', '%28', '%29', '%2A'],
            ['!', "'", '(', ')', '*'],
            rawurlencode($value)
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{width: float, height: float}
     */
    private function estimateNodeSize(array $node): array
    {
        $nodeType = (string) ($node['node_type'] ?? '');

        if ($nodeType === 'condition') {
            $estimatedLines = $this->estimateWrappedLines((string) ($node['label'] ?? ''), 42);

            return [
                'width' => self::NODE_DIMENSIONS['conditionWidth'],
                'height' => self::NODE_DIMENSIONS['conditionBaseHeight'] + ($estimatedLines * self::NODE_DIMENSIONS['conditionLineHeight']),
            ];
        }

        if ($nodeType === 'choice') {
            return [
                'width' => self::NODE_DIMENSIONS['choiceWidth'],
                'height' => self::NODE_DIMENSIONS['choiceBaseHeight'] + (! empty($node['var_summary']) ? self::NODE_DIMENSIONS['choiceVariableHeight'] : 0.0),
            ];
        }

        if ($nodeType === 'hub') {
            return [
                'width' => self::NODE_DIMENSIONS['hubWidth'],
                'height' => self::NODE_DIMENSIONS['hubHeight'],
            ];
        }

        $prompt = (string) ($node['menu_prompt'] ?? '');
        if ($prompt !== '') {
            $estimatedLines = $this->estimateWrappedLines($prompt, 30);

            return [
                'width' => self::NODE_DIMENSIONS['labelWidth'],
                'height' => self::NODE_DIMENSIONS['labelBaseHeight'] + ($estimatedLines * self::NODE_DIMENSIONS['promptLineHeight']),
            ];
        }

        return [
            'width' => self::NODE_DIMENSIONS['labelWidth'],
            'height' => self::NODE_DIMENSIONS['labelBaseHeight'],
        ];
    }

    private function estimateWrappedLines(string $text, int $charactersPerLine): int
    {
        $total = 0;

        foreach (explode("\n", $text) as $line) {
            $total += max(1, (int) ceil(mb_strlen($line) / $charactersPerLine));
        }

        return max(1, $total);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array{source: string, target: string}>  $edges
     */
    private function toDot(array $nodes, array $edges): string
    {
        $lines = [
            'digraph RouteMap {',
            'graph [rankdir=TB, nodesep="1.0", ranksep="1.35", margin="0", pad="0.55", outputorder=edgesfirst];',
            'node [shape=box, fixedsize=true, label="", margin="0"];',
            'edge [arrowsize="0.6"];',
        ];

        foreach ($nodes as $node) {
            $id = (string) $node['id'];
            $size = $this->estimateNodeSize($node);
            $width = $this->formatDotNumber($size['width'] / self::POINTS_PER_INCH);
            $height = $this->formatDotNumber($size['height'] / self::POINTS_PER_INCH);
            $lines[] = $this->dotQuote($id) . ' [width="' . $width . '", height="' . $height . '"];';
        }

        foreach ($edges as $edge) {
            $lines[] = $this->dotQuote($edge['source']) . ' -> ' . $this->dotQuote($edge['target']) . ';';
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function dotQuote(string $value): string
    {
        $value = str_replace(
            ['\\', '"', "\r", "\n"],
            ['\\\\', '\"', '\\r', '\\n'],
            $value
        );

        return '"' . $value . '"';
    }

    private function formatDotNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $graph
     * @param  array<string, array{width: float, height: float}>  $nodeSizes
     * @return array{
     *     engine: string,
     *     revision: int,
     *     width: float,
     *     height: float,
     *     nodes: array<string, array{x: float, y: float, width: float, height: float}>
     * }
     */
    private function extractLayout(array $graph, array $nodeSizes, string $engine = self::PRIMARY_ENGINE): array
    {
        [$minX, , $maxX, $maxY] = $this->parseBoundingBox((string) ($graph['bb'] ?? ''));
        $positions = [];

        foreach (($graph['objects'] ?? []) as $object) {
            if (! is_array($object)) {
                continue;
            }

            $id = (string) ($object['name'] ?? '');
            if (! isset($nodeSizes[$id])) {
                continue;
            }

            $pos = (string) ($object['pos'] ?? '');
            if ($pos === '') {
                throw new RuntimeException("GraphViz route-map layout did not return a position for node [{$id}].");
            }

            [$centerX, $centerY] = array_map('floatval', explode(',', $pos, 2));
            $size = $nodeSizes[$id];
            $positions[$id] = [
                'x' => round($centerX - $minX - ($size['width'] / 2), 3),
                'y' => round($maxY - $centerY - ($size['height'] / 2), 3),
                'width' => round($size['width'], 3),
                'height' => round($size['height'], 3),
            ];
        }

        $missing = array_values(array_diff(array_keys($nodeSizes), array_keys($positions)));
        if ($missing !== []) {
            throw new RuntimeException('GraphViz route-map layout omitted ' . count($missing) . ' nodes, including [' . $missing[0] . '].');
        }

        return [
            'engine' => 'graphviz-' . $engine,
            'revision' => self::REVISION,
            'width' => round($maxX - $minX, 3),
            'height' => round($maxY, 3),
            'nodes' => $positions,
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function parseBoundingBox(string $value): array
    {
        $parts = array_map('floatval', explode(',', $value));

        if (count($parts) !== 4) {
            throw new RuntimeException('GraphViz route-map layout returned an invalid bounding box.');
        }

        return [$parts[0], $parts[1], $parts[2], $parts[3]];
    }
}
