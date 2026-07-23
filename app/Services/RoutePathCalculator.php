<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use App\Models\VersionRoutePath;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Computes the shortest playable route from a game's start label to each of
 * its endings, persisted to version_route_paths for detail-page stats.
 *
 * Paths are derived from the *built* RouteGraph (the same node/edge set the
 * route-map view and the frontend pathfinder traverse), so a stored path
 * matches what a player sees as a navigable route — including expanded menus,
 * collapsed return helpers, and condition factoring. Raw route_edges alone
 * bypass all of that and describe routes the visual map does not show.
 */
class RoutePathCalculator
{
    public const MAX_CALCULATION_SECONDS = 30.0;

    public const MAX_ENDINGS = 1000;

    public const MAX_PATH_STEPS = 10_000;

    public const MAX_TOTAL_PATH_STEPS = 250_000;

    public const MAX_TOTAL_JSON_BYTES = 16 * 1024 * 1024;

    public function __construct(
        private readonly RouteGraphService $routeGraphService = new RouteGraphService,
    ) {}

    public function calculateAndStore(GameVersion $version): void
    {
        DB::transaction(fn () => $this->calculateAndStoreWithinTransaction($version));
    }

    private function calculateAndStoreWithinTransaction(GameVersion $version): void
    {
        $version->routePaths()->delete();
        $startedAt = hrtime(true);
        $deadline = $startedAt + (int) (self::MAX_CALCULATION_SECONDS * 1_000_000_000);

        $graph = $this->routeGraphService->buildGraph($version);

        $startNodeId = $this->findStartNodeId($graph['nodes'] ?? []);
        $allEndings = array_values(array_unique(array_map('strval', $graph['endings'] ?? [])));

        if ($startNodeId === null || $allEndings === []) {
            return;
        }

        if (count($allEndings) > self::MAX_ENDINGS) {
            throw new RuntimeException(sprintf(
                'Route path calculation found %d endings; the limit is %d.',
                count($allEndings),
                self::MAX_ENDINGS,
            ));
        }

        $nodeByLabel = $this->indexNodesByLabel($graph['nodes'] ?? []);
        $adjacency = $this->buildAdjacency($graph['edges'] ?? []);

        // Parent-pointer BFS over every edge (matches the frontend pathfinder
        // and RouteGraphPostProcessor::filterReachableFromStart, which both
        // traverse the edge set without filtering by edge_type).
        $parentEdge = $this->findShortestPathEdges($startNodeId, $allEndings, $adjacency, $deadline);

        $totalPathSteps = 0;
        $totalJsonBytes = 0;

        foreach ($allEndings as $endingLabel) {
            $this->ensureWithinDeadline($deadline);

            if (! isset($parentEdge[$endingLabel])) {
                continue;
            }

            [$pathLabels, $pathEdges] = $this->reconstructLabelPath($endingLabel, $parentEdge, $deadline);

            if ($pathLabels === []) {
                continue;
            }

            $pathSteps = count($pathLabels);
            if ($pathSteps > self::MAX_PATH_STEPS) {
                throw new RuntimeException(sprintf(
                    'Route path to "%s" has %d steps; the limit is %d.',
                    $endingLabel,
                    $pathSteps,
                    self::MAX_PATH_STEPS,
                ));
            }

            $totalPathSteps += $pathSteps;
            if ($totalPathSteps > self::MAX_TOTAL_PATH_STEPS) {
                throw new RuntimeException(sprintf(
                    'Route paths contain more than %d total steps.',
                    self::MAX_TOTAL_PATH_STEPS,
                ));
            }

            $wordCount = $this->sumWordCounts($pathLabels, $nodeByLabel);
            [$choiceCount, $choices] = $this->collectChoices($pathLabels, $pathEdges, $nodeByLabel);

            $totalJsonBytes += $this->encodedJsonBytes($pathLabels);
            if ($choices !== []) {
                $totalJsonBytes += $this->encodedJsonBytes($choices);
            }
            if ($totalJsonBytes > self::MAX_TOTAL_JSON_BYTES) {
                throw new RuntimeException(sprintf(
                    'Route path JSON exceeds the %d byte limit.',
                    self::MAX_TOTAL_JSON_BYTES,
                ));
            }

            $this->ensureWithinDeadline($deadline);

            VersionRoutePath::create([
                'game_version_id' => $version->id,
                'ending_label' => $endingLabel,
                'path_labels' => $pathLabels,
                'step_count' => $pathSteps,
                'word_count' => $wordCount,
                'choice_count' => $choiceCount,
                'choices' => $choices ?: null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function findStartNodeId(array $nodes): ?string
    {
        foreach ($nodes as $node) {
            if (! empty($node['is_start'])) {
                return (string) $node['id'];
            }
        }

        return null;
    }

    /**
     * Index graph nodes by id, skipping condition-scope nodes (display-only).
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function indexNodesByLabel(array $nodes): array
    {
        $indexed = [];
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '' || ! empty($node['is_condition_scope'])) {
                continue;
            }
            $indexed[$id] = $node;
        }

        return $indexed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildAdjacency(array $edges): array
    {
        $adjacency = [];
        foreach ($edges as $edge) {
            $source = (string) ($edge['source'] ?? '');
            if ($source === '') {
                continue;
            }
            $adjacency[$source][] = $edge;
        }

        return $adjacency;
    }

    /**
     * BFS from start, recording the inbound edge used to first reach each node.
     *
     * @param  array<string>  $endings
     * @param  array<string, array<int, array<string, mixed>>>  $adjacency
     * @return array<string, array<string, mixed>> node id => edge that reached it
     */
    private function findShortestPathEdges(string $start, array $endings, array $adjacency, int $deadline): array
    {
        $endingSet = array_flip($endings);
        $remaining = count($endingSet);

        $parentEdge = [$start => []]; // start has no inbound edge
        $queue = [$start];
        $queueIndex = 0;

        while (isset($queue[$queueIndex])) {
            if ($remaining === 0) {
                break;
            }
            $this->ensureWithinDeadline($deadline);

            $current = $queue[$queueIndex++];

            foreach ($adjacency[$current] ?? [] as $edge) {
                $target = (string) ($edge['target'] ?? '');
                if ($target === '' || isset($parentEdge[$target])) {
                    continue;
                }

                $parentEdge[$target] = $edge;
                if (isset($endingSet[$target])) {
                    $remaining--;
                }
                $queue[] = $target;
            }
        }

        return $parentEdge;
    }

    /**
     * Walk parent edges back from the ending, dropping condition-scope nodes
     * so path_labels stays real-label-only (condition nodes carry word_count 0
     * and exist only to render edge conditions on the map).
     *
     * @param  array<string, array<string, mixed>>  $parentEdge
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>}
     */
    private function reconstructLabelPath(string $ending, array $parentEdge, int $deadline): array
    {
        $reversedLabels = [];
        $reversedEdges = [];
        $node = $ending;

        while (isset($parentEdge[$node])) {
            $this->ensureWithinDeadline($deadline);
            $edge = $parentEdge[$node];

            // Only emit real labels into path_labels; condition-scope nodes are
            // structural wiring between a source and its factored condition.
            if (! str_starts_with($node, 'condition_scope:')) {
                if (count($reversedLabels) >= self::MAX_PATH_STEPS) {
                    throw new RuntimeException(sprintf(
                        'Route path to "%s" has more than %d steps.',
                        $ending,
                        self::MAX_PATH_STEPS,
                    ));
                }
                $reversedLabels[] = $node;
            }

            if ($edge === []) {
                break; // reached the start node
            }

            $reversedEdges[] = $edge;
            $node = (string) ($edge['source'] ?? '');
        }

        return [array_reverse($reversedLabels), array_reverse($reversedEdges)];
    }

    private function ensureWithinDeadline(int $deadline): void
    {
        if (hrtime(true) >= $deadline) {
            throw new RuntimeException('Route path calculation exceeded its 30 second deadline.');
        }
    }

    private function encodedJsonBytes(array $value): int
    {
        return strlen(json_encode($value, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, string>  $pathLabels
     * @param  array<string, array<string, mixed>>  $nodeByLabel
     */
    private function sumWordCounts(array $pathLabels, array $nodeByLabel): int
    {
        $total = 0;
        foreach ($pathLabels as $label) {
            $total += (int) ($nodeByLabel[$label]['word_count'] ?? 0);
        }

        return $total;
    }

    /**
     * A player "choice" is the act of selecting a menu option — represented in
     * the built graph by a `choice` edge entering a choice node. We count one
     * choice per such edge on the path and capture the choice text.
     *
     * @param  array<int, string>  $pathLabels
     * @param  array<int, array<string, mixed>>  $pathEdges
     * @param  array<string, array<string, mixed>>  $nodeByLabel
     * @return array{0: int, 1: array<int, array{from: string, to: string, text: string}>}
     */
    private function collectChoices(array $pathLabels, array $pathEdges, array $nodeByLabel): array
    {
        unset($pathLabels); // kept in signature for clarity; not needed here

        $choices = [];
        $seenChoiceNodes = [];

        foreach ($pathEdges as $edge) {
            $edgeType = (string) ($edge['edge_type'] ?? '');
            $target = (string) ($edge['target'] ?? '');

            // An edge into a choice node marks the point the player picked it.
            $isChoiceEdge = $edgeType === 'choice'
                || ($edgeType === 'menu_choice' && ! empty($edge['choice_text']));

            if (! $isChoiceEdge || ! isset($nodeByLabel[$target]) || isset($seenChoiceNodes[$target])) {
                continue;
            }

            $choiceNode = $nodeByLabel[$target];
            if (($choiceNode['node_type'] ?? null) !== 'choice' && $edgeType !== 'menu_choice') {
                continue;
            }

            $seenChoiceNodes[$target] = true;
            $from = (string) ($choiceNode['parent_label'] ?? $edge['source'] ?? '');
            $to = $edgeType === 'menu_choice'
                ? $target
                : ($this->nextRealLabelAfter($target, $pathEdges) ?? $from);
            $text = (string) ($choiceNode['choice_text'] ?? $edge['choice_text'] ?? $choiceNode['label'] ?? '');

            $choices[] = ['from' => $from, 'to' => $to, 'text' => $text];
        }

        return [count($choices), $choices];
    }

    /**
     * Find the destination the choice node leads toward: the target of the
     * edge leaving the choice node on the path. Falls back to the choice's
     * parent label when the choice has no outgoing edge on this path.
     *
     * @param  array<int, array<string, mixed>>  $pathEdges
     */
    private function nextRealLabelAfter(string $choiceNodeId, array $pathEdges): ?string
    {
        $current = $choiceNodeId;
        for ($hops = 0; $hops <= count($pathEdges); $hops++) {
            $next = null;
            foreach ($pathEdges as $edge) {
                if (($edge['source'] ?? null) === $current) {
                    $next = (string) ($edge['target'] ?? '');
                    break;
                }
            }

            if ($next === null || $next === '') {
                return null;
            }

            if (! str_starts_with($next, 'condition_scope:')) {
                return $next;
            }

            $current = $next;
        }

        return null;
    }
}
