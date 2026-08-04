<?php

declare(strict_types=1);

use App\Models\GameVersion;
use App\Models\VersionRouteEdge;
use App\Models\VersionRouteLabel;
use App\Models\VersionRouteMenuChoice;
use App\Models\VersionRoutePath;
use App\Models\VersionRouteVariableChange;
use App\Services\RouteGraphService;
use App\Services\RoutePathCalculator;
use Mockery\MockInterface;

function routePathVersion(): GameVersion
{
    return GameVersion::factory()->create();
}

function calculateImportedRoutePaths(GameVersion $version): void
{
    app(RouteGraphService::class)->computeAndStore($version);
    (new RoutePathCalculator)->calculateAndStore($version);
}

function calculateRoutePathsFromGraph(GameVersion $version, array $graph): void
{
    $routeGraphService = Mockery::mock(RouteGraphService::class, function (MockInterface $mock) use ($version, $graph): void {
        $mock->shouldReceive('buildGraph')
            ->once()
            ->with($version)
            ->andReturn($graph);
    });

    (new RoutePathCalculator($routeGraphService))->calculateAndStore($version);
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

function routePathChoice(GameVersion $version, string $from, string $text, string $target, int $line = 10): VersionRouteMenuChoice
{
    return VersionRouteMenuChoice::create([
        'game_version_id' => $version->id,
        'from_label' => $from,
        'text' => $text,
        'target_label' => $target,
        'menu_line' => $line - 1,
        'condition' => 'True',
        'enclosing_condition' => null,
        'choice_condition' => 'True',
        'edge_type' => 'jump',
        'file_path' => 'script.rpy',
        'line_number' => $line,
    ]);
}

it('stores shortest route paths derived from the built route graph', function () {
    $version = routePathVersion();
    foreach (['start', 'branch', 'long_way', 'ending_good', 'ending_bad'] as $label) {
        routePathLabel($version, $label, str_starts_with($label, 'ending_'));
    }
    routePathEdge($version, 'start', 'branch');
    routePathEdge($version, 'branch', 'long_way');
    routePathEdge($version, 'long_way', 'ending_bad');
    routePathChoice($version, 'branch', 'Take the good ending', 'ending_good', 10);
    // The menu_choice edge that backs the choice above.
    VersionRouteEdge::create([
        'game_version_id' => $version->id,
        'from_label' => 'branch',
        'to_label' => 'ending_good',
        'edge_type' => 'menu_choice',
        'condition' => 'True',
        'file_path' => 'script.rpy',
        'line_number' => 10,
    ]);

    calculateImportedRoutePaths($version);

    $good = VersionRoutePath::where('game_version_id', $version->id)
        ->where('ending_label', 'ending_good')
        ->firstOrFail();
    $bad = VersionRoutePath::where('game_version_id', $version->id)
        ->where('ending_label', 'ending_bad')
        ->firstOrFail();

    // The expanded menu inserts a branch:choice_0 node between branch and its target,
    // so the path reflects what the route map actually navigates.
    expect($good->path_labels)->toBe(['start', 'branch', 'branch:choice_0', 'ending_good'])
        ->and($good->step_count)->toBe(4)
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

    calculateImportedRoutePaths($version);

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

    calculateImportedRoutePaths($version);

    expect($version->routePaths()->count())->toBe(1)
        ->and($version->routePaths()->first()->path_labels)->toBe(['labels.start', 'ending']);
});

it('strips condition scope nodes from stored path labels', function () {
    // A conditional edge produces a condition_scope node in the built graph.
    // The stored path should not include that structural wiring node.
    $version = routePathVersion();
    foreach (['start', 'choice_room', 'finale'] as $label) {
        routePathLabel($version, $label, $label === 'finale');
    }
    routePathEdge($version, 'start', 'choice_room');
    // Conditional edge with a 2-deep stack triggers condition-scope factoring.
    VersionRouteEdge::create([
        'game_version_id' => $version->id,
        'from_label' => 'choice_room',
        'to_label' => 'finale',
        'edge_type' => 'jump',
        'condition' => '(route_a == True) and (route_b == True)',
        'file_path' => 'script.rpy',
        'line_number' => 5,
    ]);

    calculateImportedRoutePaths($version);

    $path = $version->routePaths()->where('ending_label', 'finale')->firstOrFail();

    expect($path->path_labels)->toBe(['start', 'choice_room', 'finale'])
        ->and($path->step_count)->toBe(3)
        // No condition_scope:... ids leak into the stored labels.
        ->and(collect($path->path_labels)->every(fn (string $label) => ! str_starts_with($label, 'condition_scope:')))->toBeTrue();
});

it('skips endings that are unreachable from the start node', function () {
    $version = routePathVersion();
    routePathLabel($version, 'start');
    routePathLabel($version, 'reachable', true);
    routePathLabel($version, 'stranded', true); // an ending with no path from start
    routePathEdge($version, 'start', 'reachable');

    calculateImportedRoutePaths($version);

    $endings = $version->routePaths()->pluck('ending_label')->all();

    expect($endings)->toBe(['reachable'])
        ->and($version->routePaths()->count())->toBe(1);
});

it('stores every ending path for an imported route graph', function () {
    $version = routePathVersion();
    routePathLabel($version, 'start');

    for ($index = 1; $index <= 205; $index++) {
        $ending = 'ending_'.$index;
        routePathLabel($version, $ending, true);
        routePathEdge($version, 'start', $ending);
    }

    calculateImportedRoutePaths($version);

    expect($version->routePaths()->count())->toBe(205);
});

it('stores route paths deeper than 500 steps', function () {
    $version = routePathVersion();
    routePathLabel($version, 'start');
    $previous = 'start';

    for ($index = 1; $index <= 500; $index++) {
        $label = $index === 500 ? 'ending_deep' : 'step_'.$index;
        routePathLabel($version, $label, $label === 'ending_deep');
        routePathEdge($version, $previous, $label);
        $previous = $label;
    }

    calculateImportedRoutePaths($version);

    expect($version->routePaths()->firstOrFail()->step_count)->toBe(501);
});

it('stores all path labels for many deep endings', function () {
    $version = routePathVersion();
    routePathLabel($version, 'start');
    $previous = 'start';

    for ($index = 1; $index <= 200; $index++) {
        $ending = 'ending_chain_'.$index;
        routePathLabel($version, $ending, true);
        routePathEdge($version, $previous, $ending);
        $previous = $ending;
    }

    calculateImportedRoutePaths($version);

    expect($version->routePaths()->sum('step_count'))->toBe(20300)
        ->and($version->routePaths()->count())->toBe(200);
});

it('rejects excessive ending counts before traversing the graph and retains existing paths', function () {
    $version = routePathVersion();
    VersionRoutePath::create([
        'game_version_id' => $version->id,
        'ending_label' => 'existing',
        'path_labels' => ['start', 'existing'],
        'step_count' => 2,
        'word_count' => 0,
        'choice_count' => 0,
    ]);

    $endings = array_map(
        fn (int $index): string => 'ending_'.$index,
        range(1, RoutePathCalculator::MAX_ENDINGS + 1),
    );

    $graph = [
        'nodes' => [['id' => 'start', 'is_start' => true]],
        'edges' => [],
        'endings' => $endings,
    ];

    expect(fn () => calculateRoutePathsFromGraph($version, $graph))
        ->toThrow(RuntimeException::class, 'the limit is '.RoutePathCalculator::MAX_ENDINGS);

    expect($version->routePaths()->pluck('ending_label')->all())->toBe(['existing']);
});

it('rejects quadratic aggregate path growth and rolls back partial paths', function () {
    $version = routePathVersion();
    $nodeCount = (int) ceil(sqrt(RoutePathCalculator::MAX_TOTAL_PATH_STEPS * 2));
    $nodes = [['id' => 'start', 'is_start' => true]];
    $edges = [];
    $endings = [];
    $previous = 'start';

    for ($index = 1; $index <= $nodeCount; $index++) {
        $ending = 'ending_'.$index;
        $nodes[] = ['id' => $ending];
        $edges[] = ['source' => $previous, 'target' => $ending, 'edge_type' => 'jump'];
        $endings[] = $ending;
        $previous = $ending;
    }

    $graph = [
        'nodes' => $nodes,
        'edges' => $edges,
        'endings' => $endings,
    ];

    expect(fn () => calculateRoutePathsFromGraph($version, $graph))
        ->toThrow(RuntimeException::class, 'more than '.RoutePathCalculator::MAX_TOTAL_PATH_STEPS.' total steps');

    expect($version->routePaths()->count())->toBe(0);
});

it('stops reconstructing a path when it exceeds the per-path step limit', function () {
    $version = routePathVersion();
    $nodes = [['id' => 'start', 'is_start' => true]];
    $edges = [];
    $previous = 'start';

    for ($index = 1; $index <= RoutePathCalculator::MAX_PATH_STEPS; $index++) {
        $label = $index === RoutePathCalculator::MAX_PATH_STEPS ? 'ending_deep' : 'step_'.$index;
        $nodes[] = ['id' => $label];
        $edges[] = ['source' => $previous, 'target' => $label, 'edge_type' => 'jump'];
        $previous = $label;
    }

    $graph = [
        'nodes' => $nodes,
        'edges' => $edges,
        'endings' => ['ending_deep'],
    ];

    expect(fn () => calculateRoutePathsFromGraph($version, $graph))
        ->toThrow(RuntimeException::class, 'more than '.RoutePathCalculator::MAX_PATH_STEPS.' steps');

    expect($version->routePaths()->count())->toBe(0);
});

it('stores hub menu choice destinations as the choice target label', function () {
    $version = routePathVersion();
    routePathLabel($version, 'start');
    routePathLabel($version, 'chapterselect');
    routePathEdge($version, 'start', 'chapterselect');

    // 12 same-menu function-menu choices keep chapterselect a hub node whose
    // raw menu_choice edges survive with choice_text attached.
    for ($i = 1; $i <= 12; $i++) {
        routePathLabel($version, 'chapter'.$i, $i === 1);
        routePathChoice($version, 'chapterselect', 'Chapter '.$i, 'chapter'.$i, 10);
        VersionRouteEdge::create([
            'game_version_id' => $version->id,
            'from_label' => 'chapterselect',
            'to_label' => 'chapter'.$i,
            'edge_type' => 'menu_choice',
            'condition' => 'True',
            'file_path' => 'script.rpy',
            'line_number' => 10,
        ]);
    }

    calculateImportedRoutePaths($version);

    $path = $version->routePaths()->where('ending_label', 'chapter1')->firstOrFail();

    // The raw edge already lands on the destination label, so no lookahead:
    // walking one hop past it would fall back to to == from at endings.
    expect($path->choices)->toBe([
        ['from' => 'chapterselect', 'to' => 'chapter1', 'text' => 'Chapter 1'],
    ]);
});

it('does not persist condition scope wiring nodes as choice destinations', function () {
    $version = routePathVersion();
    routePathLabel($version, 'start');
    routePathLabel($version, 'choice_room');
    routePathLabel($version, 'finale', true);
    routePathEdge($version, 'start', 'choice_room');

    VersionRouteMenuChoice::create([
        'game_version_id' => $version->id,
        'from_label' => 'choice_room',
        'text' => 'Proceed',
        'target_label' => null,
        'menu_line' => 9,
        'condition' => 'True',
        'enclosing_condition' => null,
        'choice_condition' => 'True',
        'file_path' => 'script.rpy',
        'line_number' => 10,
    ]);
    VersionRouteVariableChange::create([
        'game_version_id' => $version->id,
        'label' => 'choice_room',
        'context' => 'menu_choice:Proceed',
        'variable_name' => 'proceeded',
        'value' => 'Constant(value=True)',
        'file_path' => 'script.rpy',
        'line_number' => 11,
    ]);

    // Second menu behind a 2-deep condition stack: the menu_sequence edge from
    // the first choice gets factored through condition_scope wiring nodes.
    foreach (['Go left', 'Go right'] as $index => $text) {
        VersionRouteMenuChoice::create([
            'game_version_id' => $version->id,
            'from_label' => 'choice_room',
            'text' => $text,
            'target_label' => 'finale',
            'edge_type' => 'jump',
            'menu_line' => 29,
            'condition' => '(route_a == True) and (route_b == True)',
            'enclosing_condition' => '(route_a == True) and (route_b == True)',
            'choice_condition' => 'True',
            'menu_condition_stack' => ['route_a == True', 'route_b == True'],
            'file_path' => 'script.rpy',
            'line_number' => 30 + $index,
        ]);
    }

    calculateImportedRoutePaths($version);

    $path = $version->routePaths()->where('ending_label', 'finale')->firstOrFail();

    expect($path->choices)->not->toBeNull();
    foreach ($path->choices as $choice) {
        expect(str_starts_with($choice['to'], 'condition_scope:'))->toBeFalse()
            ->and(str_starts_with($choice['from'], 'condition_scope:'))->toBeFalse();
    }
});
