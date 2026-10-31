<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\GameDataSyncService;
use App\Services\GameDelistingService;
use App\Services\ItchGameMetadataRefresher;
use App\Services\ItchHttpClientService;
use App\Services\MeilisearchService;
use App\Support\DelistedReason;
use GuzzleHttp\Psr7\Response;

function delistingClient(array $responses): void
{
    $client = Mockery::mock(ItchHttpClientService::class);
    foreach ($responses as [$arguments, $response]) {
        $client->shouldReceive('get')->once()->with(...$arguments)->andReturn($response);
    }
    app()->instance(ItchHttpClientService::class, $client);
}

function delistableGame(array $attributes = []): Game
{
    return Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 777,
        'url' => ['itch_io' => 'https://creator.itch.io/lost'],
        'is_visible' => true,
        'is_paid' => false,
        ...$attributes,
    ]);
}

it('classifies an unreachable game by its store page', function (int $pageStatus, string $pageBody, ?DelistedReason $expected) {
    $game = delistableGame();
    delistingClient([[['https://creator.itch.io/lost', [], true], new Response($pageStatus, [], $pageBody)]]);

    $stop = app(GameDelistingService::class)->recordUploadsResponse($game, 400, ['errors' => ['invalid game']]);

    expect($stop)->toBeTrue()
        ->and($game->refresh()->delisted_reason)->toBe($expected)
        ->and($game->is_visible)->toBeTrue();
})->with([
    'removed page' => [404, '', DelistedReason::PageRemoved],
    'removed page through FlareSolverr' => [200, '<div id="not_found_game_1659297"></div>', DelistedReason::PageRemoved],
    'unpublished page' => [200, '<div id="game_not_published_error_4419"></div>', DelistedReason::PageRemoved],
    'takedown notice' => [200, '<a href="https://itch.io/takedowns/2687311">View the notice</a>', DelistedReason::Blocked],
    'password form' => [200, '<div class="game_password_page page_widget"></div>', DelistedReason::PasswordProtected],
    'normal page' => [200, '<div class="view_game_page page_widget"></div>', null],
]);

it('delists a game with no downloads and keeps it delisted once uploads return', function () {
    $game = delistableGame();
    GameVersion::factory()->create(['game_id' => $game->id, 'version' => '1.0']);
    $delisting = app(GameDelistingService::class);

    expect($delisting->recordUploadsResponse($game, 200, ['uploads' => []]))->toBeTrue()
        ->and($game->refresh()->delisted_reason)->toBe(DelistedReason::NoDownloads)
        ->and($game->delisted_at)->not->toBeNull();

    expect($delisting->recordUploadsResponse($game, 200, ['uploads' => [['id' => 1]]]))->toBeFalse()
        ->and($game->refresh()->delisted_reason)->toBe(DelistedReason::NoDownloads);
});

it('does not delist a game that never had downloads', function () {
    $delisting = app(GameDelistingService::class);
    $new = delistableGame();
    $fallbackOnly = delistableGame(['itch_id' => 778, 'url' => ['itch_io' => 'https://creator.itch.io/soon']]);
    GameVersion::factory()->create(['game_id' => $fallbackOnly->id, 'version' => 'Unknown']);

    expect($delisting->recordUploadsResponse($new, 200, ['uploads' => []]))->toBeFalse()
        ->and($delisting->recordUploadsResponse($fallbackOnly, 200, ['uploads' => []]))->toBeFalse()
        ->and($new->refresh()->delisted_at)->toBeNull()
        ->and($fallbackOnly->refresh()->delisted_at)->toBeNull();
});

it('keeps the original delisting date when the reason changes', function () {
    $game = delistableGame(['delisted_at' => '2026-03-01 00:00:00', 'delisted_reason' => DelistedReason::NoDownloads]);
    delistingClient([[['https://creator.itch.io/lost', [], true], new Response(404)]]);

    app(GameDelistingService::class)->recordUploadsResponse($game, 404, null);

    expect($game->refresh()->delisted_reason)->toBe(DelistedReason::PageRemoved)
        ->and($game->delisted_at->toDateString())->toBe('2026-03-01');
});

it('never delists on a server error', function () {
    $game = delistableGame();

    expect(app(GameDelistingService::class)->recordUploadsResponse($game, 503, null))->toBeFalse()
        ->and($game->refresh()->delisted_at)->toBeNull();
});

it('delists a game and keeps it visible when a version refresh finds the page removed', function () {
    $game = delistableGame();
    delistingClient([
        [['https://api.itch.io/games/777/uploads'], new Response(400, [], '{"errors":["invalid game"]}')],
        [['https://creator.itch.io/lost', [], true], new Response(404)],
    ]);

    app(GameDataSyncService::class)->refreshVersion($game);

    expect($game->refresh()->is_visible)->toBeTrue()
        ->and($game->delisted_reason)->toBe(DelistedReason::PageRemoved)
        ->and($game->gameVersions()->exists())->toBeFalse();
});

it('excludes delisted games from default searches and shows only them on opt-in', function () {
    $listed = delistableGame(['itch_id' => 1, 'name' => 'Listed']);
    $lost = delistableGame(['itch_id' => 2, 'name' => 'Lost', 'delisted_at' => now(), 'delisted_reason' => DelistedReason::PageRemoved]);
    $search = app(MeilisearchService::class);

    expect(collect($search->searchGames('', ['is_visible' => true])->items())->pluck('id')->all())->toBe([$listed->id])
        ->and(collect($search->searchGames('', ['is_visible' => true, 'is_delisted' => true])->items())->pluck('id')->all())->toBe([$lost->id])
        ->and(Game::listed()->pluck('id')->all())->toBe([$listed->id])
        ->and(Game::visible()->pluck('id')->all())->toEqualCanonicalizing([$listed->id, $lost->id]);
});

it('lets users list delisted games but not track their updates', function () {
    $user = User::factory()->create();
    $game = delistableGame(['delisted_at' => now(), 'delisted_reason' => DelistedReason::PasswordProtected]);
    $reading = $user->vnLists()->where('type', 'reading')->where('is_default', true)->first()
        ?? VnList::factory()->for($user)->create(['name' => 'Reading', 'type' => 'reading', 'is_default' => true]);

    $this->actingAs($user)->postJson(route('api.games.add-to-list', $game), ['list_type' => 'reading'])
        ->assertOk()
        ->assertJsonPath('action', 'added');
    $this->actingAs($user)->patchJson(route('api.user-progress.toggle-updates', $game), ['is_receiving_updates' => true])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Notifications are not available for delisted games.');

    expect(VnListEntry::where('vn_list_id', $reading->id)->where('game_id', $game->id)->exists())->toBeTrue()
        ->and(UserGameProgress::where('user_id', $user->id)->where('game_id', $game->id)->value('is_receiving_updates'))->not->toBeTrue();
});

it('rejects update tracking for delisted games through the user API', function () {
    $user = User::factory()->create();
    $game = delistableGame(['delisted_at' => now(), 'delisted_reason' => DelistedReason::NoDownloads]);
    $token = $user->createToken('test-user-api', ['user-api', 'tracking:write'], now()->addDay())->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/me/tracked-games/{$game->id}", ['is_receiving_updates' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_receiving_updates' => 'Updates are unavailable for delisted games.']);
    $this->withToken($token)->patchJson("/api/v1/me/tracked-games/{$game->id}", ['status' => 'completed'])
        ->assertCreated();
});

it('delists a taken-down game during the daily metadata refresh', function () {
    $game = delistableGame();
    delistingClient([[['https://creator.itch.io/lost', [], true], new Response(200, [], '<a href="https://itch.io/takedowns/777">View the notice</a>')]]);

    app(GameDataSyncService::class)->refreshMetadata($game);

    expect($game->refresh()->delisted_reason)->toBe(DelistedReason::Blocked)
        ->and(app(GameDelistingService::class)->recordUploadsResponse($game, 200, ['uploads' => [['id' => 5]]]))->toBeFalse()
        ->and($game->refresh()->delisted_reason)->toBe(DelistedReason::Blocked);
});

it('keeps a delisting when the daily metadata refresh finds the page again', function () {
    $game = delistableGame(['delisted_at' => now(), 'delisted_reason' => DelistedReason::PasswordProtected]);
    delistingClient([[['https://creator.itch.io/lost', [], true], new Response(200, [], '<div class="view_game_page"></div>')]]);
    $this->mock(ItchGameMetadataRefresher::class)->shouldReceive('refresh')->once();

    app(GameDataSyncService::class)->refreshMetadata($game);

    expect($game->refresh()->delisted_reason)->toBe(DelistedReason::PasswordProtected);
});
