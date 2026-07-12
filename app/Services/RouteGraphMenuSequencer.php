<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

class RouteGraphMenuSequencer
{
    /**
     * @param  array<int, array<string, mixed>>  $menuGroups
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    public function menuSequenceLinks(array $menuGroups, Collection $continuationEdges): array
    {
        $previousByIndex = [];
        $nextByIndex = [];
        $lastBySequence = [];
        $lastRootIndex = null;

        foreach ($menuGroups as $index => $group) {
            $sequenceKey = (string) ($group['menu_branch'] ?? '');

            if (isset($lastBySequence[$sequenceKey])) {
                $previousIndex = $lastBySequence[$sequenceKey];

                if (! isset($nextByIndex[$previousIndex])) {
                    $previousByIndex[$index] = $previousIndex;
                    $nextByIndex[$previousIndex] = $index;
                } else {
                    while (isset($nextByIndex[$previousIndex])) {
                        $previousIndex = $nextByIndex[$previousIndex];
                    }

                    $tailGroup = $menuGroups[$previousIndex] ?? null;
                    $tailEndLine = (int) ($tailGroup['end_line'] ?? 0);
                    $groupStartLine = (int) (($group['menu_line'] ?? 0) ?: ($group['start_line'] ?? 0));

                    if ($previousIndex !== $index && $tailEndLine > 0 && $groupStartLine > 0 && $tailEndLine < $groupStartLine) {
                        $previousByIndex[$index] = $previousIndex;
                        $nextByIndex[$previousIndex] = $index;
                    }
                }
            }

            if (! isset($previousByIndex[$index]) && $this->isRootMenuGroup($group) && $lastRootIndex !== null) {
                $previousRootGroup = $menuGroups[$lastRootIndex] ?? null;
                if ($previousRootGroup !== null && $this->canSequenceRootMenuGroups($previousRootGroup, $group, $continuationEdges)) {
                    $previousByIndex[$index] = $lastRootIndex;
                    $nextByIndex[$lastRootIndex] = $index;
                }
            }

            $lastBySequence[$sequenceKey] = $index;
            if ($this->isRootMenuGroup($group)) {
                $lastRootIndex = $index;
            }
        }

        return [$previousByIndex, $nextByIndex];
    }

    /**
     * @return array{target: string, edge_type: string, edges: Collection<int, mixed>}|null
     */
    public function commonContinuationAfterGroup(array $group, Collection $continuationEdges): ?array
    {
        $groupEndLine = (int) ($group['end_line'] ?? 0);
        if ($groupEndLine <= 0) {
            return null;
        }

        $laterEdges = $continuationEdges
            ->filter(fn ($edge) => (int) ($edge->line_number ?? 0) > $groupEndLine)
            ->values();

        if ($laterEdges->isEmpty()) {
            return null;
        }

        $targets = $laterEdges
            ->pluck('to_label')
            ->filter()
            ->unique()
            ->values();

        if ($targets->count() !== 1) {
            return null;
        }

        return [
            'target' => (string) $targets->first(),
            'edge_type' => (string) ($laterEdges->first()->edge_type ?? 'flow'),
            'edges' => $laterEdges,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $menuGroups
     * @param  array<int, int>  $previousMenuGroupByIndex
     * @return array<int, int>
     */
    public function firstChildMenuGroupByChoiceLine(array $menuGroups, array $previousMenuGroupByIndex): array
    {
        $firstByChoiceLine = [];

        foreach ($menuGroups as $index => $group) {
            if (isset($previousMenuGroupByIndex[$index])) {
                continue;
            }

            $parentChoiceLine = (int) ($group['parent_choice_line'] ?? 0);
            if ($parentChoiceLine <= 0 || isset($firstByChoiceLine[$parentChoiceLine])) {
                continue;
            }

            $firstByChoiceLine[$parentChoiceLine] = $index;
        }

        return $firstByChoiceLine;
    }

    /**
     * @param  array<int, array<string, mixed>>  $menuGroups
     * @param  array<int, int>  $nextMenuGroupByIndex
     * @return array<int, int>
     */
    public function resumeMenuGroupByChildGroupIndex(array $menuGroups, array $nextMenuGroupByIndex): array
    {
        $parentGroupByChoiceLine = [];
        foreach ($menuGroups as $index => $group) {
            foreach ($group['choices'] as $choice) {
                $choiceLine = (int) ($choice->line_number ?? 0);
                if ($choiceLine > 0) {
                    $parentGroupByChoiceLine[$choiceLine] = $index;
                }
            }
        }

        $resumeByChildGroupIndex = [];
        foreach ($menuGroups as $index => $group) {
            if (isset($nextMenuGroupByIndex[$index])) {
                continue;
            }

            $parentChoiceLine = (int) ($group['parent_choice_line'] ?? 0);
            $parentGroupIndex = $parentChoiceLine > 0 ? ($parentGroupByChoiceLine[$parentChoiceLine] ?? null) : null;
            if ($parentGroupIndex === null) {
                continue;
            }

            $resumeGroupIndex = $nextMenuGroupByIndex[$parentGroupIndex] ?? null;
            if ($resumeGroupIndex !== null) {
                $resumeByChildGroupIndex[$index] = $resumeGroupIndex;
            }
        }

        return $resumeByChildGroupIndex;
    }

    private function isRootMenuGroup(array $group): bool
    {
        return (int) ($group['parent_choice_line'] ?? 0) <= 0;
    }

    private function canSequenceRootMenuGroups(array $previousGroup, array $nextGroup, Collection $continuationEdges): bool
    {
        $previousEndLine = (int) ($previousGroup['end_line'] ?? 0);
        $nextStartLine = (int) (($nextGroup['menu_line'] ?? 0) ?: ($nextGroup['start_line'] ?? 0));

        if ($previousEndLine <= 0 || $nextStartLine <= 0 || $previousEndLine >= $nextStartLine) {
            return false;
        }

        return ! $continuationEdges->contains(function ($edge) use ($previousEndLine, $nextStartLine) {
            $line = (int) ($edge->line_number ?? 0);

            return $line > $previousEndLine && $line < $nextStartLine;
        });
    }
}
