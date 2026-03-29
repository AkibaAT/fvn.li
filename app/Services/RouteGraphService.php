<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RouteGraphService
{
    public function buildGraph(GameVersion $version): array
    {
        $labels = $version->routeLabels()->get();
        $edges = $version->routeEdges()->get();
        $menuChoices = $version->routeMenuChoices()->get();
        $variables = $version->routeVariables()->get();
        $variableChanges = $version->routeVariableChanges()->get();

        $wordCounts = self::getWordCountsByLabel($version);

        $edgeMapByFrom = $edges->groupBy('from_label');
        $varChangesByLabel = $variableChanges->groupBy('label');
        $choicesByLabel = $menuChoices->groupBy('from_label');

        $nodes = [];
        $processedEdges = [];
        $expandedLabels = [];

        foreach ($labels as $label) {
            $name = $label->name;
            $outgoingEdges = $edgeMapByFrom->get($name, collect());
            $isStart = $name === 'labels.start' || $name === 'start';
            $varChanges = $varChangesByLabel->get($name, collect());
            $nodeChoices = $choicesByLabel->get($name, collect());

            // Filter to meaningful choices: ones that change variables or branch to a target
            $meaningfulChoices = $nodeChoices->filter(function ($mc) use ($varChanges) {
                if (! empty($mc->target_label)) {
                    return true;
                }
                $choiceContext = 'menu_choice:' . ($mc->text ?? '');

                return $varChanges->contains(fn ($vc) => $vc->context === $choiceContext);
            });

            $maxExpandedChoices = 10;

            if ($meaningfulChoices->isNotEmpty() && $meaningfulChoices->count() <= $maxExpandedChoices) {
                // This label has a manageable number of choices — expand into sub-graph
                $expandedLabels[$name] = true;

                // Get the prompt (question) for this menu — same for all choices in the group
                $firstChoice = $meaningfulChoices->first();
                $menuPrompt = $firstChoice?->prompt;
                $menuPromptTranslations = $firstChoice?->prompt_translations;

                // Create the main label node (content before the choice)
                $nodes[] = [
                    'id' => $name,
                    'label' => $name,
                    'node_type' => 'label',
                    'is_ending' => false, // not an ending if it has choices
                    'is_start' => $isStart,
                    'has_menu_choice' => true,
                    'menu_prompt' => $menuPrompt,
                    'menu_prompt_translations' => $menuPromptTranslations,
                    'file_path' => $label->file_path,
                    'line_number' => $label->line_number,
                    'outgoing_count' => $meaningfulChoices->count(),
                    'word_count' => $wordCounts[$name] ?? 0,
                    'choices' => [],
                    'variable_changes' => $this->formatVarChanges($varChanges, null),
                ];

                // Find non-choice outgoing edges (conditional jumps after the menu)
                $continuationEdges = $outgoingEdges->filter(fn ($e) => $e->edge_type !== 'menu_choice');

                foreach ($meaningfulChoices->values() as $i => $mc) {
                    $choiceId = $name . ':choice_' . $i;
                    $choiceText = $mc->text ?? 'Choice ' . ($i + 1);

                    // Get variable changes for this specific choice
                    $choiceContext = 'menu_choice:' . ($mc->text ?? '');
                    $choiceVarChanges = $varChanges->filter(fn ($vc) => $vc->context === $choiceContext);

                    // Build label with variable effects
                    $varSummary = $choiceVarChanges->map(function ($vc) {
                        $val = $vc->value;
                        // Simplify AST dump values for display
                        if (preg_match('/^Num\(n=(.+)\)$/', $val, $m)) {
                            $val = $m[1];
                        } elseif (preg_match("/^Str\(s='(.+)'\)$/", $val, $m)) {
                            $val = "'" . $m[1] . "'";
                        } elseif (preg_match("/^Name\(id='(.+?)'.*\)$/", $val, $m)) {
                            $val = $m[1];
                        }

                        return $vc->variable_name . ' ' . $vc->operation . ' ' . $val;
                    })->join(', ');

                    // Hard choice: has its own target. Soft choice: uses continuation edges.
                    $hasHardTarget = ! empty($mc->target_label);
                    $outCount = $hasHardTarget ? 1 : $continuationEdges->count();

                    $nodes[] = [
                        'id' => $choiceId,
                        'label' => $choiceText,
                        'choice_text' => $choiceText,
                        'translations' => $mc->translations,
                        'var_summary' => $varSummary ?: null,
                        'node_type' => 'choice',
                        'is_ending' => false,
                        'is_start' => false,
                        'has_menu_choice' => false,
                        'file_path' => $mc->file_path,
                        'line_number' => $mc->line_number ?? 0,
                        'outgoing_count' => $outCount,
                        'word_count' => 0,
                        'choices' => [],
                        'variable_changes' => $this->formatVarChanges($choiceVarChanges, $choiceContext),
                        'parent_label' => $name,
                        'condition' => $mc->condition,
                    ];

                    // Edge from label to choice
                    $processedEdges[] = [
                        'id' => $name . ':' . $choiceId . ':choice',
                        'source' => $name,
                        'target' => $choiceId,
                        'edge_type' => 'choice',
                        'condition' => $mc->condition,
                    ];

                    if ($hasHardTarget) {
                        // Hard choice: direct edge to its target
                        $processedEdges[] = [
                            'id' => $choiceId . ':' . $mc->target_label . ':choice_target',
                            'source' => $choiceId,
                            'target' => $mc->target_label,
                            'edge_type' => 'choice_target',
                            'condition' => null,
                        ];
                    } else {
                        // Soft choice: connect to all continuation edges (conditional jumps after menu)
                        foreach ($continuationEdges as $ce) {
                            $processedEdges[] = [
                                'id' => $choiceId . ':' . $ce->to_label . ':' . $ce->edge_type,
                                'source' => $choiceId,
                                'target' => $ce->to_label,
                                'edge_type' => $ce->edge_type,
                                'condition' => $ce->condition,
                            ];
                        }
                    }
                }
            } elseif ($meaningfulChoices->count() > $maxExpandedChoices) {
                // Too many choices to expand — render as a hub node
                // Don't mark as expanded so original edges are kept
                $nodes[] = [
                    'id' => $name,
                    'label' => $name,
                    'node_type' => 'hub',
                    'is_ending' => (bool) $label->is_ending,
                    'is_start' => $isStart,
                    'has_menu_choice' => true,
                    'hub_choice_count' => $meaningfulChoices->count(),
                    'file_path' => $label->file_path,
                    'line_number' => $label->line_number,
                    'outgoing_count' => $outgoingEdges->count(),
                    'word_count' => $wordCounts[$name] ?? 0,
                    'choices' => $meaningfulChoices->map(fn ($mc) => [
                        'text' => $mc->text,
                        'translations' => $mc->translations,
                        'target_label' => $mc->target_label,
                        'condition' => $mc->condition,
                    ])->values()->toArray(),
                    'variable_changes' => $this->formatVarChanges($varChanges, null),
                ];
            } else {
                // Regular node without choices
                $nodes[] = [
                    'id' => $name,
                    'label' => $name,
                    'node_type' => 'label',
                    'is_ending' => (bool) $label->is_ending,
                    'is_start' => $isStart,
                    'has_menu_choice' => false,
                    'file_path' => $label->file_path,
                    'line_number' => $label->line_number,
                    'outgoing_count' => $outgoingEdges->count(),
                    'word_count' => $wordCounts[$name] ?? 0,
                    'choices' => [],
                    'variable_changes' => $this->formatVarChanges($varChanges, null),
                ];
            }
        }

        // Process edges — skip edges from expanded labels (they're replaced by choice sub-graphs)
        foreach ($edges as $edge) {
            if (isset($expandedLabels[$edge->from_label])) {
                continue;
            }

            $edgeData = [
                'id' => $edge->from_label . ':' . $edge->to_label . ':' . $edge->edge_type,
                'source' => $edge->from_label,
                'target' => $edge->to_label,
                'edge_type' => $edge->edge_type,
                'condition' => $edge->condition,
                'file_path' => $edge->file_path,
                'line_number' => $edge->line_number,
            ];

            if ($edge->edge_type === 'menu_choice') {
                $matchingChoice = $menuChoices->first(function ($mc) use ($edge) {
                    return $mc->from_label === $edge->from_label
                        && $mc->target_label === $edge->to_label;
                });
                if ($matchingChoice) {
                    $edgeData['choice_text'] = $matchingChoice->text;
                    $edgeData['condition'] = $matchingChoice->condition;
                }
            }

            $processedEdges[] = $edgeData;
        }

        // Post-process: infer else conditions and deduplicate
        // When a label has conditional edges AND unconditional flow edges,
        // the flow edges are implicitly the "else" case
        $processedEdges = $this->inferElseConditions($processedEdges);

        $variableData = $variables->map(function ($v) use ($variableChanges) {
            return [
                'name' => $v->name,
                'default_value' => $v->default_value,
                'type' => $v->type,
                'change_count' => $variableChanges->where('variable_name', $v->name)->count(),
            ];
        })->values()->toArray();

        $endings = $labels->where('is_ending', true)->pluck('name')->values()->toArray();

        $simplified = $this->buildSimplifiedGraph($labels, $edges, $wordCounts);

        return [
            'nodes' => $nodes,
            'edges' => $processedEdges,
            'variables' => $variableData,
            'endings' => $endings,
            'total_nodes' => $labels->count(),
            'total_edges' => $edges->count(),
            'simplified' => $simplified,
            'has_graph_data' => true,
        ];
    }

    /**
     * When a label has conditional edges alongside unconditional flow edges to
     * different targets, the flow edges are the implicit "else" case.
     * Also removes duplicate edges to the same target when a conditional version exists.
     */
    private function inferElseConditions(array $edges): array
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

            // Remove unconditional flow edges that duplicate a real conditional edge to the same target
            $conditionalTargets = [];
            foreach ($indices as $i) {
                $cond = $edges[$i]['condition'] ?? null;
                if (! empty($cond) && $cond !== 'True') {
                    $conditionalTargets[$edges[$i]['target']] = true;
                }
            }
            foreach ($unconditionalFlowIndices as $j => $i) {
                if (isset($conditionalTargets[$edges[$i]['target']])) {
                    unset($edges[$i]);
                    unset($unconditionalFlowIndices[$j]);
                }
            }

            // For remaining unconditional flow edges, infer the else condition
            if (count($conditions) === 1 && ! empty($unconditionalFlowIndices)) {
                foreach ($unconditionalFlowIndices as $i) {
                    if (isset($edges[$i])) {
                        $edges[$i]['condition'] = 'not (' . $conditions[0] . ')';
                    }
                }
            }
        }

        return array_values($edges);
    }

    private function formatVarChanges(Collection $varChanges, ?string $filterContext): array
    {
        $filtered = $filterContext !== null
            ? $varChanges->filter(fn ($vc) => $vc->context !== $filterContext)
            : $varChanges;

        return $filtered->map(function ($vc) {
            return [
                'variable' => $vc->variable_name,
                'operation' => $vc->operation,
                'value' => $vc->value,
                'context' => $vc->context,
            ];
        })->values()->toArray();
    }

    /**
     * Compute word counts per label from dialogue lines.
     * Uses the game's source language, falling back to English.
     *
     * @return array<string, int>  label name => word count
     */
    public static function getWordCountsByLabel(GameVersion $version): array
    {
        $game = $version->game ?? $version->game()->first();
        $isoCode = $game?->source_language_id ?? 'eng';

        $rows = DB::table('version_dialogue_lines')
            ->join('unique_dialogue_texts', 'version_dialogue_lines.text_id', '=', 'unique_dialogue_texts.id')
            ->where('version_dialogue_lines.game_version_id', $version->id)
            ->where('version_dialogue_lines.iso_code', $isoCode)
            ->whereNotNull('version_dialogue_lines.context')
            ->groupBy('version_dialogue_lines.context')
            ->select([
                'version_dialogue_lines.context',
                DB::raw("SUM(array_length(regexp_split_to_array(trim(unique_dialogue_texts.text_content), '\\s+'), 1)) as word_count"),
            ])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->context] = (int) $row->word_count;
        }

        return $result;
    }

    public function buildSimplifiedGraph(Collection $labels, Collection $edges, array $wordCounts = []): array
    {
        $outgoing = [];
        foreach ($labels as $label) {
            $outgoing[$label->name] = $edges->where('from_label', $label->name)->pluck('to_label')->values()->toArray();
        }

        $incoming = [];
        foreach ($labels as $label) {
            $incoming[$label->name] = $edges->where('to_label', $label->name)->pluck('from_label')->values()->toArray();
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
        foreach ($branchPoints as $name => $_) {
            $label = $labels->where('name', $name)->first();
            $simplifiedNodes[] = [
                'id' => $name,
                'label' => $name,
                'type' => 'branch',
                'is_start' => $name === 'start' || $name === 'labels.start',
                'is_ending' => (bool) ($label->is_ending ?? false),
                'word_count' => $wordCounts[$name] ?? 0,
            ];
            $labelToSimplified[$name] = $name;
        }

        foreach ($chains as $chain) {
            $first = $chain[0];
            $collapsedId = 'chain_' . md5(implode('|', $chain));
            $chainWordCount = array_sum(array_map(fn ($n) => $wordCounts[$n] ?? 0, $chain));

            $simplifiedNodes[] = [
                'id' => $collapsedId,
                'label' => count($chain) . ' nodes',
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
            $edgeKey = $sourceId . ':' . $targetId . ':' . $edge->edge_type;
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
