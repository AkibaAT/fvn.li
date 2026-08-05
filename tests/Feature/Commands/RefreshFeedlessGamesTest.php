<?php

use App\Models\Game;
use App\Services\FlareSolverrSessionManager;

function runFeedlessCommandWithoutRealFlareSolverr(): void
{
    $sessionManager = Mockery::mock(FlareSolverrSessionManager::class);
    $sessionManager->shouldReceive('executeWithSession')
        ->byDefault()
        ->andReturnUsing(fn (string $commandName, callable $callback): mixed => $callback());

    app()->instance(FlareSolverrSessionManager::class, $sessionManager);
}

test('refresh feedless games command validates selection and handles empty selections', function () {
    runFeedlessCommandWithoutRealFlareSolverr();

    $this
        ->artisan('games:refresh-feedless')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);

    Game::factory()->create([
        'name' => 'Not Feedless',
        'platform' => 'itch_io',
        'is_visible' => true,
        'is_feedless' => false,
    ]);

    $this
        ->artisan('games:refresh-feedless --all')
        ->expectsOutput('Starting version refresh for feedless games')
        ->expectsOutput('No games found matching the selection criteria')
        ->assertExitCode(1);
});
