<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\PlatformDetectionService;
use App\Services\SteamDataSyncService;
use Illuminate\Support\Facades\Queue;

it('rejects duplicate Steam imports and invalid content types before creating games', function () {
    $existing = Game::factory()->create([
        'name' => 'Already Imported',
        'slug' => 'already-imported',
        'platform' => 'steam',
        'steam_app_id' => '123456',
        'url' => ['steam' => 'https://store.steampowered.com/app/123456/Already_Imported/'],
    ]);

    $this->mock(PlatformDetectionService::class, function ($mock) {
        $mock->shouldReceive('extractSteamAppId')
            ->twice()
            ->with('https://store.steampowered.com/app/123456/Already_Imported/')
            ->andReturn('123456');
        $mock->shouldReceive('extractSteamAppId')
            ->once()
            ->with('https://store.steampowered.com/app/654321/New_Game/')
            ->andReturn('654321');
    });
    $this->mock(SteamDataSyncService::class);

    $this->artisan('games:import-steam', ['url' => 'https://store.steampowered.com/app/123456/Already_Imported/'])
        ->expectsOutput("Game already exists: {$existing->name} (ID: {$existing->id})")
        ->assertExitCode(1);

    $this->artisan('games:import-steam', [
        'url' => 'https://store.steampowered.com/app/123456/Already_Imported/',
        '--content-type' => 'visual_novel',
    ])->assertExitCode(1);

    $this->artisan('games:import-steam', [
        'url' => 'https://store.steampowered.com/app/654321/New_Game/',
        '--content-type' => 'bad-type',
    ])
        ->expectsOutput('Invalid content type: bad-type. Valid options: visual_novel, adjacent, other (aliases: adjacent_game, other_content)')
        ->assertExitCode(1);
});

it('imports a Steam game record and skips review import when requested', function () {
    Queue::fake();

    $this->mock(PlatformDetectionService::class, function ($mock) {
        $mock->shouldReceive('extractSteamAppId')
            ->once()
            ->with('https://store.steampowered.com/app/987654/Fresh_Game/')
            ->andReturn('987654');
    });

    $this->mock(SteamDataSyncService::class, function ($mock) {
        $mock->shouldReceive('loadFullDetails')
            ->once()
            ->with(Mockery::on(function (Game $game): bool {
                $game->forceFill([
                    'name' => 'Fresh Steam VN',
                    'developer' => 'Steam Dev',
                    'status' => 'Released',
                    'is_paid' => false,
                    'is_nsfw' => true,
                    'has_demo' => true,
                ])->save();

                return $game->steam_app_id === '987654';
            }));
    });

    $this->artisan('games:import-steam', [
        'url' => 'https://store.steampowered.com/app/987654/Fresh_Game/',
        '--hidden' => true,
        '--content-type' => 'visual_novel',
        '--no-reviews' => true,
    ])
        ->expectsOutput('Starting Steam game import...')
        ->expectsOutput('Steam App ID: 987654')
        ->expectsOutput('Game data fetched and saved successfully')
        ->expectsOutput('Import complete.')
        ->expectsOutput('Game is hidden. To make it visible, edit it in the admin panel or run:')
        ->assertExitCode(0);

    $game = Game::where('steam_app_id', '987654')->firstOrFail();
    expect($game->name)->toBe('Fresh Steam VN')
        ->and($game->platform)->toBe('steam')
        ->and($game->is_visible)->toBeFalse()
        ->and($game->content_type)->toBe('visual_novel')
        ->and($game->getUrlForPlatform('steam'))->toBe('https://store.steampowered.com/app/987654/Fresh_Game/');
});

it('normalizes Steam import content type aliases to canonical game values', function () {
    Queue::fake();

    $this->mock(PlatformDetectionService::class, function ($mock) {
        $mock->shouldReceive('extractSteamAppId')
            ->once()
            ->with('https://store.steampowered.com/app/111111/Adjacent_Game/')
            ->andReturn('111111');
        $mock->shouldReceive('extractSteamAppId')
            ->once()
            ->with('https://store.steampowered.com/app/222222/Other_Content/')
            ->andReturn('222222');
    });

    $this->mock(SteamDataSyncService::class, function ($mock) {
        $mock->shouldReceive('loadFullDetails')
            ->twice()
            ->with(Mockery::type(Game::class));
    });

    $this->artisan('games:import-steam', [
        'url' => 'https://store.steampowered.com/app/111111/Adjacent_Game/',
        '--content-type' => 'adjacent',
        '--no-reviews' => true,
    ])->assertExitCode(0);

    $this->artisan('games:import-steam', [
        'url' => 'https://store.steampowered.com/app/222222/Other_Content/',
        '--content-type' => 'other_content',
        '--no-reviews' => true,
    ])->assertExitCode(0);

    expect(Game::where('steam_app_id', '111111')->firstOrFail()->content_type)->toBe('adjacent')
        ->and(Game::where('steam_app_id', '222222')->firstOrFail()->content_type)->toBe('other');
});
