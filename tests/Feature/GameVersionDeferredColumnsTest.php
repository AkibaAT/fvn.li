<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\RouteGraphService;

function versionWithRouteGraph(): GameVersion
{
    $version = GameVersion::factory()->create(['game_id' => Game::factory()->create()->id, 'is_latest' => true]);
    $version->forceFill([
        'route_graph_data' => ['graph_revision' => RouteGraphService::GRAPH_REVISION, 'nodes' => []],
        'route_graph_unreachable_data' => ['graph_revision' => RouteGraphService::GRAPH_REVISION - 1],
    ])->saveQuietly();

    return $version;
}

test('default selects leave the route graph columns out', function () {
    $version = versionWithRouteGraph();

    $loaded = GameVersion::query()->findOrFail($version->id);
    $eagerLoaded = Game::with('latestVersion')->findOrFail($version->game_id)->latestVersion;

    expect($loaded->getAttributes())->not->toHaveKeys(GameVersion::DEFERRED_COLUMNS)
        ->and($eagerLoaded->getAttributes())->not->toHaveKeys(GameVersion::DEFERRED_COLUMNS)
        ->and($loaded->version)->toBe($version->version);
});

test('route graph columns load on first access and stay clean', function () {
    $loaded = GameVersion::query()->findOrFail(versionWithRouteGraph()->id);

    expect($loaded->route_graph_data['graph_revision'])->toBe(RouteGraphService::GRAPH_REVISION)
        ->and($loaded->isDirty())->toBeFalse();
});

test('explicit column selections are honoured', function () {
    $version = versionWithRouteGraph();

    $loaded = GameVersion::query()->whereKey($version->id)->get(['id', 'route_graph_data'])->first();

    expect(array_keys($loaded->getAttributes()))->toBe(['id', 'route_graph_data']);
});

test('the current route graph scope filters by stored revision', function () {
    $version = versionWithRouteGraph();

    expect(GameVersion::query()->withCurrentRouteGraph()->pluck('id')->all())->toBe([$version->id])
        ->and(GameVersion::query()->withCurrentRouteGraph(includeUnreachable: true)->count())->toBe(0);
});
