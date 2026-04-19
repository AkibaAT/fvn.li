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
        if ($version->route_graph_data) {
            return $version->route_graph_data;
        }

        // Fallback: compute and store if not pre-calculated
        return $this->computeAndStore($version);
    }

    public function computeAndStore(GameVersion $version): array
    {
        $graph = $this->computeGraph($version);
        $version->route_graph_data = $graph;
        $version->saveQuietly();

        return $graph;
    }

    public function computeGraph(GameVersion $version): array
    {
        $labels = $version->routeLabels()->get();
        $edges = $version->routeEdges()->get();
        $menuChoices = $version->routeMenuChoices()->get();
        $variables = $version->routeVariables()->get();
        $variableChanges = $version->routeVariableChanges()->get();

        $wordCounts = self::getWordCountsByLabel($version);

        $edgeMapByFrom = $edges->groupBy('from_label');

        // Pre-index variable changes by label AND by context for fast lookup
        $varChangesByContext = [];
        foreach ($variableChanges as $vc) {
            $key = $vc->label . '|' . ($vc->context ?? '');
            $varChangesByContext[$key][] = $vc;
        }
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
            $meaningfulChoices = $nodeChoices->filter(function ($mc) use ($name, $varChangesByContext) {
                if (! empty($mc->target_label)) {
                    return true;
                }
                $contextKey = $name . '|menu_choice:' . ($mc->text ?? '');

                return ! empty($varChangesByContext[$contextKey]);
            });

            // Only collapse "function menus" (chapter select, gallery, etc.) into hub nodes.
            // Normal gameplay menus should always expand to show all choices.
            // Function menus are detected by label names, prompts, or choice patterns.
            $maxExpandedChoices = 10;

            // Get the prompt (question) for this menu early (needed for function menu detection)
            $firstChoice = $meaningfulChoices->first();
            $menuPrompt = $firstChoice?->prompt;
            $isFunctionMenu = $this->isFunctionMenu($name, $meaningfulChoices, $menuPrompt);

            if ($meaningfulChoices->isNotEmpty() && ($meaningfulChoices->count() <= $maxExpandedChoices || ! $isFunctionMenu)) {
                // This label has a manageable number of choices — expand into sub-graph
                $expandedLabels[$name] = true;
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

                    // Get variable changes for this specific choice (indexed lookup)
                    $choiceContext = 'menu_choice:' . ($mc->text ?? '');
                    $contextKey = $name . '|' . $choiceContext;
                    $choiceVarChanges = collect($varChangesByContext[$contextKey] ?? []);

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

        // Pre-index menu choices by from_label:target_label for O(1) lookup
        $choiceLookup = [];
        foreach ($menuChoices as $mc) {
            if (! empty($mc->target_label)) {
                $choiceLookup[$mc->from_label . ':' . $mc->target_label] = $mc;
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
                $matchingChoice = $choiceLookup[$edge->from_label . ':' . $edge->to_label] ?? null;
                if ($matchingChoice) {
                    $edgeData['choice_text'] = $matchingChoice->text;
                    $edgeData['condition'] = $matchingChoice->condition;
                }
            }

            $processedEdges[] = $edgeData;
        }

        // Post-process: infer else conditions and deduplicate
        $processedEdges = $this->inferElseConditions($processedEdges);

        // Bridge disconnected components: if start can't reach the main game,
        // add a synthetic edge from the last reachable node to the main component
        $processedEdges = $this->bridgeDisconnectedComponents($nodes, $processedEdges);

        // Pre-count variable changes by name
        $varChangeCounts = [];
        foreach ($variableChanges as $vc) {
            $varChangeCounts[$vc->variable_name] = ($varChangeCounts[$vc->variable_name] ?? 0) + 1;
        }

        $variableData = $variables->map(function ($v) use ($varChangeCounts) {
            return [
                'name' => $v->name,
                'default_value' => $v->default_value,
                'type' => $v->type,
                'change_count' => $varChangeCounts[$v->name] ?? 0,
            ];
        })->values()->toArray();

        $endings = $labels->where('is_ending', true)->pluck('name')->values()->toArray();

        return [
            'nodes' => $nodes,
            'edges' => $processedEdges,
            'variables' => $variableData,
            'endings' => $endings,
            'total_nodes' => $labels->count(),
            'total_edges' => $edges->count(),
            'has_graph_data' => true,
        ];
    }

    /**
     * When a label has conditional edges alongside unconditional flow edges to
     * different targets, the flow edges are the implicit "else" case.
     * Also removes duplicate edges to the same target when a conditional version exists.
     */
    /**
     * When start can only reach a small subset of the graph, bridge to
     * disconnected components via synthetic edges.
     * This handles games that use screen-based navigation we can't statically resolve.
     */

    private function bridgeDisconnectedComponents(array $nodes, array $edges): array
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
            while (! empty($queue)) {
                $current = array_shift($queue);
                foreach ($adjacency[$current] ?? [] as $target) {
                    if (! isset($visited[$target])) {
                        $visited[$target] = true;
                        $queue[] = $target;
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

            // Build incoming edge index
            $incomingFromReachable = [];
            foreach ($edges as $e) {
                if (isset($visited[$e['source']]) && ! isset($visited[$e['target']])) {
                    // Edge from reachable to unreachable — but target is already connected? No, it's unreachable
                }
                if (! isset($visited[$e['target']])) {
                    $incomingFromReachable[$e['target']] = ($incomingFromReachable[$e['target']] ?? 0) +
                        (isset($visited[$e['source']]) ? 1 : 0);
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
                $hasUnreachableIncoming = false;
                foreach ($edges as $e) {
                    if ($e['target'] === $id && ! isset($visited[$e['source']])) {
                        $hasUnreachableIncoming = true;
                        break;
                    }
                }

                $score = $outCount + ($hasUnreachableIncoming ? 0 : 1000);
                if ($score > $bestOutgoing) {
                    $bestOutgoing = $score;
                    $bestBridge = $id;
                }
            }

            if (! $bestBridge) {
                break;
            }

            // Find the last reachable label node as bridge source
            $bridgeSource = $startId;
            foreach ($nodes as $n) {
                if (isset($visited[$n['id']]) && ($n['node_type'] ?? '') === 'label' && ($n['has_menu_choice'] ?? false)) {
                    $bridgeSource = $n['id'];
                }
            }

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
     * Detect if a menu is a "function menu" (chapter select, gallery, etc.)
     * rather than a normal gameplay choice menu.
     *
     * Function menus are identified by:
     * - Label names containing keywords like "chapter", "gallery", "select", "menu_main"
     * - Prompt text indicating chapter/scene selection
     * - Choice text patterns like "Chapter X", "Scene Y", "CG Gallery"
     */
    private function isFunctionMenu(string $labelName, $choices, ?string $prompt): bool
    {
        // Check label name patterns
        $labelPatterns = ['/chapter[_\s]?select/i', '/chapter[_\s]?menu/i', '/gallery/i', '/select[_\s]?screen/i', '/main[_\s]?menu/i', '/extras/i', '/bonus/i'];
        foreach ($labelPatterns as $pattern) {
            if (preg_match($pattern, $labelName)) {
                return true;
            }
        }

        // Check prompt text patterns
        if ($prompt) {
            $promptPatterns = ['/^(select|choose)\s+(a\s+)?chapter/i', '/^(select|choose)\s+(a\s+)?scene/i', '/^chapter\s+select/i', '/^scene\s+select/i', '/^jump\s+to/i'];
            foreach ($promptPatterns as $pattern) {
                if (preg_match($pattern, trim($prompt))) {
                    return true;
                }
            }
        }

        // Check if all choices look like chapter/scene selectors
        if ($choices->count() > 5) {
            $chapterLikeChoices = 0;
            foreach ($choices as $choice) {
                $text = $choice->text ?? '';
                // Match patterns like "Chapter 1", "Scene 5", "Prologue", "Epilogue", "Day 1", "Route A"
                if (preg_match('/^(chapter|scene|day|route|part|act)\s*\d+/i', $text) ||
                    preg_match('/^(prologue|epilogue|intro|ending|bonus|extra|gallery|cg)/i', $text)) {
                    $chapterLikeChoices++;
                }
            }
            // If >80% of choices look like chapter selectors, it's likely a function menu
            if ($chapterLikeChoices / $choices->count() > 0.8) {
                return true;
            }
        }

        return false;
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
        // Pre-index edges for O(1) lookup instead of O(n) per label
        $outgoing = [];
        $incoming = [];
        foreach ($labels as $label) {
            $outgoing[$label->name] = [];
            $incoming[$label->name] = [];
        }
        foreach ($edges as $edge) {
            $outgoing[$edge->from_label][] = $edge->to_label;
            $incoming[$edge->to_label][] = $edge->from_label;
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
