<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;

it('reports no platform support inconsistencies when no multi-version games exist', function () {
    Game::factory()->create();

    $this->artisan('fix:platforms:incremental-support')
        ->expectsOutput('Analyzing platform support consistency across game versions...')
        ->expectsOutput('No games found to analyze.')
        ->assertExitCode(0);
});

it('reports platform support regressions across versions', function () {
    $game = Game::factory()->create(['name' => 'Platform Regression']);
    GameVersion::factory()->for($game)->create([
        'version' => '1.0',
        'published_at' => now()->subDays(2),
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
    ]);
    GameVersion::factory()->for($game)->create([
        'version' => '2.0',
        'published_at' => now()->subDay(),
        'is_windows' => true,
        'is_linux' => false,
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
    ])->forceFill(['is_latest' => true])->save();

    $this->artisan('fix:platforms:incremental-support', ['--game-id' => $game->id])
        ->expectsOutputToContain('Game: Platform Regression')
        ->expectsOutputToContain('Linux')
        ->expectsOutput('Games analyzed: 1')
        ->expectsOutput('Games with platform issues: 1')
        ->expectsOutput('Found platform support inconsistencies in 1 games.')
        ->assertExitCode(0);
});

it('summarizes clean multi-version games without warning tables', function () {
    $game = Game::factory()->create(['name' => 'Clean Platforms']);
    GameVersion::factory()->for($game)->create([
        'version' => '1.0',
        'published_at' => now()->subDays(2),
        'is_windows' => true,
        'is_linux' => false,
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
    ]);
    GameVersion::factory()->for($game)->create([
        'version' => '2.0',
        'published_at' => now()->subDay(),
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
    ])->forceFill(['is_latest' => true])->save();

    $this->artisan('fix:platforms:incremental-support', ['--game-id' => $game->id])
        ->doesntExpectOutputToContain('Game: Clean Platforms')
        ->assertExitCode(0);
});
