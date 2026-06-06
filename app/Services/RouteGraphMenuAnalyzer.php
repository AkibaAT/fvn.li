<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

class RouteGraphMenuAnalyzer
{
    public function __construct(
        private readonly RouteGraphConditionService $conditions = new RouteGraphConditionService,
        private readonly RouteGraphChoiceAccessor $choiceAccessor = new RouteGraphChoiceAccessor,
    ) {}

    public function targetlessChoices(Collection $choices): Collection
    {
        return $choices
            ->filter(fn ($choice) => empty($choice->target_label))
            ->sortBy(fn ($choice) => (int) ($choice->line_number ?? 0))
            ->values();
    }

    /**
     * @return array<int, array{
     *     choices: Collection<int, mixed>,
     *     prompt: string|null,
     *     prompt_translations: array<string, string>|null,
     *     menu_line: int,
     *     menu_branch: string|null,
     *     condition_stack: array<int, string>,
     *     parent_menu_line: int,
     *     parent_choice_line: int,
     *     start_line: int,
     *     end_line: int
     * }>
     */
    public function menuChoiceGroups(Collection $choices): array
    {
        $groups = [];
        $groupOrder = [];

        foreach ($choices->sortBy(fn ($choice) => (int) ($choice->line_number ?? 0))->values() as $choice) {
            $prompt = $this->choiceAccessor->prompt($choice);
            $displayPrompt = $prompt !== '' ? $prompt : null;
            $line = (int) ($choice->line_number ?? 0);
            $menuLine = $this->choiceAccessor->menuLine($choice);
            $menuBranch = $this->choiceAccessor->menuBranch($choice);
            $parentMenuLine = $this->choiceAccessor->parentMenuLine($choice);
            $parentChoiceLine = $this->choiceAccessor->parentChoiceLine($choice);
            $groupKey = implode('|', [
                $menuLine > 0 ? 'menu:' . $menuLine : 'choice:' . $line,
                'branch:' . ($menuBranch ?? ''),
                'parent_menu:' . $parentMenuLine,
                'parent_choice:' . $parentChoiceLine,
            ]);

            if (! isset($groups[$groupKey])) {
                $groupOrder[] = $groupKey;
                $groups[$groupKey] = [
                    'choices' => collect(),
                    'prompt' => $displayPrompt,
                    'prompt_translations' => $choice->prompt_translations,
                    'menu_line' => $menuLine,
                    'menu_branch' => $menuBranch,
                    'condition_stack' => [],
                    'parent_menu_line' => $parentMenuLine,
                    'parent_choice_line' => $parentChoiceLine,
                    'start_line' => $line,
                    'end_line' => $line,
                ];
            } elseif ($groups[$groupKey]['prompt'] === null && $displayPrompt !== null) {
                $groups[$groupKey]['prompt'] = $displayPrompt;
                $groups[$groupKey]['prompt_translations'] = $choice->prompt_translations;
            }

            $groups[$groupKey]['choices']->push($choice);
            if ($line > 0) {
                $groups[$groupKey]['start_line'] = $groups[$groupKey]['start_line'] > 0 ? min($groups[$groupKey]['start_line'], $line) : $line;
                $groups[$groupKey]['end_line'] = max($groups[$groupKey]['end_line'], $line);
            }
        }

        return collect($groupOrder)
            ->map(function (string $key) use ($groups) {
                $group = $groups[$key];
                $group['condition_stack'] = $this->commonMenuConditionStack($group['choices']);

                return $group;
            })
            ->sortBy(fn (array $group) => sprintf(
                '%010d:%010d',
                (int) ($group['menu_line'] ?: $group['start_line']),
                (int) $group['start_line'],
            ))
            ->values()
            ->all();
    }

    public function menuGroupNodeId(string $labelName, int $startLine, int $index): string
    {
        return $labelName . ':menu_' . ($startLine > 0 ? $startLine : $index);
    }

    public function choiceEnclosingCondition($choice): ?string
    {
        return $this->conditions->normalizeDisplayCondition($choice->enclosing_condition ?? null);
    }

    public function choiceCondition($choice): ?string
    {
        return $this->conditions->normalizeDisplayCondition($choice->choice_condition ?? ($choice->condition ?? null));
    }

    public function choiceEffectiveCondition($choice): ?string
    {
        return $this->conditions->normalizeDisplayCondition($choice->condition ?? null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    public function meaningfulMenuGroups(
        string $labelName,
        array $groups,
        array $varChangesByContext,
        $label,
        Collection $nodeChoices,
        Collection $menuChoiceLines,
        Collection $continuationEdges,
        Collection $targetlessChoices
    ): array {
        if ($groups === []) {
            return [];
        }

        $meaningfulByIndex = [];
        foreach ($groups as $index => $group) {
            if ($this->isMeaningfulMenuGroup(
                $labelName,
                $group['choices'],
                $varChangesByContext,
                $label,
                $nodeChoices,
                $menuChoiceLines,
                $continuationEdges,
                $targetlessChoices
            )) {
                $meaningfulByIndex[$index] = true;
            }
        }

        $choiceLineToGroupIndex = [];
        foreach ($groups as $index => $group) {
            foreach ($group['choices'] as $choice) {
                $choiceLine = (int) ($choice->line_number ?? 0);
                if ($choiceLine > 0) {
                    $choiceLineToGroupIndex[$choiceLine] = $index;
                }
            }
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($groups as $index => $group) {
                if (! isset($meaningfulByIndex[$index])) {
                    continue;
                }

                $parentChoiceLine = (int) ($group['parent_choice_line'] ?? 0);
                $parentIndex = $parentChoiceLine > 0 ? ($choiceLineToGroupIndex[$parentChoiceLine] ?? null) : null;
                if ($parentIndex !== null && ! isset($meaningfulByIndex[$parentIndex])) {
                    $meaningfulByIndex[$parentIndex] = true;
                    $changed = true;
                }
            }
        }

        return collect($groups)
            ->filter(fn (array $group, int $index) => isset($meaningfulByIndex[$index]))
            ->values()
            ->all();
    }

    public function isMeaningfulMenuGroup(
        string $labelName,
        Collection $choices,
        array $varChangesByContext,
        $label,
        Collection $nodeChoices,
        Collection $menuChoiceLines,
        Collection $continuationEdges,
        Collection $targetlessChoices
    ): bool {
        if ((bool) $label->is_ending) {
            return true;
        }

        $continuationSignatures = [];

        foreach ($choices as $choice) {
            if (! empty($choice->target_label)) {
                return true;
            }

            $contextKey = $labelName . '|menu_choice:' . ($choice->text ?? '');
            $choiceVarChanges = $this->getVariableChangesForChoice(
                $choice,
                collect($varChangesByContext[$contextKey] ?? []),
                $nodeChoices
            );

            if ($choiceVarChanges->isNotEmpty()) {
                return true;
            }

            $choiceContinuationEdges = $this->getContinuationEdgesForChoice($choice, $continuationEdges, $targetlessChoices);
            if ($choiceContinuationEdges->isNotEmpty()) {
                $continuationSignatures[] = $choiceContinuationEdges
                    ->map(fn ($edge) => implode('|', [
                        (string) ($edge->to_label ?? ''),
                        (string) ($edge->edge_type ?? ''),
                        $this->conditions->normalizedConditionForComparison($edge->condition ?? null),
                    ]))
                    ->sort()
                    ->values()
                    ->join(';');
            }
        }

        if ($choices->count() === 1 && $continuationSignatures !== []) {
            return true;
        }

        return count(array_unique($continuationSignatures)) > 1;
    }

    public function commonMenuCondition(Collection $choices): ?string
    {
        $displayCondition = null;
        $normalizedCondition = null;

        foreach ($choices as $choice) {
            $choiceCondition = $this->choiceEnclosingCondition($choice);

            if ($choiceCondition === null) {
                return null;
            }

            $normalizedChoiceCondition = $this->conditions->normalizedConditionForComparison($choiceCondition);
            if ($normalizedCondition === null) {
                $displayCondition = $choiceCondition;
                $normalizedCondition = $normalizedChoiceCondition;

                continue;
            }

            if ($normalizedChoiceCondition !== $normalizedCondition) {
                return null;
            }
        }

        return $displayCondition;
    }

    /**
     * @return array<int, string>
     */
    public function commonMenuConditionStack(Collection $choices): array
    {
        $displayStack = null;
        $normalizedStack = null;

        foreach ($choices as $choice) {
            $choiceStack = $this->choiceMenuConditionStack($choice);
            if ($choiceStack === []) {
                return [];
            }

            $normalizedChoiceStack = array_map(
                fn (string $condition) => $this->conditions->normalizedConditionForComparison($condition),
                $choiceStack
            );

            if ($normalizedStack === null) {
                $displayStack = $choiceStack;
                $normalizedStack = $normalizedChoiceStack;

                continue;
            }

            if ($normalizedChoiceStack !== $normalizedStack) {
                return [];
            }
        }

        return $displayStack ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function choiceMenuConditionStack($choice): array
    {
        $stack = $choice->menu_condition_stack ?? [];

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

    public function menuEmptyCondition(Collection $choices): ?string
    {
        $conditions = [];

        foreach ($choices as $choice) {
            $choiceCondition = $this->choiceCondition($choice);

            if ($choiceCondition === null) {
                return null;
            }

            $conditions[$this->conditions->normalizedConditionForComparison($choiceCondition)] = $choiceCondition;
        }

        if ($conditions === []) {
            return null;
        }

        $conditionParts = array_map(fn (string $condition) => '(' . $condition . ')', array_values($conditions));

        return 'not (' . implode(' or ', $conditionParts) . ')';
    }

    public function getContinuationEdgesForChoice($choice, Collection $continuationEdges, Collection $targetlessChoices, ?int $stopBeforeLine = null): Collection
    {
        $choiceLine = (int) ($choice->line_number ?? 0);

        if ($choiceLine <= 0) {
            return $continuationEdges->count() === 1 ? $continuationEdges : collect();
        }

        [, $groupEndLine, $nextMenuLine] = $this->targetlessMenuGroupBounds($choice, $targetlessChoices);
        if ($stopBeforeLine !== null && $stopBeforeLine <= 0) {
            $stopBeforeLine = null;
        }

        if ($stopBeforeLine !== null) {
            return $this->compatibleContinuationEdges(
                $choice,
                $continuationEdges
                    ->filter(function ($edge) use ($groupEndLine, $stopBeforeLine) {
                        $edgeLine = (int) ($edge->line_number ?? 0);

                        return $edgeLine > $groupEndLine && $edgeLine < $stopBeforeLine;
                    })
                    ->values()
            );
        }

        $segmentEdges = $continuationEdges
            ->filter(function ($edge) use ($groupEndLine, $nextMenuLine) {
                $edgeLine = (int) ($edge->line_number ?? 0);

                return $edgeLine > $groupEndLine && ($nextMenuLine === null || $edgeLine < $nextMenuLine);
            })
            ->values();

        if ($segmentEdges->isNotEmpty()) {
            return $this->compatibleContinuationEdges($choice, $segmentEdges);
        }

        if ($this->choiceAccessor->prompt($choice) !== '' || $nextMenuLine === null) {
            $laterEdges = $continuationEdges
                ->filter(fn ($edge) => (int) ($edge->line_number ?? 0) > $groupEndLine)
                ->values();
            $compatibleLaterEdges = $this->compatibleContinuationEdges($choice, $laterEdges);

            if ($compatibleLaterEdges->isNotEmpty()) {
                return $this->firstLineEdgeGroup($compatibleLaterEdges);
            }

            if ($nextMenuLine === null && $continuationEdges->count() === 1) {
                return $continuationEdges;
            }
        }

        return collect();
    }

    /**
     * @return array{0: int, 1: int, 2: int|null}
     */
    public function targetlessMenuGroupBounds($choice, Collection $targetlessChoices): array
    {
        $choiceLine = (int) ($choice->line_number ?? 0);
        $choiceMenuLine = $this->choiceAccessor->menuLine($choice);
        $choicePrompt = $this->choiceAccessor->prompt($choice);
        $choices = $targetlessChoices->values();
        $choiceIndex = $choices->search(fn ($candidate) => (int) ($candidate->line_number ?? 0) === $choiceLine && (string) ($candidate->text ?? '') === (string) ($choice->text ?? '') && $this->choiceAccessor->menuLine($candidate) === $choiceMenuLine);

        if ($choiceIndex === false) {
            $choiceIndex = $choices->search(fn ($candidate) => (int) ($candidate->line_number ?? 0) === $choiceLine && $this->choiceAccessor->menuLine($candidate) === $choiceMenuLine);
        }

        if ($choiceIndex === false) {
            $nextLine = $choices
                ->pluck('line_number')
                ->filter(fn ($line) => (int) $line > $choiceLine)
                ->map(fn ($line) => (int) $line)
                ->sort()
                ->first();

            return [$choiceLine, $choiceLine, $nextLine ?: null];
        }

        $startIndex = (int) $choiceIndex;
        $endIndex = (int) $choiceIndex;

        if ($choiceMenuLine > 0) {
            while ($startIndex > 0 && $this->choiceAccessor->menuLine($choices[$startIndex - 1]) === $choiceMenuLine) {
                $startIndex--;
            }

            while ($endIndex < $choices->count() - 1 && $this->choiceAccessor->menuLine($choices[$endIndex + 1]) === $choiceMenuLine) {
                $endIndex++;
            }
        } elseif ($choicePrompt !== '') {
            while ($startIndex > 0 && $this->choiceAccessor->prompt($choices[$startIndex - 1]) === $choicePrompt) {
                $startIndex--;
            }

            while ($endIndex < $choices->count() - 1 && $this->choiceAccessor->prompt($choices[$endIndex + 1]) === $choicePrompt) {
                $endIndex++;
            }
        } else {
            while ($startIndex > 0 && (int) ($choices[$startIndex - 1]->line_number ?? 0) === $choiceLine) {
                $startIndex--;
            }

            while ($endIndex < $choices->count() - 1 && (int) ($choices[$endIndex + 1]->line_number ?? 0) === $choiceLine) {
                $endIndex++;
            }
        }

        $groupStartLine = (int) ($choices[$startIndex]->line_number ?? $choiceLine);
        $groupEndLine = (int) ($choices[$endIndex]->line_number ?? $choiceLine);
        $nextChoice = $choices[$endIndex + 1] ?? null;
        $nextMenuLine = $nextChoice ? (int) ($nextChoice->line_number ?? 0) : null;

        return [$groupStartLine, $groupEndLine, $nextMenuLine && $nextMenuLine > 0 ? $nextMenuLine : null];
    }

    public function compatibleContinuationEdges($choice, Collection $edges): Collection
    {
        $choiceCondition = $this->conditions->normalizedConditionForComparison($this->choiceEffectiveCondition($choice));

        return $edges
            ->filter(function ($edge) use ($choiceCondition) {
                $edgeCondition = $this->conditions->normalizedConditionForComparison($edge->condition ?? null);

                if ($choiceCondition === '') {
                    return $edgeCondition === '';
                }

                return $edgeCondition === '' || $this->conditions->conditionsCanOverlap($choiceCondition, $edgeCondition);
            })
            ->values();
    }

    public function firstLineEdgeGroup(Collection $edges): Collection
    {
        $firstLine = $edges
            ->pluck('line_number')
            ->filter(fn ($line) => $line !== null && (int) $line > 0)
            ->map(fn ($line) => (int) $line)
            ->min();

        if (! $firstLine) {
            return $edges->values();
        }

        return $edges
            ->filter(fn ($edge) => (int) ($edge->line_number ?? 0) === $firstLine)
            ->values();
    }

    public function isOuterConditionJoinEdge($choice, $edge): bool
    {
        $stack = $this->choiceMenuConditionStack($choice);
        if (count($stack) < 2) {
            return false;
        }

        $edgeCondition = $this->conditions->normalizedConditionForComparison($edge->condition ?? null);
        if ($edgeCondition === '') {
            return false;
        }

        for ($i = 1; $i < count($stack); $i++) {
            $prefixCondition = $this->conditions->combinedConditionStack(array_slice($stack, 0, $i));
            if ($edgeCondition === $this->conditions->normalizedConditionForComparison($prefixCondition)) {
                return true;
            }
        }

        return false;
    }

    public function getVariableChangesForChoice($choice, Collection $choiceVarChanges, Collection $allChoices): Collection
    {
        $choiceLine = (int) ($choice->line_number ?? 0);
        if ($choiceLine <= 0) {
            return $choiceVarChanges->values();
        }

        $nextMenuLine = $this->nextNonDescendantChoiceLine($choice, $allChoices);
        $lineScopedChanges = $choiceVarChanges
            ->filter(function ($change) use ($choiceLine, $nextMenuLine) {
                $changeLine = (int) ($change->line_number ?? 0);

                return $changeLine >= $choiceLine && ($nextMenuLine === null || $changeLine < $nextMenuLine);
            })
            ->values();

        return $lineScopedChanges;
    }

    public function nextNonDescendantChoiceLine($choice, Collection $allChoices): ?int
    {
        $choiceLine = (int) ($choice->line_number ?? 0);
        if ($choiceLine <= 0) {
            return null;
        }

        return $allChoices
            ->filter(function ($candidate) use ($choice, $choiceLine) {
                $candidateLine = (int) ($candidate->line_number ?? 0);

                return $candidateLine > $choiceLine
                    && ! $this->choiceIsDescendantOfChoice($candidate, $choice);
            })
            ->pluck('line_number')
            ->map(fn ($line) => (int) $line)
            ->sort()
            ->first();
    }

    public function choiceIsDescendantOfChoice($candidate, $choice): bool
    {
        $choiceLine = (int) ($choice->line_number ?? 0);
        if ($choiceLine <= 0) {
            return false;
        }

        if ((int) ($candidate->parent_choice_line ?? 0) === $choiceLine) {
            return true;
        }

        $branch = (string) ($candidate->menu_branch ?? '');
        if ($branch === '') {
            return false;
        }

        return (bool) preg_match('/(?:^|\/)menu:\d+:choice:' . preg_quote((string) $choiceLine, '/') . '(?:\/|$)/', $branch);
    }
}
