<?php

declare(strict_types=1);

use App\Console\Commands\RefreshGames;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveService;
use App\Services\GameStatsService;
use App\Services\GameVersionStatsImportService;
use App\Services\ImageProcessingService;
use App\Services\PlatformDetectionService;
use App\Services\SteamDataSyncService;
use App\Support\Stats\ArrayStatsPayload;
use App\Support\Stats\StatsPayload;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

it('validates thumbnail processing game selection options', function () {
    $this->mock(Client::class);
    $this->mock(ImageProcessingService::class)
        ->shouldReceive('setProgressReporter')
        ->once()
        ->andReturnSelf();

    $this->artisan('games:process-thumbnails')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);
});

it('validates stats import required parameters before reading files', function () {
    $this->mock(GameVersionStatsImportService::class);

    $this->artisan('games:import-stats')
        ->expectsOutput('Either game ID or game name is required')
        ->assertExitCode(1);

    $this->artisan('games:import-stats', ['--game-id' => 1])
        ->expectsOutput('Version ID is required')
        ->assertExitCode(1);

    $this->artisan('games:import-stats', ['--game-id' => 1, '--version-id' => 2])
        ->expectsOutput('Stats file path is required')
        ->assertExitCode(1);

    $this->artisan('games:import-stats', [
        '--game-id' => 1,
        '--version-id' => 2,
        '--stats-file' => '/tmp/missing-fvn-stats.json',
    ])
        ->expectsOutput('Stats file not found: /tmp/missing-fvn-stats.json')
        ->assertExitCode(1);
});

it('validates steam import and refresh commands before external calls', function () {
    $this->mock(PlatformDetectionService::class, function ($mock) {
        $mock->shouldReceive('extractSteamAppId')->with('https://store.steampowered.com/app/not-an-id/Game/')->andReturnNull();
    });
    $this->mock(SteamDataSyncService::class);

    $this->artisan('games:import-steam', ['url' => 'https://example.com/game'])
        ->expectsOutput('Invalid Steam URL. Must be a store.steampowered.com URL.')
        ->assertExitCode(1);

    $this->artisan('games:import-steam', ['url' => 'https://store.steampowered.com/app/not-an-id/Game/'])
        ->expectsOutput('Could not extract Steam App ID from URL. Expected format: https://store.steampowered.com/app/123456/Game_Name/')
        ->assertExitCode(1);

    $this->artisan('games:refresh-steam')
        ->expectsOutput('No refresh options selected. Please use at least one of: --update-data, --update-reviews')
        ->assertExitCode(1);

    $this->artisan('games:refresh-steam', ['--update-data' => true])
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);
});

it('validates delisted check selection options before external calls', function () {
    $this->artisan('games:check-delisted')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);
});

it('validates itch refresh options and selection before external calls', function () {
    expect(app(RefreshGames::class)->getDefinition()->getOption('force')->getDescription())
        ->toBe('Include abandoned/canceled games and reprocess existing version stats');

    $this->artisan('games:refresh', ['--all' => true])
        ->expectsOutput('No refresh options selected. Please use at least one of: --update-version, --update-info, --update-metadata')
        ->assertExitCode(1);

    $this->artisan('games:refresh', ['--update-info' => true])
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);
});

it('reports itch refresh selections and invalid sort before stopping on empty results', function () {
    $this->artisan('games:refresh', [
        '--game-name' => 'definitely missing visual novel',
        '--update-metadata' => true,
        '--force' => true,
        '--sort' => 'bad-column',
        '--max-retries' => 5,
        '--retry-cooldown' => 7,
    ])
        ->expectsOutput('Starting refresh for games matching name: "definitely missing visual novel"')
        ->expectsOutput('Force mode: Yes')
        ->expectsOutput('Options selected:')
        ->expectsOutput('- Version: No')
        ->expectsOutput('- Base Info: No')
        ->expectsOutput('- Metadata: Yes')
        ->expectsOutput('Retry settings:')
        ->expectsOutput('- Max retries: 5')
        ->expectsOutput('- Base cooldown: 7 seconds')
        ->expectsOutput("Invalid sort field: bad-column. Using 'updated_at' instead.")
        ->expectsOutput('Executing database query...')
        ->expectsOutput('No games found matching the selection criteria')
        ->assertExitCode(1);
});

it('validates game jam detail selections without external calls', function () {
    $this->artisan('game-jams:fetch-details', ['--id' => 999999])
        ->expectsOutput('No game jams found matching the selection criteria')
        ->assertExitCode(0);
});

it('validates version reimport command parameters before processing archives', function () {
    $this->artisan('games:reimport-version', ['--game-version' => '1.0'])
        ->expectsOutput('A specific game ID (--game-id) must be provided when specifying a version')
        ->assertExitCode(1);

    $this->artisan('games:reimport-version')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);
});

it('reimports stored archive statistics and handles missing archives and invalid timestamps', function () {
    $game = Game::factory()->create([
        'name' => 'Reimport Target',
        'is_visible' => true,
        'game_engine' => "Ren'Py",
    ]);
    $oldVersion = GameVersion::factory()->for($game)->create([
        'version' => '1.0',
        'is_latest' => false,
        'published_at' => now()->subMonth(),
    ]);
    $latestVersion = GameVersion::factory()->for($game)->create([
        'version' => '2.0',
        'is_latest' => true,
        'published_at' => now(),
    ]);

    Storage::fake('local');
    Storage::put("games/{$game->id}/{$latestVersion->id}/archive.zip", 'zip');
    $archivePath = Storage::path("games/{$game->id}/{$latestVersion->id}/archive.zip");

    $statsService = new class extends GameStatsService
    {
        public function extractGameStats(string $archivePath): ?StatsPayload
        {
            return new ArrayStatsPayload(['languages' => ['eng' => ['blocks' => 1]]]);
        }

        public function saveVersionStats(
            GameVersion $version,
            StatsPayload|array $stats,
            string $defaultLanguage = 'eng',
            ?Game $game = null
        ): void {
            $version->forceFill(['devlog' => 'stats-saved'])->save();
        }
    };
    $archiveService = new GameArchiveService($statsService);
    $this->app->instance(GameStatsService::class, $statsService);
    $this->app->instance(GameArchiveService::class, $archiveService);

    $this->artisan('games:reimport-version', ['--game-id' => $game->id])
        ->expectsOutput('Found 1 game(s):')
        ->expectsOutput("Processing version: {$latestVersion->version}")
        ->expectsOutput('Processing game archive...')
        ->expectsOutput('Saving version statistics...')
        ->expectsOutput('Statistics saved successfully')
        ->expectsOutputToContain('Reimport process completed')
        ->assertExitCode(0);

    expect($latestVersion->refresh()->devlog)->toBe('stats-saved');

    Storage::put("games/{$game->id}/{$oldVersion->id}/archive.zip", 'zip');

    $this->artisan('games:reimport-version', [
        '--game-id' => $game->id,
        '--game-version' => '1.0',
        '--timestamp' => '2030-01-02 03:04:05',
    ])
        ->expectsOutput("Processing version: {$oldVersion->version}")
        ->expectsOutput('Processing game archive...')
        ->expectsOutput('Saving version statistics...')
        ->expectsOutputToContain('Reimport process completed')
        ->assertExitCode(0);

    expect($oldVersion->refresh()->published_at?->format('Y-m-d H:i:s'))->toBe('2030-01-02 03:04:05')
        ->and($oldVersion->is_latest)->toBeTrue()
        ->and($latestVersion->refresh()->is_latest)->toBeFalse();

    Storage::delete("games/{$game->id}/{$oldVersion->id}/archive.zip");

    $realStatsService = new GameStatsService;
    $this->app->instance(GameStatsService::class, $realStatsService);
    $this->app->instance(GameArchiveService::class, new GameArchiveService($realStatsService));

    $this->artisan('games:reimport-version', [
        '--game-id' => $game->id,
        '--game-version' => '1.0',
    ])
        ->expectsOutput("Processing version: {$oldVersion->version}")
        ->expectsOutput('No stored archive found for this version, skipping')
        ->expectsOutputToContain('Reimport process completed')
        ->assertExitCode(0);

    $realStatsService = new GameStatsService;
    $this->app->instance(GameStatsService::class, $realStatsService);
    $this->app->instance(GameArchiveService::class, new GameArchiveService($realStatsService));

    $this->artisan('games:reimport-version', [
        '--game-id' => $game->id,
        '--game-version' => '1.0',
        '--timestamp' => 'not a date',
    ])
        ->expectsOutput("Processing version: {$oldVersion->version}")
        ->expectsOutput('Invalid timestamp format. Use YYYY-MM-DD HH:mm:ss')
        ->assertExitCode(1);
});
