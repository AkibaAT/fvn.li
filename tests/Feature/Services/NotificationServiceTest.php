<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

function createPushSubscription(User $user, string $endpoint = 'https://push.example/subscription'): PushSubscription
{
    return PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => $endpoint,
        'p256dh' => 'public-key',
        'auth' => 'auth-token',
        'subscription_data' => ['endpoint' => $endpoint],
    ]);
}

it('does not send direct or digest notifications without subscriptions', function () {
    $service = app(NotificationService::class);
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $version = GameVersion::factory()->create(['game_id' => $game->id]);

    expect($service->sendPushToUser($user, $game, $version))->toBeFalse()
        ->and($service->sendDigestToUser($user, collect([['game' => $game, 'version' => $version]]), 'daily'))->toBeFalse()
        ->and(NotificationHistory::query()->count())->toBe(0);
});

it('records browser notification history when a direct push succeeds', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['name' => 'Updated VN']);
    $version = GameVersion::factory()->create(['game_id' => $game->id, 'version' => '2.0']);
    createPushSubscription($user);

    $service = Mockery::mock(NotificationService::class)->makePartial();
    $service->shouldReceive('sendPushNotifications')
        ->once()
        ->withArgs(function (Collection $subscriptions, array $payload) use ($game, $version) {
            return $subscriptions->count() === 1
                && $payload['title'] === 'Updated VN - New Update Available'
                && $payload['data']['game_id'] === $game->id
                && $payload['data']['game_version_id'] === $version->id;
        })
        ->andReturnTrue();

    expect($service->sendPushToUser($user, $game, $version))->toBeTrue();

    $history = NotificationHistory::query()->first();
    expect($history)->not->toBeNull()
        ->and($history->user_id)->toBe($user->id)
        ->and($history->type)->toBe('browser')
        ->and($history->success)->toBeTrue()
        ->and($history->meta_data)->toBe(['digest' => false]);
});

it('does not record direct notification history when sending fails', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $version = GameVersion::factory()->create(['game_id' => $game->id]);
    createPushSubscription($user);

    $service = Mockery::mock(NotificationService::class)->makePartial();
    $service->shouldReceive('sendPushNotifications')->once()->andReturnFalse();

    expect($service->sendPushToUser($user, $game, $version))->toBeFalse()
        ->and(NotificationHistory::query()->count())->toBe(0);
});

it('records digest notification history for each game when a digest push succeeds', function () {
    $user = User::factory()->create();
    createPushSubscription($user);
    $firstGame = Game::factory()->create(['name' => 'First VN']);
    $firstVersion = GameVersion::factory()->create(['game_id' => $firstGame->id, 'version' => '1.1']);
    $secondGame = Game::factory()->create(['name' => 'Second VN']);
    $secondVersion = GameVersion::factory()->create(['game_id' => $secondGame->id, 'version' => '2.2']);

    $service = Mockery::mock(NotificationService::class)->makePartial();
    $service->shouldReceive('sendPushNotifications')
        ->once()
        ->withArgs(function (Collection $subscriptions, array $payload) {
            return $subscriptions->count() === 1
                && $payload['title'] === 'Weekly Game Updates'
                && $payload['body'] === '2 games you follow have been updated.'
                && count($payload['data']['games']) === 2;
        })
        ->andReturnTrue();

    $result = $service->sendDigestToUser($user, collect([
        ['game' => $firstGame, 'version' => $firstVersion],
        ['game' => $secondGame, 'version' => $secondVersion],
    ]), 'weekly');

    expect($result)->toBeTrue()
        ->and(NotificationHistory::query()->count())->toBe(2)
        ->and(NotificationHistory::query()->pluck('meta_data')->all())->toBe([
            ['digest' => true, 'digest_type' => 'weekly'],
            ['digest' => true, 'digest_type' => 'weekly'],
        ]);
});

it('returns false when low level push sending receives no subscriptions or catches an error', function () {
    $service = app(NotificationService::class);

    expect($service->sendPushNotifications(collect(), ['title' => 'Empty']))->toBeFalse();
});
