<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Support\Facades\DB;

class GameStatsRouteGraphPersister
{
    public function __construct(
        private readonly LanguageMappingService $languageMappingService,
    ) {}

    public function save(GameVersion $version, array $stats): void
    {
        $now = now();

        $version->route_graph_data = null;
        $version->route_graph_unreachable_data = null;
        $version->saveQuietly();

        $version->routeLabels()->delete();
        $version->routeEdges()->delete();
        $version->routeMenuChoices()->delete();
        $version->routeVariables()->delete();
        $version->routeVariableChanges()->delete();

        if (isset($stats['route_labels']) && ! empty($stats['route_labels'])) {
            $labelBatch = [];
            foreach ($stats['route_labels'] as $label) {
                $labelBatch[] = [
                    'game_version_id' => $version->id,
                    'name' => $label['name'] ?? '',
                    'file_path' => $label['file'] ?? '',
                    'line_number' => $label['line'] ?? 0,
                    'is_ending' => $label['is_ending'] ?? false,
                    'returns_to_caller' => $label['returns_to_caller'] ?? false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($labelBatch, 1000) as $chunk) {
                DB::table('version_route_labels')->insert($chunk);
            }
        }

        if (isset($stats['route_edges']) && ! empty($stats['route_edges'])) {
            $edgeBatch = [];
            foreach ($stats['route_edges'] as $edge) {
                $edgeBatch[] = [
                    'game_version_id' => $version->id,
                    'from_label' => $edge['from_label'] ?? '',
                    'to_label' => $edge['to_label'] ?? '',
                    'edge_type' => $edge['edge_type'] ?? 'flow',
                    'condition' => $edge['condition'] ?? null,
                    'file_path' => $edge['file'] ?? null,
                    'line_number' => $edge['line'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($edgeBatch, 1000) as $chunk) {
                DB::table('version_route_edges')->insert($chunk);
            }
        }

        if (isset($stats['route_menu_choices']) && ! empty($stats['route_menu_choices'])) {
            $choiceBatch = [];
            $game = $version->game;
            foreach ($stats['route_menu_choices'] as $choice) {
                $translations = $this->mapTranslationKeys($choice['translations'] ?? [], $game);
                $promptTranslations = $this->mapTranslationKeys($choice['prompt_translations'] ?? [], $game);

                $choiceBatch[] = [
                    'game_version_id' => $version->id,
                    'from_label' => $choice['from_label'] ?? '',
                    'prompt' => $choice['prompt'] ?? null,
                    'prompt_translations' => ! empty($promptTranslations) ? json_encode($promptTranslations) : null,
                    'menu_line' => $choice['menu_line'] ?? 0,
                    'text' => $choice['text'] ?? null,
                    'translations' => ! empty($translations) ? json_encode($translations) : null,
                    'condition' => $choice['condition'] ?? null,
                    'enclosing_condition' => $choice['enclosing_condition'] ?? null,
                    'choice_condition' => $choice['choice_condition'] ?? null,
                    'menu_branch' => $choice['menu_branch'] ?? null,
                    'menu_condition_stack' => ! empty($choice['menu_condition_stack']) ? json_encode($choice['menu_condition_stack']) : null,
                    'parent_menu_line' => $choice['parent_menu_line'] ?? 0,
                    'parent_choice_line' => $choice['parent_choice_line'] ?? 0,
                    'target_label' => $choice['target_label'] ?? null,
                    'edge_type' => $choice['edge_type'] ?? null,
                    'file_path' => $choice['file'] ?? null,
                    'line_number' => $choice['line'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($choiceBatch, 1000) as $chunk) {
                DB::table('version_route_menu_choices')->insert($chunk);
            }
        }

        if (isset($stats['route_variables']) && ! empty($stats['route_variables'])) {
            $varBatch = [];
            $seen = [];
            foreach ($stats['route_variables'] as $var) {
                $key = $var['name'] ?? '';
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $varBatch[] = [
                    'game_version_id' => $version->id,
                    'name' => $var['name'] ?? '',
                    'default_value' => $var['default_value'] ?? null,
                    'type' => $var['type'] ?? 'default',
                    'file_path' => $var['file'] ?? null,
                    'line_number' => $var['line'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($varBatch, 1000) as $chunk) {
                DB::table('version_route_variables')->insert($chunk);
            }
        }

        if (isset($stats['route_variable_changes']) && ! empty($stats['route_variable_changes'])) {
            $changeBatch = [];
            foreach ($stats['route_variable_changes'] as $change) {
                $changeBatch[] = [
                    'game_version_id' => $version->id,
                    'label' => $change['label'] ?? '',
                    'variable_name' => $change['variable'] ?? '',
                    'operation' => $change['operation'] ?? '=',
                    'value' => $change['value'] ?? null,
                    'file_path' => $change['file'] ?? null,
                    'line_number' => $change['line'] ?? 0,
                    'context' => $change['context'] ?? null,
                    'condition' => $change['condition'] ?? null,
                    'condition_stack' => isset($change['condition_stack']) ? json_encode($change['condition_stack']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($changeBatch, 1000) as $chunk) {
                DB::table('version_route_variable_changes')->insert($chunk);
            }
        }
    }

    private function mapTranslationKeys(array $translations, ?Game $game): array
    {
        $mapped = [];
        foreach ($translations as $langKey => $text) {
            $isoCode = $this->languageMappingService->resolveLanguageCode($langKey, $game);
            if ($isoCode) {
                $mapped[$isoCode] = $text;
            }
        }

        return $mapped;
    }
}
