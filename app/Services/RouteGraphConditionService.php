<?php

declare(strict_types=1);

namespace App\Services;

class RouteGraphConditionService
{
    public function normalizeDisplayConditions(array $edges): array
    {
        foreach ($edges as &$edge) {
            $edge['condition'] = $this->normalizeDisplayCondition($edge['condition'] ?? null);
        }
        unset($edge);

        return $edges;
    }

    public function normalizeDisplayCondition(?string $condition): ?string
    {
        $condition = trim((string) $condition);

        return $condition === '' || $condition === 'True' ? null : $condition;
    }

    public function normalizedConditionForComparison(?string $condition): string
    {
        $condition = trim((string) $condition);
        if ($condition === '' || $condition === 'True') {
            return '';
        }

        return preg_replace('/\s+/', '', $condition) ?? $condition;
    }

    public function negatedCondition(?string $condition): ?string
    {
        $condition = $this->normalizeDisplayCondition($condition);
        if ($condition === null) {
            return null;
        }

        return 'not ((' . $condition . '))';
    }

    public function conditionsCanOverlap(?string $choiceCondition, ?string $edgeCondition): bool
    {
        $choiceCondition = $this->normalizedConditionForComparison($choiceCondition);
        $edgeCondition = $this->normalizedConditionForComparison($edgeCondition);

        if ($choiceCondition === '' || $edgeCondition === '') {
            return true;
        }

        if ($choiceCondition === $edgeCondition) {
            return true;
        }

        if ($this->conditionsContradict($choiceCondition, $edgeCondition)) {
            return false;
        }

        return str_contains($edgeCondition, $choiceCondition) || str_contains($choiceCondition, $edgeCondition);
    }

    public function conditionsContradict(string $leftCondition, string $rightCondition): bool
    {
        $leftCondition = $this->stripOuterParentheses($leftCondition);
        $rightCondition = $this->stripOuterParentheses($rightCondition);

        return $this->conditionContainsDirectNegation($leftCondition, $rightCondition)
            || $this->conditionContainsDirectNegation($rightCondition, $leftCondition);
    }

    public function conditionImplies(?string $knownCondition, ?string $candidateCondition): bool
    {
        $knownCondition = $this->normalizedConditionForComparison($knownCondition);
        $candidateCondition = $this->normalizedConditionForComparison($candidateCondition);

        if ($candidateCondition === '') {
            return true;
        }

        if ($knownCondition === '') {
            return false;
        }

        if ($knownCondition === $candidateCondition) {
            return true;
        }

        if ($this->conditionsContradict($knownCondition, $candidateCondition)) {
            return false;
        }

        return str_contains($knownCondition, $candidateCondition);
    }

    public function deduplicatedContinuationCondition(?string $choiceCondition, ?string $edgeCondition): ?string
    {
        $normalizedEdgeCondition = $this->normalizeDisplayCondition($edgeCondition);

        if ($normalizedEdgeCondition === null) {
            return null;
        }

        if ($this->conditionImplies($choiceCondition, $normalizedEdgeCondition)) {
            return null;
        }

        return $normalizedEdgeCondition;
    }

    public function deduplicatedMenuTransitionCondition(
        ?string $menuCondition,
        ?string $choiceCondition,
        ?string $nextMenuCondition
    ): ?string {
        $normalizedNextMenuCondition = $this->normalizeDisplayCondition($nextMenuCondition);

        if ($normalizedNextMenuCondition === null) {
            return null;
        }

        $knownConditions = [
            $this->normalizedConditionForComparison($menuCondition),
            $this->normalizedConditionForComparison($choiceCondition),
        ];

        if (in_array($this->normalizedConditionForComparison($normalizedNextMenuCondition), $knownConditions, true)) {
            return null;
        }

        return $normalizedNextMenuCondition;
    }

    public function variableConditionAlreadyShown(?string $knownCondition, ?string $candidateCondition): bool
    {
        $knownCondition = $this->normalizedConditionForComparison($knownCondition);
        $candidateCondition = $this->normalizedConditionForComparison($candidateCondition);

        if ($candidateCondition === '') {
            return true;
        }

        if ($knownCondition === '') {
            return false;
        }

        if ($knownCondition === $candidateCondition) {
            return true;
        }

        if (str_contains($knownCondition, 'or')) {
            return false;
        }

        return str_contains($knownCondition, $candidateCondition);
    }

    /**
     * @param  array<string, mixed>  $edge
     * @return array<int, string>
     */
    public function edgeConditionStack(array $edge): array
    {
        $stack = $edge['condition_stack'] ?? [];
        if (is_string($stack)) {
            $decoded = json_decode($stack, true);
            $stack = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($stack)) {
            return [];
        }

        return collect($stack)
            ->map(fn ($condition) => $this->normalizeDisplayCondition(is_string($condition) ? $condition : null))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $stack
     */
    public function combinedConditionStack(array $stack): ?string
    {
        $conditions = collect($stack)
            ->map(fn (string $condition) => $this->normalizeDisplayCondition($condition))
            ->filter()
            ->values();

        if ($conditions->isEmpty()) {
            return null;
        }

        $combined = $conditions->shift();
        foreach ($conditions as $condition) {
            $combined = '(' . $combined . ') and (' . $condition . ')';
        }

        return $combined;
    }

    public function conditionScopeNodeId(string $source, ?string $prefixCondition): string
    {
        return 'condition_scope:' . md5($source . "\0" . $this->normalizedConditionForComparison($prefixCondition));
    }

    public function conditionScopeLabel(?string $condition): string
    {
        $condition = $this->normalizeDisplayCondition($condition);
        if ($condition === null) {
            return 'if True';
        }

        if (preg_match('/^not\s*\(.+\)$/', $condition) && ! str_contains($condition, ' and ')) {
            return 'else';
        }

        return 'if ' . $condition;
    }

    public function isUnconditional(?string $condition): bool
    {
        $condition = trim((string) $condition);

        return $condition === '' || $condition === 'True';
    }

    private function conditionContainsDirectNegation(string $condition, string $negatedTerm): bool
    {
        $negatedTerm = $this->stripOuterParentheses($negatedTerm);
        if ($negatedTerm === '') {
            return false;
        }

        $patterns = [
            'not(' . $negatedTerm . ')',
            'not((' . $negatedTerm . '))',
        ];

        foreach ($patterns as $pattern) {
            if ($condition === $pattern || str_contains($condition, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function stripOuterParentheses(string $condition): string
    {
        $condition = trim($condition);

        while (strlen($condition) >= 2 && $condition[0] === '(' && str_ends_with($condition, ')')) {
            $depth = 0;
            $wrapsWholeExpression = true;
            $length = strlen($condition);

            for ($i = 0; $i < $length; $i++) {
                if ($condition[$i] === '(') {
                    $depth++;
                } elseif ($condition[$i] === ')') {
                    $depth--;

                    if ($depth === 0 && $i < $length - 1) {
                        $wrapsWholeExpression = false;
                        break;
                    }
                }

                if ($depth < 0) {
                    $wrapsWholeExpression = false;
                    break;
                }
            }

            if (! $wrapsWholeExpression || $depth !== 0) {
                break;
            }

            $condition = substr($condition, 1, -1);
        }

        return $condition;
    }
}
