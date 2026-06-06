<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

class RouteGraphEdgePruner
{
    public function __construct(
        private readonly RouteGraphConditionService $conditions = new RouteGraphConditionService,
    ) {}

    public function removeUnreachableFallthroughEdges(Collection $edges, array $choiceLookup = []): Collection
    {
        $terminalLineBySource = [];

        foreach ($edges as $edge) {
            if (! in_array($edge->edge_type, ['jump', 'return'], true)) {
                continue;
            }

            if (! $this->conditions->isUnconditional($edge->condition)) {
                continue;
            }

            $line = (int) ($edge->line_number ?? 0);
            if ($line <= 0) {
                continue;
            }

            if ($this->isDuplicateMenuChoiceEdge($edge, $choiceLookup[$edge->from_label . ':' . $edge->to_label] ?? [])) {
                continue;
            }

            $source = (string) $edge->from_label;
            $terminalLineBySource[$source] = min($terminalLineBySource[$source] ?? $line, $line);
        }

        if (empty($terminalLineBySource)) {
            return $edges->values();
        }

        return $edges
            ->reject(function ($edge) use ($terminalLineBySource) {
                if ($edge->edge_type !== 'flow') {
                    return false;
                }

                if (! $this->conditions->isUnconditional($edge->condition)) {
                    return false;
                }

                $terminalLine = $terminalLineBySource[(string) $edge->from_label] ?? null;
                if ($terminalLine === null) {
                    return false;
                }

                $line = (int) ($edge->line_number ?? 0);

                return $line > $terminalLine;
            })
            ->values();
    }

    public function removeInputRetrySelfLoopEdges(Collection $edges): Collection
    {
        $hasNonSelfOutgoing = [];
        foreach ($edges as $edge) {
            if ((string) $edge->from_label === (string) $edge->to_label) {
                continue;
            }

            $hasNonSelfOutgoing[(string) $edge->from_label] = true;
        }

        return $edges
            ->reject(function ($edge) use ($hasNonSelfOutgoing) {
                if ($edge->edge_type !== 'jump') {
                    return false;
                }

                $source = (string) $edge->from_label;
                if ($source === '' || $source !== (string) $edge->to_label) {
                    return false;
                }

                if (! isset($hasNonSelfOutgoing[$source])) {
                    return false;
                }

                return $this->isEmptyInputCondition($edge->condition);
            })
            ->values();
    }

    public function findMatchingMenuChoice($edge, array $choices)
    {
        if (empty($choices)) {
            return null;
        }

        $edgeLine = (int) ($edge->line_number ?? 0);

        foreach ($choices as $choice) {
            if ((int) ($choice->line_number ?? 0) === $edgeLine) {
                return $choice;
            }
        }

        return $choices[0] ?? null;
    }

    public function isDuplicateMenuChoiceEdge($edge, array $choices): bool
    {
        if (empty($choices) || $edge->edge_type === 'menu_choice') {
            return false;
        }

        if (! in_array($edge->edge_type, ['jump', 'call', 'flow'], true)) {
            return false;
        }

        $edgeLine = (int) ($edge->line_number ?? 0);
        foreach ($choices as $choice) {
            $choiceLine = (int) ($choice->line_number ?? 0);
            $choiceEdgeType = $this->normalizedMenuChoiceTargetType($choice->edge_type ?: $edge->edge_type);
            $edgeType = $this->normalizedMenuChoiceTargetType($edge->edge_type);
            $edgeCondition = $this->conditions->normalizeDisplayCondition($edge->condition ?? null);

            if ($edgeCondition !== null && $this->conditions->normalizedConditionForComparison($edgeCondition) !== $this->conditions->normalizedConditionForComparison($this->choiceEffectiveCondition($choice))) {
                continue;
            }

            if ($choiceEdgeType === $edgeType && ($edgeLine <= 0 || $choiceLine <= 0 || $edgeLine >= $choiceLine)) {
                return true;
            }
        }

        return false;
    }

    private function choiceEffectiveCondition($choice): ?string
    {
        return $this->conditions->normalizeDisplayCondition($choice->condition ?? null);
    }

    private function normalizedMenuChoiceTargetType(?string $edgeType): string
    {
        return $edgeType === 'label' ? 'flow' : (string) $edgeType;
    }

    private function isEmptyInputCondition(?string $condition): bool
    {
        $condition = trim((string) $condition);
        if ($condition === '') {
            return false;
        }

        return (bool) preg_match('/\b[A-Za-z_][A-Za-z0-9_]*\s*==\s*([\'"])\1/', $condition);
    }
}
