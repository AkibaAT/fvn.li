<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use App\Models\VersionRoutePath;

class RoutePathCalculator
{
    public function calculateAndStore(GameVersion $version): void
    {
        $labels = $version->routeLabels()->get();
        $edges = $version->routeEdges()->get();
        $menuChoices = $version->routeMenuChoices()->get();
        $wordCounts = RouteGraphService::getWordCountsByLabel($version);

        // Build adjacency list
        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->from_label][] = $edge;
        }

        // Find start and ending labels
        $startLabel = null;
        $endings = [];
        foreach ($labels as $label) {
            if ($label->name === 'start' || $label->name === 'labels.start') {
                $startLabel = $label->name;
            }
            if ($label->is_ending) {
                $endings[] = $label->name;
            }
        }

        if ($startLabel === null || empty($endings)) {
            // Delete any existing paths and return
            $version->routePaths()->delete();

            return;
        }

        // BFS to find shortest path from start to each ending
        $paths = $this->findPaths($startLabel, $endings, $adjacency);

        // Build menu choice lookup: from_label -> [target_label -> choice text]
        $choiceLookup = [];
        foreach ($menuChoices as $mc) {
            if (! empty($mc->target_label)) {
                $choiceLookup[$mc->from_label][$mc->target_label] = $mc->text;
            }
        }

        // Delete existing paths for this version
        $version->routePaths()->delete();

        // Store each path
        foreach ($paths as $endingLabel => $pathLabels) {
            $pathWordCount = 0;
            $choiceCount = 0;
            $choices = [];

            foreach ($pathLabels as $i => $label) {
                $pathWordCount += $wordCounts[$label] ?? 0;

                // Check if the transition to the next label is a menu choice
                if (isset($pathLabels[$i + 1])) {
                    $nextLabel = $pathLabels[$i + 1];
                    if (isset($choiceLookup[$label][$nextLabel])) {
                        $choiceCount++;
                        $choices[] = [
                            'from' => $label,
                            'to' => $nextLabel,
                            'text' => $choiceLookup[$label][$nextLabel],
                        ];
                    }
                }
            }

            VersionRoutePath::create([
                'game_version_id' => $version->id,
                'ending_label' => $endingLabel,
                'path_labels' => $pathLabels,
                'step_count' => count($pathLabels),
                'word_count' => $pathWordCount,
                'choice_count' => $choiceCount,
                'choices' => $choices ?: null,
            ]);
        }
    }

    /**
     * BFS from start to find shortest paths to each ending.
     * Uses parent-pointer backtracking instead of array_merge per step
     * to avoid O(path_length) copies at each BFS iteration.
     *
     * @param  array<string, array>  $adjacency
     * @return array<string, array<string>> ending_label => [ordered label names]
     */
    private function findPaths(string $start, array $endings, array $adjacency): array
    {
        $endingSet = array_flip($endings);
        $paths = [];

        $queue = [$start];
        $parent = [$start => null]; // node => parent node

        while (! empty($queue)) {
            $current = array_shift($queue);

            if (isset($endingSet[$current]) && $current !== $start) {
                // Reconstruct path by backtracking through parent pointers
                $path = [];
                $node = $current;
                while ($node !== null) {
                    array_unshift($path, $node);
                    $node = $parent[$node];
                }
                $paths[$current] = $path;
                unset($endingSet[$current]);

                if (empty($endingSet)) {
                    break;
                }
            }

            foreach ($adjacency[$current] ?? [] as $edge) {
                $next = $edge->to_label;
                if (! isset($parent[$next])) {
                    $parent[$next] = $current;
                    $queue[] = $next;
                }
            }
        }

        return $paths;
    }
}
