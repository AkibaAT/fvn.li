<?php

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\FlareSolverrSessionManager;
use App\Services\GameDataSyncService;

it('retries only due eligible current versions and remembers a failed retry', function () {
    $session = Mockery::mock(FlareSolverrSessionManager::class);
    $session->shouldReceive('executeWithSession')->andReturnUsing(fn ($name, $callback) => $callback());
    app()->instance(FlareSolverrSessionManager::class, $session);
    $eligible = ['platform' => 'itch_io', 'is_visible' => true, 'is_paid' => false,
        'is_stats_extraction_disabled' => false, 'game_engine' => 'unknown'];
    $game = Game::factory()->create($eligible);
    $version = GameVersion::factory()->for($game)->create(['is_latest' => true, 'version' => '1.2']);
    foreach ([['is_paid' => true], ['is_stats_extraction_disabled' => true], ['game_engine' => 'Unity'], ['is_visible' => false]] as $override) {
        GameVersion::factory()->for(Game::factory()->create(array_merge($eligible, $override)))
            ->create(['is_latest' => true, 'version' => '1.2']);
    }
    foreach ([['stats_skipped' => true], ['stats_attempts' => 5], ['stats_retry_at' => now()->addDay()]] as $state) {
        GameVersion::factory()->for(Game::factory()->create($eligible))
            ->create(array_merge(['is_latest' => true, 'version' => '1.2'], $state));
    }
    $sync = Mockery::mock(GameDataSyncService::class);
    $sync->shouldReceive('refreshVersion')->once()
        ->withArgs(fn (Game $candidate) => $candidate->is($game))
        ->andThrow(new RuntimeException('Uploads temporarily unavailable'));
    app()->instance(GameDataSyncService::class, $sync);

    $this->artisan('games:retry-stats')->assertSuccessful();
    expect($version->refresh()->stats_attempts)->toBe(1)
        ->and($version->stats_error)->toBe('Uploads temporarily unavailable')
        ->and($version->stats_retry_at)->not->toBeNull();
    $this->artisan('games:retry-stats')->assertSuccessful();
});
