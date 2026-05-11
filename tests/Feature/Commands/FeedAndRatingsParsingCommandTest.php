<?php

declare(strict_types=1);

use App\Console\Commands\BackfillFeed;
use App\Console\Commands\BackfillRatings;
use App\Console\Commands\ImportRatings;
use App\Console\Commands\ProcessFeed;
use App\Models\Game;
use App\Models\ImportState;
use App\Models\ProcessedEvent;
use App\Models\Rater;
use App\Models\Rating;
use App\Services\GameDataSyncService;
use App\Services\ItchAuthService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function invokeFeedRatingsCommandMethod(object $command, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($command);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($command, $arguments);
}

function setFeedRatingsCommandProperty(object $command, string $property, mixed $value): void
{
    $reflection = new ReflectionClass($command);
    $propertyReflection = $reflection->getProperty($property);
    $propertyReflection->setAccessible(true);
    $propertyReflection->setValue($command, $value);
}

function feedRatingsCommandResponse(array $payload): Response
{
    return new Response(200, [], json_encode($payload, JSON_THROW_ON_ERROR));
}

function attachFeedRatingsCommandOutput(object $command): void
{
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
}

function ratingEventHtml(
    int $eventId = 555,
    int $gameId = 777,
    int $userId = 999,
    int $stars = 4,
    string $reviewHtml = '<p>Worth playing.</p>',
): string {
    $starHtml = str_repeat('<span class="icon-star"></span>', $stars);

    return <<<HTML
<div class="event_row">
    <script type="text/javascript">window.eventData = { user_id:{$userId} };</script>
    <a class="event_source_user" href="https://alice.itch.io">Alice</a>
    <a class="event_time" href="https://itch.io/feed/{$eventId}" title="2026-01-02 12:00:00">now</a>
    <div class="game_cell" data-game_id="{$gameId}"></div>
    <a class="object_title" href="https://dev.itch.io/game">Game Name</a>
    {$starHtml}
    <div class="rating_blurb">{$reviewHtml}</div>
</div>
HTML;
}

it('imports a rating page into rater game rating and import state records', function () {
    $auth = Mockery::mock(ItchAuthService::class);
    $command = new ImportRatings($auth);
    $command->setLaravel(app());
    attachFeedRatingsCommandOutput($command);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/feed?filter=ratings&format=json')
        ->andReturn(feedRatingsCommandResponse([
            'content' => ratingEventHtml(),
        ]));
    setFeedRatingsCommandProperty($command, 'client', $client);

    $newRatings = 0;
    $errors = 0;

    expect(invokeFeedRatingsCommandMethod($command, 'importRatingsPage', [null, &$newRatings, &$errors]))->toBeNull()
        ->and($newRatings)->toBe(1)
        ->and($errors)->toBe(0);

    $game = Game::query()->where('itch_id', 777)->firstOrFail();
    $rater = Rater::query()->where('itch_id', 999)->firstOrFail();
    $rating = Rating::query()->where('event_id', 555)->firstOrFail();

    expect($game->name)->toBe('Game Name')
        ->and($game->getUrlForPlatform('itch_io'))->toBe('https://dev.itch.io/game')
        ->and($rater->name)->toBe('Alice')
        ->and($rating->game_id)->toBe($game->id)
        ->and($rating->rater_id)->toBe($rater->id)
        ->and($rating->rating)->toBe(4.0)
        ->and($rating->review)->toContain('<p>Worth playing.</p>')
        ->and($rating->is_reviewed)->toBeTrue()
        ->and(ImportState::query()->where('type', 'ratings')->value('last_processed_id'))->toBe(555);
});

it('sanitizes imported rating blurb html before storing reviews', function () {
    $auth = Mockery::mock(ItchAuthService::class);
    $command = new ImportRatings($auth);
    $command->setLaravel(app());
    attachFeedRatingsCommandOutput($command);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')
        ->once()
        ->andReturn(feedRatingsCommandResponse([
            'content' => ratingEventHtml(
                eventId: 557,
                reviewHtml: '<p onclick="alert(1)" style="color:red;position:absolute;background-image:url(javascript:alert(2))">Worth <strong>playing</strong></p><a href="javascript:alert(3)">bad link</a><script>alert(4)</script><img src="x" onerror="alert(5)">&lt;script&gt;alert(6)&lt;/script&gt;',
            ),
        ]));
    setFeedRatingsCommandProperty($command, 'client', $client);

    $newRatings = 0;
    $errors = 0;

    invokeFeedRatingsCommandMethod($command, 'importRatingsPage', [null, &$newRatings, &$errors]);

    $review = Rating::query()->where('event_id', 557)->value('review');

    expect($newRatings)->toBe(1)
        ->and($errors)->toBe(0)
        ->and($review)->toContain('<strong>playing</strong>')
        ->and($review)->toContain('style="color:red"')
        ->and($review)->toContain('&lt;script&gt;alert(6)&lt;/script&gt;')
        ->and($review)->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('position:absolute')
        ->not->toContain('background-image');
});

it('stops rating import when an existing event is encountered', function () {
    $game = Game::factory()->create(['itch_id' => 777]);
    $rater = Rater::factory()->create(['itch_id' => 999]);
    Rating::create([
        'event_id' => 555,
        'published_at' => now(),
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 3,
        'review' => '',
        'is_visible' => true,
        'is_reviewed' => false,
        'source_platform' => 'fvn_li',
    ]);
    ImportState::create(['type' => 'ratings', 'last_processed_id' => 123]);

    $auth = Mockery::mock(ItchAuthService::class);
    $command = new ImportRatings($auth);
    $command->setLaravel(app());
    attachFeedRatingsCommandOutput($command);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')
        ->once()
        ->andReturn(feedRatingsCommandResponse([
            'content' => ratingEventHtml(),
        ]));
    setFeedRatingsCommandProperty($command, 'client', $client);

    $newRatings = 0;
    $errors = 0;

    expect(invokeFeedRatingsCommandMethod($command, 'importRatingsPage', [null, &$newRatings, &$errors]))->toBeNull()
        ->and($newRatings)->toBe(0)
        ->and($errors)->toBe(0)
        ->and(ImportState::query()->where('type', 'ratings')->exists())->toBeFalse();
});

it('backfill rating processing updates existing game and hides older rater ratings', function () {
    $game = Game::factory()->create([
        'itch_id' => 777,
        'name' => 'Old Name',
    ]);
    $game->setUrlForPlatform('itch_io', 'https://old.example/game');
    $game->save();
    $rater = Rater::factory()->create(['itch_id' => 999]);
    $oldRating = Rating::create([
        'published_at' => now()->subDay(),
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 2,
        'review' => 'old',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'fvn_li',
    ]);

    $auth = Mockery::mock(ItchAuthService::class);
    $command = new BackfillRatings($auth);
    $command->setLaravel(app());

    invokeFeedRatingsCommandMethod($command, 'processRating', [
        888,
        999,
        'Alice Updated',
        'alice',
        new DateTime('2026-01-03 12:00:00'),
        777,
        'Updated Name',
        'https://dev.itch.io/updated',
        5,
        '',
    ]);

    $game->refresh();
    $rater->refresh();
    $newRating = Rating::query()->where('event_id', 888)->firstOrFail();

    expect($game->name)->toBe('Updated Name')
        ->and($game->getUrlForPlatform('itch_io'))->toBe('https://dev.itch.io/updated')
        ->and($rater->name)->toBe('Alice Updated')
        ->and($oldRating->fresh()->is_visible)->toBeFalse()
        ->and($newRating->rating)->toBe(5.0)
        ->and($newRating->is_reviewed)->toBeFalse();
});

it('sanitizes backfilled rating blurb html before storing reviews', function () {
    Queue::fake();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/feed?filter=ratings&format=json')
        ->andReturn(feedRatingsCommandResponse([
            'content' => ratingEventHtml(
                eventId: 559,
                gameId: 779,
                userId: 1001,
                reviewHtml: '<div style="text-align:center;position:fixed" onmouseover="alert(1)">Looks <em>good</em></div><a href="javascript:alert(2)">bad link</a>',
            ),
        ]));

    $auth = Mockery::mock(ItchAuthService::class);
    $auth->shouldReceive('getClient')->once()->andReturn($client);
    $this->app->instance(ItchAuthService::class, $auth);

    $this->artisan('ratings:backfill')
        ->expectsOutput('Fetching ratings from: https://itch.io/feed?filter=ratings&format=json')
        ->expectsOutput('Backfill completed:')
        ->expectsOutput('- Processed 0 pages')
        ->expectsOutput('- Found 1 new ratings')
        ->expectsOutput('- Encountered 0 errors')
        ->assertExitCode(0);

    $review = Rating::query()->where('event_id', 559)->value('review');

    expect($review)->toContain('<em>good</em>')
        ->and($review)->toContain('style="text-align:center"')
        ->and($review)->not->toContain('onmouseover')
        ->not->toContain('javascript:')
        ->not->toContain('position:fixed');
});

it('runs the ratings backfill command through one batch page', function () {
    Queue::fake();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/feed?filter=ratings&format=json')
        ->andReturn(feedRatingsCommandResponse([
            'next_page' => 777,
            'content' => ratingEventHtml(eventId: 556, gameId: 778, userId: 1000, stars: 5),
        ]));

    $auth = Mockery::mock(ItchAuthService::class);
    $auth->shouldReceive('getClient')->once()->andReturn($client);
    $this->app->instance(ItchAuthService::class, $auth);

    $this->artisan('ratings:backfill', ['--batch-size' => 1])
        ->expectsOutput('Fetching ratings from: https://itch.io/feed?filter=ratings&format=json')
        ->expectsOutput('Batch limit of 1 pages reached. Run the command again to continue.')
        ->expectsOutput('Backfill completed:')
        ->expectsOutput('- Processed 1 pages')
        ->expectsOutput('- Found 1 new ratings')
        ->expectsOutput('- Encountered 0 errors')
        ->expectsOutput('- Stopped due to batch size limit. Run again to continue.')
        ->assertExitCode(0);

    expect(ImportState::query()->where('type', 'ratings_backfill')->value('last_processed_id'))->toBe(556)
        ->and(Rating::query()->where('event_id', 556)->exists())->toBeTrue()
        ->and(Game::query()->where('itch_id', 778)->value('name'))->toBe('Game Name')
        ->and(Rater::query()->where('itch_id', 1000)->value('username'))->toBe('alice');
});

it('reports ratings backfill authentication failures', function () {
    $auth = Mockery::mock(ItchAuthService::class);
    $auth->shouldReceive('getClient')->once()->andThrow(new RuntimeException('missing itch auth'));
    $this->app->instance(ItchAuthService::class, $auth);

    $this->artisan('ratings:backfill')
        ->expectsOutput('Error backfilling ratings: missing itch auth')
        ->assertExitCode(1);
});

it('processes feed pages and records skipped non-visible game events', function () {
    $game = Game::factory()->create([
        'itch_id' => 4321,
        'is_visible' => false,
    ]);

    $auth = Mockery::mock(ItchAuthService::class);
    $command = new ProcessFeed($auth);
    $command->setLaravel(app());
    attachFeedRatingsCommandOutput($command);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/my-feed?filter=posts&format=json')
        ->andReturn(feedRatingsCommandResponse([
            'next_page' => 100,
            'content' => <<<'HTML'
<div class="event_row"><span class="like_btn" data-like_url="https://itch.io/post/900/like"></span></div>
<div class="event_row">
    <span class="like_btn" data-like_url="https://itch.io/post/901/like"></span>
    <div class="game_cell" data-game_id="4321"><a class="game_link" href="https://dev.itch.io/hidden"></a></div>
    <div class="object_short_summary"><a href="https://dev.itch.io/hidden">Hidden Game</a></div>
</div>
HTML,
        ]));

    expect(invokeFeedRatingsCommandMethod($command, 'processFeedPage', [$client, null]))->toBe(100)
        ->and(ProcessedEvent::query()->where('event_id', 901)->exists())->toBeFalse()
        ->and($game->fresh()->error)->toBeNull();
});

it('backfills feed pages with cutoff and already processed game skips', function () {
    $visibleGame = Game::factory()->create([
        'itch_id' => 4321,
        'is_visible' => false,
    ]);
    $alreadySeenGame = Game::factory()->create([
        'itch_id' => 7777,
        'is_visible' => true,
    ]);

    $auth = Mockery::mock(ItchAuthService::class);
    $command = new BackfillFeed($auth);
    $command->setLaravel(app());
    attachFeedRatingsCommandOutput($command);
    setFeedRatingsCommandProperty($command, 'cutoffDate', Carbon::parse('2026-01-01 00:00:00'));
    setFeedRatingsCommandProperty($command, 'latestEventPerGame', [
        7777 => 7000,
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/my-feed?filter=posts&format=json&from_event=6000')
        ->andReturn(feedRatingsCommandResponse([
            'next_page' => 7000,
            'content' => <<<'HTML'
<div class="event_row">
    <span class="like_btn" data-like_url="https://itch.io/post/6001/like"></span>
    <a class="event_time" title="2026-01-02 12:00:00">new</a>
    <div class="game_cell" data-game_id="7777"><a class="game_link" href="https://dev.itch.io/already"></a></div>
    <div class="object_short_summary"><a href="https://dev.itch.io/already">Already Seen</a></div>
</div>
<div class="event_row">
    <span class="like_btn" data-like_url="https://itch.io/post/6002/like"></span>
    <a class="event_time" title="2026-01-02 12:00:00">new</a>
    <div class="game_cell" data-game_id="4321"><a class="game_link" href="https://dev.itch.io/hidden"></a></div>
    <div class="object_short_summary"><a href="https://dev.itch.io/hidden">Hidden Game</a></div>
</div>
<div class="event_row">
    <span class="like_btn" data-like_url="https://itch.io/post/6003/like"></span>
    <a class="event_time" title="2025-12-31 12:00:00">old</a>
    <div class="game_cell" data-game_id="4321"><a class="game_link" href="https://dev.itch.io/hidden"></a></div>
    <div class="object_short_summary"><a href="https://dev.itch.io/hidden">Hidden Game</a></div>
</div>
HTML,
        ]));

    expect(invokeFeedRatingsCommandMethod($command, 'processFeedPage', [$client, 6000]))
        ->toBe([7000, true])
        ->and(ProcessedEvent::query()->where('event_id', 6002)->exists())->toBeFalse()
        ->and($visibleGame->fresh()->error)->toBeNull()
        ->and($alreadySeenGame->fresh()->error)->toBeNull();
});

it('backfill feed records processed events with external itch game ids for later skips', function () {
    $game = Game::factory()->create([
        'itch_id' => 12345,
        'is_visible' => true,
    ]);
    expect($game->id)->not->toBe(12345);

    $syncService = Mockery::mock(GameDataSyncService::class);
    $syncService->shouldReceive('refreshVersion')
        ->once()
        ->with(Mockery::on(fn (Game $refreshedGame): bool => $refreshedGame->id === $game->id), false);
    app()->instance(GameDataSyncService::class, $syncService);

    $auth = Mockery::mock(ItchAuthService::class);
    $client = Mockery::mock(Client::class);
    $feedPayload = [
        'content' => <<<'HTML'
<div class="event_row">
    <span class="like_btn" data-like_url="https://itch.io/post/8100/like"></span>
    <a class="event_time" title="2026-01-02 12:00:00">new</a>
    <div class="game_cell" data-game_id="12345"><a class="game_link" href="https://dev.itch.io/visible"></a></div>
    <div class="object_short_summary"><a href="https://dev.itch.io/visible">Visible Game</a></div>
</div>
HTML,
    ];
    $client->shouldReceive('get')
        ->twice()
        ->with('https://itch.io/my-feed?filter=posts&format=json')
        ->andReturn(feedRatingsCommandResponse($feedPayload), feedRatingsCommandResponse($feedPayload));

    $firstCommand = new BackfillFeed($auth);
    $firstCommand->setLaravel(app());
    attachFeedRatingsCommandOutput($firstCommand);
    setFeedRatingsCommandProperty($firstCommand, 'cutoffDate', Carbon::parse('2026-01-01 00:00:00'));
    setFeedRatingsCommandProperty($firstCommand, 'latestEventPerGame', []);

    expect(invokeFeedRatingsCommandMethod($firstCommand, 'processFeedPage', [$client, null]))
        ->toBe([null, false])
        ->and(ProcessedEvent::query()->where('event_id', 8100)->where('game_id', 12345)->count())->toBe(1);

    $secondCommand = new BackfillFeed($auth);
    $secondCommand->setLaravel(app());
    attachFeedRatingsCommandOutput($secondCommand);
    setFeedRatingsCommandProperty($secondCommand, 'cutoffDate', Carbon::parse('2026-01-01 00:00:00'));
    invokeFeedRatingsCommandMethod($secondCommand, 'buildLatestEventMap');

    expect(invokeFeedRatingsCommandMethod($secondCommand, 'processFeedPage', [$client, null]))
        ->toBe([null, false])
        ->and(ProcessedEvent::query()->where('event_id', 8100)->count())->toBe(1);
});

it('backfill feed summary fallback resolves game ids and skips failures', function () {
    $game = Game::factory()->create([
        'itch_id' => 8888,
        'is_visible' => false,
    ]);

    $auth = Mockery::mock(ItchAuthService::class);
    $auth->shouldReceive('getGameId')
        ->once()
        ->with('https://dev.itch.io/from-summary')
        ->andReturn(8888);
    $auth->shouldReceive('getGameId')
        ->once()
        ->with('https://dev.itch.io/unresolvable')
        ->andThrow(new RuntimeException('missing game id'));

    $command = new BackfillFeed($auth);
    $command->setLaravel(app());
    attachFeedRatingsCommandOutput($command);
    setFeedRatingsCommandProperty($command, 'cutoffDate', Carbon::parse('2026-01-01 00:00:00'));
    setFeedRatingsCommandProperty($command, 'latestEventPerGame', []);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/my-feed?filter=posts&format=json')
        ->andReturn(feedRatingsCommandResponse([
            'content' => <<<'HTML'
<div class="event_row">
    <span class="like_btn" data-like_url="https://itch.io/post/7001/like"></span>
    <a class="event_time" title="2026-01-02 12:00:00">new</a>
    <div class="object_short_summary"><a href="https://dev.itch.io/from-summary">Summary Game</a></div>
</div>
<div class="event_row">
    <span class="like_btn" data-like_url="https://itch.io/post/7002/like"></span>
    <a class="event_time" title="2026-01-02 12:00:00">new</a>
    <div class="object_short_summary"><a href="https://dev.itch.io/unresolvable">Missing Game</a></div>
</div>
HTML,
        ]));

    expect(invokeFeedRatingsCommandMethod($command, 'processFeedPage', [$client, null]))
        ->toBe([null, false])
        ->and(ProcessedEvent::query()->where('event_id', 7001)->exists())->toBeFalse()
        ->and($game->fresh()->error)->toBeNull();
});
