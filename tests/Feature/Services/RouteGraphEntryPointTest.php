<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameStatsRouteGraphPersister;
use App\Services\RouteGraphPostProcessor;
use App\Support\Stats\ArrayStatsPayload;
use Illuminate\Support\Facades\DB;

it('stores labels that code enters by name as entry points', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();

    app(GameStatsRouteGraphPersister::class)->save($version, new ArrayStatsPayload([
        'route_labels' => [
            ['name' => 'start', 'file' => 'script.rpy', 'line' => 1],
            ['name' => 'show_area_unlock', 'file' => 'script.rpy', 'line' => 5, 'externally_invoked' => true],
            ['name' => 'orphan', 'file' => 'script.rpy', 'line' => 9],
        ],
    ]));

    $labels = DB::table('version_route_labels')
        ->where('game_version_id', $version->id)
        ->pluck('externally_invoked', 'name');

    expect((bool) $labels['show_area_unlock'])->toBeTrue()
        ->and((bool) $labels['orphan'])->toBeFalse()
        ->and((bool) $labels['start'])->toBeFalse();
});

it('only keeps nodes reachable from start in the default graph', function () {
    $nodes = [
        ['id' => 'start', 'is_start' => true],
        ['id' => 'chapter_one'],
        // Entered from a class method by name, so no edge leads here.
        ['id' => 'music_room', 'is_entry_point' => true],
        ['id' => 'music_room_track'],
        ['id' => 'truly_orphaned'],
    ];
    $edges = [
        ['source' => 'start', 'target' => 'chapter_one'],
        ['source' => 'music_room', 'target' => 'music_room_track'],
    ];

    [$filtered] = app(RouteGraphPostProcessor::class)->filterReachableFromStart($nodes, $edges);
    $ids = array_column($filtered, 'id');

    expect($ids)->toContain('start')
        ->and($ids)->toContain('chapter_one')
        ->and($ids)->not->toContain('music_room')
        ->and($ids)->not->toContain('music_room_track')
        ->and($ids)->not->toContain('truly_orphaned');
});
