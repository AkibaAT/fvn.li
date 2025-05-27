<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use Carbon\Carbon;

test('it reports platform support inconsistencies', function () {
    // Create a game with multiple versions where platform support decreases
    $game = Game::factory()->create(['name' => 'Test Game']);

    // Version 1: Windows only
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => Carbon::now()->subDays(30),
        'is_windows' => true,
        'is_linux' => false,
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
        'is_latest' => false,
    ]);

    // Version 2: Windows + Linux + Mac
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '2.0',
        'published_at' => Carbon::now()->subDays(20),
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => true,
        'is_android' => false,
        'is_web' => false,
        'is_latest' => false,
    ]);

    // Version 3: Only Windows (regression - should be reported)
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '3.0',
        'published_at' => Carbon::now()->subDays(10),
        'is_windows' => true,
        'is_linux' => false, // Lost Linux support
        'is_mac' => false,   // Lost Mac support
        'is_android' => false,
        'is_web' => false,
        'is_latest' => false,
    ]);

    // Version 4: Current version with all platforms
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '4.0',
        'published_at' => Carbon::now(),
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => true,
        'is_android' => true,
        'is_web' => true,
        'is_latest' => true,
    ]);

    // Run the report command
    $this->artisan('fix:platform-support:incremental')
        ->expectsOutput('Analyzing platform support consistency across game versions...')
        ->expectsOutputToContain('Game: Test Game')
        ->expectsOutput('Games analyzed: 1')
        ->expectsOutput('Games with platform issues: 1')
        ->assertExitCode(0);
});

test('it reports current version issues', function () {
    // Create a game where the current version has fewer platforms than previous
    $game = Game::factory()->create(['name' => 'Problem Game']);

    // Version 1: Windows + Linux
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => Carbon::now()->subDays(10),
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
        'is_latest' => false,
    ]);

    // Version 2: Current version with only Windows (problem!)
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '2.0',
        'published_at' => Carbon::now(),
        'is_windows' => true,
        'is_linux' => false, // Lost Linux support
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
        'is_latest' => true,
    ]);

    // Run the report command
    $this->artisan('fix:platform-support:incremental')
        ->expectsOutput('Games analyzed: 1')
        ->expectsOutput('Games with platform issues: 1')
        ->expectsOutputToContain('Game: Problem Game')
        ->expectsOutputToContain('Linux')
        ->assertExitCode(0);
});

test('it skips games with only one version', function () {
    // Create a game with only one version
    $game = Game::factory()->create();

    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'is_latest' => true,
    ]);

    // Run the report command
    $this->artisan('fix:platform-support:incremental')
        ->expectsOutput('No games found to analyze.')
        ->assertExitCode(0);
});

test('it can analyze a specific game by ID', function () {
    // Create two games
    $game1 = Game::factory()->create(['name' => 'Game 1']);
    $game2 = Game::factory()->create(['name' => 'Game 2']);

    // Both have platform regressions
    foreach ([$game1, $game2] as $game) {
        GameVersion::factory()->create([
            'game_id' => $game->id,
            'version' => '1.0',
            'published_at' => Carbon::now()->subDays(20),
            'is_windows' => true,
            'is_linux' => true,
            'is_latest' => false,
        ]);

        GameVersion::factory()->create([
            'game_id' => $game->id,
            'version' => '2.0',
            'published_at' => Carbon::now()->subDays(10),
            'is_windows' => true,
            'is_linux' => false, // This should be reported
            'is_latest' => false,
        ]);

        GameVersion::factory()->create([
            'game_id' => $game->id,
            'version' => '3.0',
            'published_at' => Carbon::now(),
            'is_windows' => true,
            'is_linux' => true,
            'is_latest' => true, // Current version
        ]);
    }

    // Analyze only game 1
    $this->artisan('fix:platform-support:incremental', ['--game-id' => $game1->id])
        ->expectsOutput('Games analyzed: 1')
        ->expectsOutput('Games with platform issues: 1')
        ->expectsOutputToContain('Game: Game 1')
        ->assertExitCode(0);
});

test('it shows no issues when platform support is consistent', function () {
    // Create a game with consistent platform support
    $game = Game::factory()->create(['name' => 'Good Game']);

    // Version 1: Windows only
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => Carbon::now()->subDays(20),
        'is_windows' => true,
        'is_linux' => false,
        'is_latest' => false,
    ]);

    // Version 2: Windows + Linux (incremental)
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '2.0',
        'published_at' => Carbon::now()->subDays(10),
        'is_windows' => true,
        'is_linux' => true, // Added Linux support
        'is_latest' => false,
    ]);

    // Version 3: Windows + Linux + Mac (incremental)
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '3.0',
        'published_at' => Carbon::now(),
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => true, // Added Mac support
        'is_latest' => true,
    ]);

    // Run the report command
    $this->artisan('fix:platform-support:incremental')
        ->expectsOutput('Games analyzed: 1')
        ->expectsOutput('Games with platform issues: 0')
        ->expectsOutput('No platform support inconsistencies found!')
        ->assertExitCode(0);
});
