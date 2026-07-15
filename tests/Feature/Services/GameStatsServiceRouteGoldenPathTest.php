<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameStatsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

uses()->group('route-graph');

beforeEach(function () {
    config()->set('scout.driver', 'null');

    $this->originalEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    DB::table('iso_639_3_languages')->updateOrInsert(
        ['id' => 'eng'],
        [
            'part2b' => 'eng',
            'part2t' => 'eng',
            'part1' => 'en',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $this->game = Game::withoutEvents(fn () => Game::factory()->create([
        'name' => 'Route Golden Path',
        'slug' => 'route-golden-path',
    ]));

    $this->version = GameVersion::withoutEvents(fn () => GameVersion::factory()->create([
        'game_id' => $this->game->id,
        'version' => '1.0.0',
    ]));
});

afterEach(function () {
    if ($this->originalEventDispatcher) {
        Model::setEventDispatcher($this->originalEventDispatcher);
    }
});

test('route stats keep call continuations, choice assignments, and conditional exits', function () {
    $stats = [
        'languages' => [
            'default' => [
                'blocks' => 0,
                'words' => 0,
                'menus' => 1,
                'options' => 2,
                'characters' => [],
            ],
        ],
        'route_labels' => [
            ['name' => 'start', 'file' => 'script.rpy', 'line' => 1, 'is_ending' => false],
            ['name' => 'new_game_settings', 'file' => 'script.rpy', 'line' => 5, 'is_ending' => false, 'returns_to_caller' => true],
            ['name' => 'intro', 'file' => 'script.rpy', 'line' => 10, 'is_ending' => false],
            ['name' => 'lunch_tablemikko', 'file' => 'day1_1_cafeteria.rpy', 'line' => 20, 'is_ending' => false],
            ['name' => 'lunch_tablelake', 'file' => 'day1_1_cafeteria.rpy', 'line' => 30, 'is_ending' => false],
            ['name' => 'lunch_coach', 'file' => 'day1_1_cafeteria.rpy', 'line' => 40, 'is_ending' => false],
            ['name' => 'lunch_postmikko', 'file' => 'day1_1_cafeteria.rpy', 'line' => 50, 'is_ending' => false],
            ['name' => 'lunch_postlake', 'file' => 'day1_1_cafeteria.rpy', 'line' => 60, 'is_ending' => false],
        ],
        'route_edges' => [
            ['from_label' => 'start', 'to_label' => 'new_game_settings', 'edge_type' => 'call', 'file' => 'script.rpy', 'line' => 2],
            ['from_label' => 'start', 'to_label' => 'intro', 'edge_type' => 'jump', 'file' => 'script.rpy', 'line' => 4],
            ['from_label' => 'intro', 'to_label' => 'lunch_tablemikko', 'edge_type' => 'menu_choice', 'choice_text' => 'Sit with Mikko', 'file' => 'day1_1_cafeteria.rpy', 'line' => 21],
            ['from_label' => 'intro', 'to_label' => 'lunch_tablelake', 'edge_type' => 'menu_choice', 'choice_text' => 'Sit with Lake', 'file' => 'day1_1_cafeteria.rpy', 'line' => 24],
            ['from_label' => 'lunch_tablemikko', 'to_label' => 'lunch_coach', 'edge_type' => 'jump', 'file' => 'day1_1_cafeteria.rpy', 'line' => 28],
            ['from_label' => 'lunch_tablelake', 'to_label' => 'lunch_coach', 'edge_type' => 'jump', 'file' => 'day1_1_cafeteria.rpy', 'line' => 38],
            ['from_label' => 'lunch_coach', 'to_label' => 'lunch_postmikko', 'edge_type' => 'jump', 'condition' => 'lunch_person == "mikko"', 'file' => 'day1_1_cafeteria.rpy', 'line' => 482],
            ['from_label' => 'lunch_coach', 'to_label' => 'lunch_postlake', 'edge_type' => 'jump', 'condition' => 'not ((lunch_person == "mikko"))', 'file' => 'day1_1_cafeteria.rpy', 'line' => 484],
        ],
        'route_menu_choices' => [
            [
                'from_label' => 'intro',
                'text' => 'Sit with Mikko',
                'condition' => 'True',
                'choice_condition' => 'True',
                'target_label' => 'lunch_tablemikko',
                'edge_type' => 'jump',
                'file' => 'day1_1_cafeteria.rpy',
                'menu_line' => 19,
                'line' => 21,
            ],
            [
                'from_label' => 'intro',
                'text' => 'Sit with Lake',
                'condition' => 'True',
                'choice_condition' => 'True',
                'target_label' => 'lunch_tablelake',
                'edge_type' => 'jump',
                'file' => 'day1_1_cafeteria.rpy',
                'menu_line' => 19,
                'line' => 24,
            ],
        ],
        'route_variable_changes' => [
            [
                'label' => 'intro',
                'variable' => 'lunch_person',
                'operation' => '=',
                'value' => "Constant(value='mikko')",
                'file' => 'day1_1_cafeteria.rpy',
                'line' => 21,
                'context' => 'menu_choice:Sit with Mikko',
                'condition_stack' => [],
            ],
            [
                'label' => 'intro',
                'variable' => 'lunch_person',
                'operation' => '=',
                'value' => "Constant(value='lake')",
                'file' => 'day1_1_cafeteria.rpy',
                'line' => 24,
                'context' => 'menu_choice:Sit with Lake',
                'condition_stack' => [],
            ],
        ],
    ];

    app(GameStatsService::class)->saveVersionStats($this->version, $stats, 'eng', $this->game);

    expect(DB::table('version_route_edges')
        ->where('game_version_id', $this->version->id)
        ->where('from_label', 'start')
        ->orderBy('line_number')
        ->pluck('to_label')
        ->all())->toBe(['new_game_settings', 'intro']);

    expect(DB::table('version_route_edges')
        ->where('game_version_id', $this->version->id)
        ->where('from_label', 'lunch_coach')
        ->orderBy('line_number')
        ->pluck('condition', 'to_label')
        ->all())->toBe([
            'lunch_postmikko' => 'lunch_person == "mikko"',
            'lunch_postlake' => 'not ((lunch_person == "mikko"))',
        ]);

    expect(DB::table('version_route_variable_changes')
        ->where('game_version_id', $this->version->id)
        ->where('variable_name', 'lunch_person')
        ->orderBy('line_number')
        ->pluck('context', 'value')
        ->all())->toBe([
            "Constant(value='mikko')" => 'menu_choice:Sit with Mikko',
            "Constant(value='lake')" => 'menu_choice:Sit with Lake',
        ]);

    $version = $this->version->fresh();
    $graph = $version->route_graph_data;
    $newGameSettings = collect($graph['nodes'])->firstWhere('id', 'new_game_settings');
    $mikkoChoice = collect($graph['nodes'])->firstWhere('choice_text', 'Sit with Mikko');

    expect($newGameSettings)->not->toBeNull()
        ->and($newGameSettings['returns_to_caller'])->toBeTrue()
        ->and($mikkoChoice)->not->toBeNull()
        ->and($mikkoChoice['variable_changes'])->toHaveCount(1)
        ->and($mikkoChoice['variable_changes'][0]['variable'])->toBe('lunch_person')
        ->and($version->route_graph_unreachable_data['includes_unreachable'])->toBeTrue();
});
