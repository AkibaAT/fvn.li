<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

class RouteGraphVariableChangeFormatter
{
    public function __construct(
        private readonly RouteGraphConditionService $conditions = new RouteGraphConditionService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function format(Collection $varChanges, ?string $onlyContext = null, bool $excludeChoiceContexts = false): array
    {
        $filtered = $varChanges;

        if ($onlyContext !== null) {
            $filtered = $filtered->filter(fn ($vc) => $vc->context === $onlyContext);
        }

        if ($excludeChoiceContexts) {
            $filtered = $filtered->reject(fn ($vc) => str_starts_with((string) $vc->context, 'menu_choice:'));
        }

        return $filtered->map(function ($vc) {
            return [
                'variable' => $vc->variable_name,
                'operation' => $vc->operation,
                'value' => $this->formatValue($vc->value),
                'context' => $vc->context,
                'condition' => $this->conditions->normalizeDisplayCondition($vc->condition ?? null),
                'condition_stack' => $this->conditionStack($vc),
            ];
        })->values()->toArray();
    }

    public function displayCondition($variableChange, ?string $knownCondition): ?string
    {
        foreach (array_reverse($this->conditionStack($variableChange)) as $condition) {
            if (! $this->conditions->variableConditionAlreadyShown($knownCondition, $condition)) {
                return $condition;
            }
        }

        $condition = $this->conditions->normalizeDisplayCondition($variableChange->condition ?? null);
        if ($condition !== null && ! $this->conditions->variableConditionAlreadyShown($knownCondition, $condition)) {
            return $condition;
        }

        return null;
    }

    public function formatValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/^Num\(n=(.+)\)$/', $value, $m)) {
            return $m[1];
        }

        if (preg_match("/^Str\(s='(.+)'\)$/", $value, $m)) {
            return "'" . $m[1] . "'";
        }

        if (preg_match("/^Name\(id='(.+?)'.*\)$/", $value, $m)) {
            return $m[1];
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function conditionStack($variableChange): array
    {
        $stack = $variableChange->condition_stack ?? [];

        if (is_string($stack)) {
            $decoded = json_decode($stack, true);
            $stack = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($stack)) {
            return [];
        }

        return collect($stack)
            ->map(fn ($condition) => $this->conditions->normalizeDisplayCondition(is_string($condition) ? $condition : null))
            ->filter()
            ->values()
            ->all();
    }
}
