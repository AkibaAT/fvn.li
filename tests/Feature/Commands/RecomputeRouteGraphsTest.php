<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionRouteEdge;
use App\Models\VersionRouteLabel;

test('recompute route graphs refreshes cached graphs and route paths', function () {
    config()->set('scout.driver', 'null');

    $game = Game::factory()->create(['name' => 'Recompute Target']);
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'route_graph_data' => ['graph_revision' => 1, 'nodes' => [], 'edges' => []],
    ]);

    foreach ([['start', false], ['finale', true]] as [$name, $isEnding]) {
        VersionRouteLabel::create([
            'game_version_id' => $version->id,
            'name' => $name,
            'file_path' => 'script.rpy',
            'line_number' => 1,
            'is_ending' => $isEnding,
        ]);
    }
    VersionRouteEdge::create([
        'game_version_id' => $version->id,
        'from_label' => 'start',
        'to_label' => 'finale',
        'edge_type' => 'jump',
        'file_path' => 'script.rpy',
        'line_number' => 2,
    ]);

    $this->artisan('route-graph:recompute', ['--game-id' => $game->id])
        ->expectsOutputToContain('Recomputing route graphs for 1 version(s)')
        ->assertExitCode(0);

    $version->refresh();

    expect($version->route_graph_data['graph_revision'])->toBeGreaterThan(1)
        ->and($version->routePaths()->where('ending_label', 'finale')->exists())->toBeTrue();
});

test('recompute route graphs fails cleanly when nothing matches the selection', function () {
    $this->artisan('route-graph:recompute', ['--game-id' => 999999])
        ->expectsOutputToContain('No game versions with route data found')
        ->assertExitCode(1);
});
