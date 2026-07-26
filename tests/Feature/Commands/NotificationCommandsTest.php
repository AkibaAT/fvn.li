<?php

declare(strict_types=1);

use App\Models\DiscordChannelAnnouncement;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\PushSubscription;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

function queueCommandGame(array $versionAttributes = [], array $gameAttributes = []): array
{
    $game = Game::factory()->create(array_merge([
        'name' => 'Command VN',
        'is_paid' => false,
        'is_visible' => true,
    ], $gameAttributes));
    $version = GameVersion::factory()->create(array_merge([
        'game_id' => $game->id,
        'version' => '3.0',
        'published_at' => now()->subHour(),
    ], $versionAttributes));
    $version->forceFill(['is_latest' => true])->save();

    return [$game, $version];
}

function notificationUserFor(Game $game, array $preferences = [], bool $withDiscord = false): User
{
    $user = User::factory()->create();
    $user->notificationPreferences()->create(array_merge([
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => false,
        'notification_digest' => 'asap',
    ], $preferences));
    UserGameProgress::factory()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        // Pinned to the game under test: any other version would carry a random
        // publication date and could land inside the command's recency window.
        'game_version_id' => $game->latestVersion?->id,
        'receive_updates' => true,
    ]);

    if ($withDiscord) {
        SocialAccount::factory()->discord()->create(['user_id' => $user->id]);
    }

    return $user;
}

function pushSubscriptionFor(User $user, string $endpoint = 'https://push.example/endpoint'): PushSubscription
{
    return PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => $endpoint,
        'p256dh' => 'public-key',
        'auth' => 'auth-token',
        'subscription_data' => ['endpoint' => $endpoint],
    ]);
}

it('exits queue command early when there are no recent free game updates', function () {
    Game::factory()->create(['is_paid' => false]);

    $this->artisan('notifications:queue-game-updates --days=1')
        ->expectsOutput('No recently updated games found, skipping notification processing')
        ->assertExitCode(0);

    expect(NotificationQueue::query()->count())->toBe(0);
});

it('does not queue notifications or channel announcements for hidden games', function () {
    [$game] = queueCommandGame(gameAttributes: ['is_visible' => false]);
    notificationUserFor($game);

    $this->artisan('notifications:queue-game-updates --days=1')
        ->expectsOutput('No recently updated games found, skipping notification processing')
        ->assertExitCode(0);

    expect(NotificationQueue::query()->count())->toBe(0)
        ->and(DiscordChannelAnnouncement::query()->count())->toBe(0);
});

it('queues browser and Discord notifications for eligible users', function () {
    [$game, $version] = queueCommandGame();
    notificationUserFor($game);
    notificationUserFor($game, [
        'browser_notifications_enabled' => false,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'daily',
    ], withDiscord: true);
    notificationUserFor($game, [
        'browser_notifications_enabled' => false,
        'discord_notifications_enabled' => true,
    ], withDiscord: false);

    $this->artisan('notifications:queue-game-updates --days=1 --limit=5')
        ->expectsOutput('Found 1 recently updated games')
        ->expectsOutput('Successfully queued 2 notifications')
        ->assertExitCode(0);

    $notifications = NotificationQueue::query()->orderBy('channel')->get();
    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('channel')->all())->toBe(['browser', 'discord'])
        ->and($notifications->pluck('game_version_id')->unique()->all())->toBe([$version->id])
        ->and($notifications->firstWhere('channel', 'browser')->scheduled_at->lte(now()))->toBeTrue()
        ->and($notifications->firstWhere('channel', 'discord')->scheduled_at->isFuture())->toBeTrue();
});

it('does not queue duplicate notifications for already notified users', function () {
    [$game, $version] = queueCommandGame();
    $user = notificationUserFor($game);
    NotificationHistory::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'browser',
        'success' => true,
        'meta_data' => ['digest' => false],
    ]);

    $this->artisan('notifications:queue-game-updates --days=1')
        ->expectsOutput('Successfully queued 0 notifications')
        ->assertExitCode(0);
});

it('processes individual browser push notifications and records history', function () {
    $user = User::factory()->create();
    pushSubscriptionFor($user);
    [$game, $version] = queueCommandGame();
    $notification = NotificationQueue::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'channel' => 'browser',
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
        'payload' => ['title' => 'Update'],
    ]);

    $service = Mockery::mock(NotificationService::class);
    $service->shouldReceive('sendPushNotifications')
        ->once()
        ->withArgs(fn (Collection $subscriptions, array $payload) => $subscriptions->count() === 1 && $payload['title'] === 'Update')
        ->andReturnTrue();
    app()->instance(NotificationService::class, $service);

    $this->artisan('notifications:process-push --limit=10 --batch=5')
        ->expectsOutput('Found 1 notifications to process')
        ->expectsOutput('Success: 1, Failed: 0')
        ->assertExitCode(0);

    $notification->refresh();
    expect($notification->status)->toBe('sent')
        ->and($notification->processed_at)->not->toBeNull()
        ->and(NotificationHistory::query()->first()->meta_data)->toBe(['digest' => false]);
});

it('marks browser notifications failed when users have no push subscriptions', function () {
    $user = User::factory()->create();
    [$game, $version] = queueCommandGame();
    $notification = NotificationQueue::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'channel' => 'browser',
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
        'payload' => ['title' => 'Update'],
    ]);

    $this->artisan('notifications:process-push')
        ->expectsOutput('Success: 0, Failed: 1')
        ->assertExitCode(0);

    $notification->refresh();
    expect($notification->status)->toBe('failed')
        ->and($notification->error)->toBe('No valid push subscriptions found');
});

it('combines daily digest browser notifications for a user', function () {
    $user = User::factory()->create();
    $user->notificationPreferences()->create([
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => false,
        'notification_digest' => 'daily',
    ]);
    pushSubscriptionFor($user);
    [$firstGame, $firstVersion] = queueCommandGame();
    [$secondGame, $secondVersion] = queueCommandGame(['version' => '4.0']);

    foreach ([[$firstGame, $firstVersion], [$secondGame, $secondVersion]] as [$game, $version]) {
        NotificationQueue::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'game_version_id' => $version->id,
            'channel' => 'browser',
            'status' => 'pending',
            'scheduled_at' => now()->subMinute(),
            'payload' => ['title' => 'Update'],
        ]);
    }

    $service = Mockery::mock(NotificationService::class);
    $service->shouldReceive('sendPushNotifications')
        ->once()
        ->withArgs(fn (Collection $subscriptions, array $payload) => $subscriptions->count() === 1
            && $payload['title'] === 'Daily Game Updates'
            && count($payload['data']['games']) === 2)
        ->andReturnTrue();
    app()->instance(NotificationService::class, $service);

    $this->artisan('notifications:process-push')
        ->expectsOutput('Success: 2, Failed: 0')
        ->assertExitCode(0);

    expect(NotificationQueue::query()->where('status', 'sent')->count())->toBe(2)
        ->and(NotificationHistory::query()->count())->toBe(2)
        ->and(NotificationHistory::query()->first()->meta_data)->toBe(['digest' => true, 'digest_type' => 'daily']);
});
