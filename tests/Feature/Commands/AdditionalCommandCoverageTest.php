<?php

use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameJam;
use App\Models\ImportState;
use App\Models\ProcessedEvent;
use App\Services\ItchAuthService;
use App\Services\ItchHttpClientService;
use App\Services\SteamDataSyncService;
use App\Services\SteamReviewImportService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

test('anonymize click stat ips validates batch size and exits cleanly when no records need work', function () {
    $this
        ->artisan('click-stats:anonymize-ips --batch-size=0 --force')
        ->expectsOutput('Batch size must be between 1 and 10000')
        ->assertExitCode(1);

    $game = Game::factory()->create();
    ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => 'local-session',
        'ip_address' => '127.0.0.1',
        'clicked_at' => now(),
    ]);

    $this
        ->artisan('click-stats:anonymize-ips --force')
        ->expectsOutput('Analyzing click statistics with IP addresses...')
        ->expectsOutput('No IP addresses found that need anonymization.')
        ->assertExitCode(0);
});

test('anonymize click stat ips supports dry runs and forced updates', function () {
    $game = Game::factory()->create();
    $firstClick = ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => 'session-one',
        'ip_address' => '203.0.113.42',
        'clicked_at' => now(),
    ]);
    $secondClick = ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_EXTERNAL_PROJECT,
        'session_id' => 'session-two',
        'ip_address' => '198.51.100.77',
        'clicked_at' => now()->subMinute(),
    ]);

    $this
        ->artisan('click-stats:anonymize-ips --dry-run --batch-size=1')
        ->expectsOutput('Analyzing click statistics with IP addresses...')
        ->expectsOutput('Found 2 click statistics with IP addresses that need anonymization.')
        ->expectsOutput('DRY RUN MODE - Showing what would be anonymized:')
        ->expectsOutput('Total records that would be updated: 2')
        ->assertExitCode(0);

    expect($firstClick->refresh()->ip_address)->toBe('203.0.113.42');

    $this
        ->artisan('click-stats:anonymize-ips --force --batch-size=1')
        ->expectsOutput('Analyzing click statistics with IP addresses...')
        ->expectsOutput('Found 2 click statistics with IP addresses that need anonymization.')
        ->expectsOutput('Starting IP address anonymization...')
        ->expectsOutput('IP address anonymization completed!')
        ->expectsOutput('Successfully processed: 2 records')
        ->expectsOutput('All IP addresses have been successfully anonymized!')
        ->assertExitCode(0);

    expect($firstClick->refresh()->ip_address)->toBe('203.0.113.0')
        ->and($secondClick->refresh()->ip_address)->toBe('198.51.100.0');
});

test('refresh steam games validates options and reports empty selections', function () {
    $this
        ->artisan('games:refresh-steam --all')
        ->expectsOutput('No refresh options selected. Please use at least one of: --update-data, --update-reviews')
        ->assertExitCode(1);

    $this
        ->artisan('games:refresh-steam --update-data')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);

    Game::factory()->create([
        'name' => 'Itch Only',
        'platform' => 'itch_io',
        'is_visible' => true,
    ]);

    $this
        ->artisan('games:refresh-steam --all --update-data --sort=bad-field --sleep=0')
        ->expectsOutput('Starting refresh for all visible Steam games')
        ->expectsOutput("Invalid sort field: bad-field. Using 'updated_at' instead.")
        ->expectsOutput('No games found matching the selection criteria')
        ->assertExitCode(1);
});

test('refresh steam games refreshes data and reviews with mocked steam services', function () {
    Config::set('scout.queue', true);
    Log::spy();

    $game = Game::factory()->create([
        'name' => 'Steam Coverage VN',
        'platform' => 'steam',
        'is_visible' => true,
        'status' => 'Released',
    ]);
    Game::factory()->create([
        'name' => 'Canceled Steam VN',
        'platform' => 'steam',
        'is_visible' => true,
        'status' => 'Canceled',
    ]);

    $dataService = Mockery::mock(SteamDataSyncService::class);
    $dataService
        ->shouldReceive('loadFullDetails')
        ->once()
        ->with(Mockery::on(fn (Game $received) => $received->id === $game->id));
    app()->instance(SteamDataSyncService::class, $dataService);

    $reviewService = Mockery::mock(SteamReviewImportService::class);
    $reviewService
        ->shouldReceive('syncAllReviews')
        ->once()
        ->with(Mockery::on(fn (Game $received) => $received->id === $game->id))
        ->andReturn([
            'fetched' => 5,
            'imported' => 3,
            'updated' => 1,
            'deleted' => 0,
            'skipped' => 1,
            'errors' => 0,
        ]);
    $reviewService
        ->shouldReceive('updateGameRatingStats')
        ->once()
        ->with(Mockery::on(fn (Game $received) => $received->id === $game->id));
    app()->instance(SteamReviewImportService::class, $reviewService);

    $this
        ->artisan('games:refresh-steam --all --update-data --update-reviews --sleep=0')
        ->expectsOutput('Starting refresh for all visible Steam games')
        ->expectsOutput('- Game Data: Yes')
        ->expectsOutput('- Reviews: Yes (complete sync)')
        ->expectsOutput('Found 1 game(s):')
        ->expectsOutput("- {$game->name} (ID: {$game->id}, Status: Released)")
        ->expectsOutput('  ✓ Game data updated successfully')
        ->expectsOutput('  ✓ Reviews synced successfully')
        ->expectsOutput('    Fetched: 5, Imported: 3, Updated: 1, Deleted: 0, Skipped: 1, Errors: 0')
        ->expectsOutput("✓ Successfully refreshed {$game->name}")
        ->assertExitCode(0);

    expect(Config::get('scout.queue'))->toBeFalse();
});

test('refresh steam games records service errors but continues processing', function () {
    Log::spy();

    $game = Game::factory()->create([
        'name' => 'Failing Steam VN',
        'platform' => 'steam',
        'is_visible' => true,
        'status' => 'Released',
    ]);

    $dataService = Mockery::mock(SteamDataSyncService::class);
    $dataService
        ->shouldReceive('loadFullDetails')
        ->once()
        ->andThrow(new RuntimeException('Steam data exploded'));
    app()->instance(SteamDataSyncService::class, $dataService);

    $reviewService = Mockery::mock(SteamReviewImportService::class);
    $reviewService->shouldReceive('syncAllReviews')->never();
    $reviewService->shouldReceive('updateGameRatingStats')->never();
    app()->instance(SteamReviewImportService::class, $reviewService);

    $this
        ->artisan("games:refresh-steam --game-id={$game->id} --update-data --sleep=0")
        ->expectsOutput("Starting refresh for game with ID: {$game->id}")
        ->expectsOutput('  × Error updating game data: Steam data exploded')
        ->expectsOutput("✓ Successfully refreshed {$game->name}")
        ->assertExitCode(0);

    Log::shouldHaveReceived('error')
        ->with('Steam game data refresh failed for Failing Steam VN', Mockery::on(
            fn (array $context) => $context['game_id'] === $game->id
                && $context['exception'] instanceof RuntimeException
        ))
        ->once();
});

test('refresh itch games validates options and reports empty selections', function () {
    $this
        ->artisan('games:refresh --all')
        ->expectsOutput('No refresh options selected. Please use at least one of: --update-version, --update-info, --update-metadata')
        ->assertExitCode(1);

    $this
        ->artisan('games:refresh --update-version')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);

    Game::factory()->create([
        'name' => 'Hidden Itch VN',
        'platform' => 'itch_io',
        'is_visible' => false,
    ]);

    $this
        ->artisan('games:refresh --all --update-version --sort=bad-field')
        ->expectsOutput('Starting refresh for all visible games')
        ->expectsOutput("Invalid sort field: bad-field. Using 'updated_at' instead.")
        ->expectsOutput('No games found matching the selection criteria')
        ->assertExitCode(1);
});

test('refresh itch games configures retry service and records per-game failures', function () {
    Log::spy();

    $game = Game::factory()->create([
        'name' => 'Failing Itch VN',
        'platform' => 'itch_io',
        'is_visible' => true,
        'status' => 'Released',
    ]);

    $itchClient = Mockery::mock(ItchHttpClientService::class);
    $itchClient->shouldReceive('setMaxRetries')->once()->with(2)->andReturnSelf();
    $itchClient->shouldReceive('setBaseCooldown')->once()->with(0)->andReturnSelf();
    $itchClient
        ->shouldReceive('executeWithRetry')
        ->once()
        ->with(Mockery::type('callable'), 'Version information', Mockery::type('callable'), Mockery::type('callable'))
        ->andThrow(new RuntimeException('itch version failed'));
    app()->instance(ItchHttpClientService::class, $itchClient);

    $this
        ->artisan("games:refresh --game-id={$game->id} --update-version --max-retries=2 --retry-cooldown=0")
        ->expectsOutput("Starting refresh for game with ID: {$game->id}")
        ->expectsOutput('- Version: Yes')
        ->expectsOutput('- Base Info: No')
        ->expectsOutput('- Metadata: No')
        ->expectsOutput('ItchHttpClientService configured successfully')
        ->expectsOutput("× Error refreshing {$game->name}: itch version failed")
        ->expectsOutputToContain('Refresh process completed')
        ->assertExitCode(0);

    expect($game->refresh()->error)->toBe('itch version failed');

    Log::shouldHaveReceived('error')
        ->with("Game refresh failed for {$game->name}", Mockery::on(
            fn (array $context) => $context['game_id'] === $game->id
                && $context['exception'] instanceof RuntimeException
        ))
        ->once();
});

test('fetch game jam details processes pending jams through the itch retry client', function () {
    Log::spy();

    $jam = GameJam::create([
        'name' => 'Parser Jam',
        'url' => 'https://example.itch.io/jam/parser-jam',
        'needs_details_fetch' => true,
    ]);

    $html = <<<'HTML'
        <main>
            <div class="formatted_description"><p>Jam description.</p></div>
            <div class="jam_host_header"><a>Jam Host</a></div>
        </main>
    HTML;

    $itchClient = Mockery::mock(ItchHttpClientService::class);
    $itchClient->shouldReceive('setMaxRetries')->once()->with(4)->andReturnSelf();
    $itchClient->shouldReceive('setBaseCooldown')->once()->with(0)->andReturnSelf();
    $itchClient
        ->shouldReceive('executeWithRetry')
        ->once()
        ->with(Mockery::type('callable'), 'Game jam details', Mockery::type('callable'), Mockery::type('callable'))
        ->andReturnUsing(function (callable $callback, string $operation, callable $onSuccess) {
            $result = $callback();
            $onSuccess($operation);

            return $result;
        });
    $itchClient
        ->shouldReceive('get')
        ->once()
        ->with($jam->url, ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new GuzzleResponse(200, [], $html));
    app()->instance(ItchHttpClientService::class, $itchClient);

    $this
        ->artisan('game-jams:fetch-details --max-retries=4 --retry-cooldown=0')
        ->expectsOutput('Found 1 game jam(s):')
        ->expectsOutput("- {$jam->name} (ID: {$jam->id})")
        ->expectsOutput('Processing 1 game jams...')
        ->expectsOutput('  Game jam details processed successfully')
        ->expectsOutput('✓ Details fetched successfully')
        ->expectsOutputToContain('Processing complete: 1 succeeded, 0 failed')
        ->assertExitCode(0);

    $jam->refresh();

    expect($jam->needs_details_fetch)->toBeFalse()
        ->and($jam->description)->toBe('<p>Jam description.</p>')
        ->and($jam->host)->toBe('Jam Host');
});

test('fetch game jam details rejects unsafe queued jam URLs before HTTP requests', function () {
    $jam = GameJam::create([
        'name' => 'Queued Internal Jam',
        'url' => 'http://127.0.0.1:8765/jam/internal-admin',
        'needs_details_fetch' => true,
    ]);

    $itchClient = Mockery::mock(ItchHttpClientService::class);
    $itchClient->shouldReceive('setMaxRetries')->once()->with(3)->andReturnSelf();
    $itchClient->shouldReceive('setBaseCooldown')->once()->with(0)->andReturnSelf();
    $itchClient
        ->shouldReceive('executeWithRetry')
        ->once()
        ->with(Mockery::type('callable'), 'Game jam details', Mockery::type('callable'), Mockery::type('callable'))
        ->andReturnUsing(fn (callable $callback) => $callback());
    $itchClient->shouldNotReceive('get');
    app()->instance(ItchHttpClientService::class, $itchClient);

    $this
        ->artisan('game-jams:fetch-details --retry-cooldown=0')
        ->expectsOutput('Found 1 game jam(s):')
        ->expectsOutput("- {$jam->name} (ID: {$jam->id})")
        ->expectsOutput('⚠ Failed to fetch details')
        ->expectsOutputToContain('Processing complete: 0 succeeded, 1 failed')
        ->assertExitCode(1);

    expect($jam->refresh()->needs_details_fetch)->toBeTrue();
});

test('cleanup game downloads validates selection and cleans all or selected games', function () {
    $this
        ->artisan('games:cleanup-downloads --all')
        ->expectsOutput('Cleaning up old downloads for all games...')
        ->expectsOutput('Cleanup completed successfully for 0 games')
        ->assertExitCode(0);

    $game = Game::factory()->create(['name' => 'Cleanup Target']);

    $this
        ->artisan("games:cleanup-downloads --game-id={$game->id}")
        ->expectsOutput('Found 1 game(s):')
        ->expectsOutput("- {$game->name} (ID: {$game->id}, Status: {$game->status})")
        ->expectsOutput("Cleaning up old downloads for game: {$game->name} (ID: {$game->id})")
        ->expectsOutput('Cleanup completed successfully for 1 game(s)')
        ->assertExitCode(0);

    $this
        ->artisan('games:cleanup-downloads')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);
});

test('process feed clears import state when the feed has no content', function () {
    Config::set('services.flaresolverr.enabled', false);
    Config::set('scout.queue', true);

    ImportState::create([
        'type' => 'feed',
        'last_processed_id' => 1234,
    ]);
    ProcessedEvent::create([
        'event_id' => 1200,
        'game_id' => 999,
    ]);

    $client = new Client([
        'handler' => HandlerStack::create(new MockHandler([
            new GuzzleResponse(200, [], json_encode(['next_page' => null], JSON_THROW_ON_ERROR)),
        ])),
    ]);

    $authService = Mockery::mock(ItchAuthService::class);
    $authService->shouldReceive('getClient')->once()->andReturn($client);
    app()->instance(ItchAuthService::class, $authService);

    $this
        ->artisan('feed:process')
        ->expectsOutput('Starting feed processing')
        ->expectsOutput('Resuming from previous import state at event 1234')
        ->expectsOutput('Processing page from event 1234')
        ->expectsOutput('Fetching feed from: https://itch.io/my-feed?filter=posts&format=json&from_event=1234')
        ->assertExitCode(0);

    expect(ImportState::where('type', 'feed')->exists())->toBeFalse()
        ->and(Config::get('scout.queue'))->toBeFalse();
});

test('process feed logs and fails when the authenticated client fails', function () {
    Config::set('services.flaresolverr.enabled', false);
    Log::spy();

    $client = new Client([
        'handler' => HandlerStack::create(new MockHandler([
            new RequestException('feed unavailable', new GuzzleRequest('GET', 'https://itch.io/my-feed')),
        ])),
    ]);

    $authService = Mockery::mock(ItchAuthService::class);
    $authService->shouldReceive('getClient')->once()->andReturn($client);
    app()->instance(ItchAuthService::class, $authService);

    $this
        ->artisan('feed:process')
        ->expectsOutput('Starting feed processing')
        ->expectsOutput('Processing page (initial)')
        ->expectsOutput('Error processing feed: feed unavailable')
        ->assertExitCode(1);

    Log::shouldHaveReceived('error')
        ->with('Feed processing failed', Mockery::on(
            fn (array $context) => $context['exception'] instanceof RequestException
        ))
        ->once();
});

test('backfill feed handles empty feed pages', function () {
    Config::set('services.flaresolverr.enabled', false);

    $game = Game::factory()->create();
    ProcessedEvent::create([
        'event_id' => 2000,
        'game_id' => $game->id,
    ]);

    $emptyClient = new Client([
        'handler' => HandlerStack::create(new MockHandler([
            new GuzzleResponse(200, [], json_encode(['next_page' => null], JSON_THROW_ON_ERROR)),
        ])),
    ]);

    $authService = Mockery::mock(ItchAuthService::class);
    $authService->shouldReceive('getClient')->once()->andReturn($emptyClient);
    app()->instance(ItchAuthService::class, $authService);

    $this
        ->artisan('feed:backfill --months=2')
        ->expectsOutput('Building map of latest processed events per game...')
        ->expectsOutput('Found 1 games with processed events')
        ->expectsOutput('Fetching feed from: https://itch.io/my-feed?filter=posts&format=json')
        ->assertExitCode(0);
});

test('backfill feed logs and fails when the authenticated client fails', function () {
    Config::set('services.flaresolverr.enabled', false);
    Log::spy();

    $failingClient = new Client([
        'handler' => HandlerStack::create(new MockHandler([
            new RequestException('backfill unavailable', new GuzzleRequest('GET', 'https://itch.io/my-feed')),
        ])),
    ]);

    $authService = Mockery::mock(ItchAuthService::class);
    $authService->shouldReceive('getClient')->once()->andReturn($failingClient);
    app()->instance(ItchAuthService::class, $authService);

    $this
        ->artisan('feed:backfill --months=1')
        ->expectsOutput('Error during backfill: backfill unavailable')
        ->assertExitCode(1);

    Log::shouldHaveReceived('error')
        ->with('Feed backfill failed', Mockery::on(
            fn (array $context) => $context['exception'] instanceof RequestException
        ))
        ->once();
});
