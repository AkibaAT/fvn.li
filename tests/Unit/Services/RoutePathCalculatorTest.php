<?php

declare(strict_types=1);

use App\Models\GameVersion;
use App\Models\VersionRouteEdge;
use App\Models\VersionRouteLabel;
use App\Models\VersionRouteMenuChoice;
use App\Models\VersionRoutePath;
use App\Services\RoutePathCalculator;

function routePathVersion(): GameVersion
{
    return GameVersion::factory()->create();
}

function routePathLabel(GameVersion $version, string $name, bool $isEnding = false): void
{
    VersionRouteLabel::create([
        'game_version_id' => $version->id,
        'name' => $name,
        'file_path' => 'script.rpy',
        'line_number' => 1,
        'is_ending' => $isEnding,
        'returns_to_caller' => false,
    ]);
}

function routePathEdge(GameVersion $version, string $from, string $to): void
{
    VersionRouteEdge::create([
        'game_version_id' => $version->id,
        'from_label' => $from,
        'to_label' => $to,
        'edge_type' => 'jump',
        'file_path' => 'script.rpy',
        'line_number' => 1,
    ]);
}

it('stores shortest route paths with menu choice metadata', function () {
    $version = routePathVersion();
    foreach (['start', 'branch', 'long_way', 'ending_good', 'ending_bad'] as $label) {
        routePathLabel($version, $label, str_starts_with($label, 'ending_'));
    }
    routePathEdge($version, 'start', 'branch');
    routePathEdge($version, 'branch', 'ending_good');
    routePathEdge($version, 'branch', 'long_way');
    routePathEdge($version, 'long_way', 'ending_bad');
    VersionRouteMenuChoice::create([
        'game_version_id' => $version->id,
        'from_label' => 'branch',
        'text' => 'Take the good ending',
        'target_label' => 'ending_good',
        'edge_type' => 'menu',
        'file_path' => 'script.rpy',
        'line_number' => 10,
    ]);

    (new RoutePathCalculator)->calculateAndStore($version);

    $good = VersionRoutePath::where('game_version_id', $version->id)
        ->where('ending_label', 'ending_good')
        ->firstOrFail();
    $bad = VersionRoutePath::where('game_version_id', $version->id)
        ->where('ending_label', 'ending_bad')
        ->firstOrFail();

    expect($good->path_labels)->toBe(['start', 'branch', 'ending_good'])
        ->and($good->step_count)->toBe(3)
        ->and($good->choice_count)->toBe(1)
        ->and($good->choices)->toBe([
            [
                'from' => 'branch',
                'to' => 'ending_good',
                'text' => 'Take the good ending',
            ],
        ])
        ->and($bad->path_labels)->toBe(['start', 'branch', 'long_way', 'ending_bad'])
        ->and($bad->choice_count)->toBe(0)
        ->and($bad->choices)->toBeNull();
});

it('clears existing paths when start or endings are missing', function () {
    $version = routePathVersion();
    VersionRoutePath::create([
        'game_version_id' => $version->id,
        'ending_label' => 'old',
        'path_labels' => ['start', 'old'],
        'step_count' => 2,
        'word_count' => 0,
        'choice_count' => 0,
    ]);
    routePathLabel($version, 'start');

    (new RoutePathCalculator)->calculateAndStore($version);

    expect($version->routePaths()->count())->toBe(0);
});

it('replaces stale paths and accepts labels.start as start label', function () {
    $version = routePathVersion();
    VersionRoutePath::create([
        'game_version_id' => $version->id,
        'ending_label' => 'stale',
        'path_labels' => ['stale'],
        'step_count' => 1,
        'word_count' => 0,
        'choice_count' => 0,
    ]);
    routePathLabel($version, 'labels.start');
    routePathLabel($version, 'ending', true);
    routePathEdge($version, 'labels.start', 'ending');

    (new RoutePathCalculator)->calculateAndStore($version);

    expect($version->routePaths()->count())->toBe(1)
        ->and($version->routePaths()->first()->path_labels)->toBe(['labels.start', 'ending']);
});
