<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Support\Routes\AuthoringScaffoldingClassifier;
use App\Support\Stats\StatsPayload;
use Illuminate\Support\Facades\DB;

class GameStatsRouteGraphPersister
{
    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly LanguageMappingService $languageMappingService,
        private readonly AuthoringScaffoldingClassifier $scaffolding,
    ) {}

    /**
     * Persist the route graph sections of a payload.
     *
     * Each section is pulled from the payload and flushed a batch at a time, so
     * the peak holds one batch rather than the whole graph.
     *
     * @return int the number of rows written across every section
     */
    public function save(GameVersion $version, StatsPayload $payload): int
    {
        $now = now();
        $scaffolding = $this->scaffolding;

        $version->route_graph_data = null;
        $version->route_graph_unreachable_data = null;
        $version->saveQuietly();

        $version->routeLabels()->delete();
        $version->routeEdges()->delete();
        $version->routeMenuChoices()->delete();
        $version->routeVariables()->delete();
        $version->routeVariableChanges()->delete();

        $written = 0;

        $written += $this->persistSection(
            $payload,
            'route_labels',
            'version_route_labels',
            fn (array $label): array => [
                'game_version_id' => $version->id,
                'name' => $label['name'] ?? '',
                'file_path' => $label['file'] ?? '',
                'line_number' => $label['line'] ?? 0,
                'is_ending' => $label['is_ending'] ?? false,
                'returns_to_caller' => $label['returns_to_caller'] ?? false,
                'externally_invoked' => $label['externally_invoked'] ?? false,
                'is_scaffolding' => $scaffolding->isScaffolding(
                    (string) ($label['name'] ?? ''),
                    (string) ($label['file'] ?? '')
                ),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $written += $this->persistSection(
            $payload,
            'route_edges',
            'version_route_edges',
            fn (array $edge): array => [
                'game_version_id' => $version->id,
                'from_label' => $edge['from_label'] ?? '',
                'to_label' => $edge['to_label'] ?? '',
                'edge_type' => $edge['edge_type'] ?? 'flow',
                'condition' => $edge['condition'] ?? null,
                'file_path' => $edge['file'] ?? null,
                'line_number' => $edge['line'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $game = $version->game;
        $written += $this->persistSection(
            $payload,
            'route_menu_choices',
            'version_route_menu_choices',
            function (array $choice) use ($version, $game, $now): array {
                $translations = $this->mapTranslationKeys($choice['translations'] ?? [], $game);
                $promptTranslations = $this->mapTranslationKeys($choice['prompt_translations'] ?? [], $game);

                return [
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
        );

        $seenVariables = [];
        $written += $this->persistSection(
            $payload,
            'route_variables',
            'version_route_variables',
            function (array $var) use ($version, $now, &$seenVariables): ?array {
                $key = $var['name'] ?? '';
                if (isset($seenVariables[$key])) {
                    return null;
                }
                $seenVariables[$key] = true;

                return [
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
        );

        $written += $this->persistSection(
            $payload,
            'route_variable_changes',
            'version_route_variable_changes',
            fn (array $change): array => [
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
            ]
        );

        return $written;
    }

    /**
     * @param  callable(array<string, mixed>): (array<string, mixed>|null)  $toRow
     */
    private function persistSection(StatsPayload $payload, string $section, string $table, callable $toRow): int
    {
        $batch = [];
        $written = 0;

        foreach ($payload->section($section) as $entry) {
            $row = $toRow($entry);
            if ($row === null) {
                continue;
            }

            $batch[] = $row;

            if (count($batch) >= self::BATCH_SIZE) {
                DB::table($table)->insert($batch);
                $written += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table($table)->insert($batch);
            $written += count($batch);
        }

        return $written;
    }

    private function mapTranslationKeys(array $translations, ?Game $game): array
    {
        $mapped = [];
        foreach ($translations as $langKey => $text) {
            $isoCode = $this->languageMappingService->resolveLanguageCode((string) $langKey, $game);
            if ($isoCode) {
                $mapped[$isoCode] = $text;
            }
        }

        return $mapped;
    }
}
