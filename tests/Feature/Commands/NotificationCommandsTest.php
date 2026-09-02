<?php

declare(strict_types=1);

use App\Exceptions\WebPushConfigurationException;
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
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    if ((bool) $user->notificationPreferences->browser_notifications_enabled) {
        pushSubscriptionFor($user, "https://93.184.216.34/{$user->id}");
    }

    return $user;
}

function pushSubscriptionFor(User $user, string $endpoint = 'https://93.184.216.34/endpoint'): PushSubscription
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

it('processes recent games in stable id order when limited', function () {
    [$firstGame] = queueCommandGame(gameAttributes: ['name' => 'First by id']);
    [$secondGame] = queueCommandGame(gameAttributes: ['name' => 'Second by id']);
    notificationUserFor($firstGame);
    notificationUserFor($secondGame);

    $this->artisan('notifications:queue-game-updates --days=3 --limit=1')->assertSuccessful();

    expect(NotificationQueue::pluck('game_id')->unique()->all())->toBe([$firstGame->id])
        ->and($firstGame->id)->toBeLessThan($secondGame->id);
});

it('is idempotent when the three-day overlap window is run again', function () {
    [$game] = queueCommandGame();
    notificationUserFor($game);

    $this->artisan('notifications:queue-game-updates --days=3')->assertSuccessful();
    $firstCount = NotificationQueue::count();
    $this->artisan('notifications:queue-game-updates --days=3')->assertSuccessful();

    expect($firstCount)->toBe(1)
        ->and(NotificationQueue::count())->toBe($firstCount);
});

it('schedules weekly notifications for the next Sunday at 09:00', function (string $now, string $expected) {
    Carbon::setTestNow($now);

    [$game] = queueCommandGame();
    notificationUserFor($game, [
        'browser_notifications_enabled' => true,
        'notification_digest' => 'weekly',
    ]);

    $this->artisan('notifications:queue-game-updates --days=1')->assertExitCode(0);

    expect(NotificationQueue::query()->firstOrFail()->scheduled_at->toDateTimeString())->toBe($expected);

    Carbon::setTestNow();
})->with([
    'Saturday' => ['2026-08-08 12:00:00', '2026-08-09 09:00:00'],
    'Sunday before delivery time' => ['2026-08-09 08:00:00', '2026-08-09 09:00:00'],
    'Sunday after delivery time' => ['2026-08-09 10:00:00', '2026-08-16 09:00:00'],
]);

it('queues both channels for a game update', function () {
    [$game] = queueCommandGame();
    notificationUserFor($game);
    notificationUserFor($game, [
        'browser_notifications_enabled' => false,
        'discord_notifications_enabled' => true,
    ], withDiscord: true);

    $this->artisan('notifications:queue-game-updates --days=1 --limit=5')
        ->expectsOutput('Successfully queued 2 notifications')
        ->assertExitCode(0);

    expect(NotificationQueue::query()->orderBy('channel')->pluck('channel')->all())->toBe(['browser', 'discord'])
        ->and(DiscordChannelAnnouncement::query()->count())->toBe(1);
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

it('ignores a notification that is already queued without creating another row', function () {
    [$game] = queueCommandGame();
    $user = notificationUserFor($game);

    $this->artisan('notifications:queue-game-updates --days=1')
        ->expectsOutput('Successfully queued 1 notifications')
        ->assertExitCode(0);

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->artisan('notifications:queue-game-updates --days=1')
        ->expectsOutput("Notification already queued for user {$user->id}, game {$game->name}, channel browser")
        ->expectsOutput('Successfully queued 0 notifications')
        ->assertExitCode(0);

    expect(NotificationQueue::query()->count())->toBe(1)
        ->and(collect($queries)->contains(
            fn (string $query): bool => str_contains($query, /** @lang text */ 'insert into "notification_queue"')
                && str_contains($query, 'on conflict do nothing')
        ))->toBeTrue();
});

it('deduplicates browser and Discord delivery history independently', function () {
    [$game, $version] = queueCommandGame();
    $browserDelivered = notificationUserFor($game, [
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => true,
    ], withDiscord: true);
    $discordDelivered = notificationUserFor($game, [
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => true,
    ], withDiscord: true);

    NotificationHistory::create([
        'user_id' => $browserDelivered->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'browser',
        'success' => true,
    ]);
    NotificationHistory::create([
        'user_id' => $discordDelivered->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'discord',
        'success' => true,
    ]);

    $this->artisan('notifications:queue-game-updates --days=3')->assertSuccessful();

    expect(NotificationQueue::where('user_id', $browserDelivered->id)->pluck('channel')->all())->toBe(['discord'])
        ->and(NotificationQueue::where('user_id', $discordDelivered->id)->pluck('channel')->all())->toBe(['browser']);
});

it('tolerates duplicate notification history records', function () {
    [$game, $version] = queueCommandGame();
    $user = User::factory()->create();
    $attributes = [
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'browser',
        'success' => true,
    ];

    $first = NotificationHistory::record($attributes);
    $second = NotificationHistory::record($attributes);

    expect($second->id)->toBe($first->id)
        ->and(NotificationHistory::count())->toBe(1);
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
    $service->shouldReceive('assertConfigured')->once();
    $service->shouldReceive('sendPushNotifications')
        ->once()
        ->withArgs(fn (Collection $subscriptions, array $payload) => $subscriptions->count() === 1 && $payload['title'] === 'Update')
        ->andReturn(['sent' => 1, 'failed' => 0, 'pruned' => 0, 'errors' => []]);
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

it('leaves browser notifications pending and unclaimed when users have no push subscriptions', function () {
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
        ->expectsOutput('Found 0 notifications to process')
        ->assertExitCode(0);

    $notification->refresh();
    expect($notification->status)->toBe('pending')
        ->and($notification->attempts)->toBe(0)
        ->and($notification->batch_key)->toBeNull();
});

it('does not queue new browser work until the user has a usable subscription', function () {
    [$game] = queueCommandGame();
    $user = notificationUserFor($game);
    $user->pushSubscriptions()->delete();

    $this->artisan('notifications:queue-game-updates --days=1')->assertSuccessful();

    expect(NotificationQueue::where('user_id', $user->id)->exists())->toBeFalse();
});

it('aborts before claiming when VAPID configuration is invalid', function () {
    $user = User::factory()->create();
    [$game, $version] = queueCommandGame();
    $notification = NotificationQueue::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'channel' => 'browser',
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
    ]);
    $service = Mockery::mock(NotificationService::class);
    $service->shouldReceive('assertConfigured')->once()->andThrow(new WebPushConfigurationException('Missing VAPID private_key'));
    $service->shouldNotReceive('sendPushNotifications');
    app()->instance(NotificationService::class, $service);

    $this->artisan('notifications:process-push')
        ->expectsOutput('Missing VAPID private_key')
        ->assertFailed();

    expect($notification->fresh()->status)->toBe('pending')
        ->and($notification->fresh()->attempts)->toBe(0)
        ->and($notification->fresh()->batch_key)->toBeNull();
});

it('backs off retryable browser failures and fails at the attempt limit', function () {
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
        'attempts' => 0,
        'payload' => ['title' => 'Update'],
    ]);
    $service = Mockery::mock(NotificationService::class);
    $service->shouldReceive('assertConfigured')->twice();
    $service->shouldReceive('sendPushNotifications')->twice()
        ->andReturn(['sent' => 0, 'failed' => 1, 'pruned' => 0, 'errors' => ['temporary outage']]);
    app()->instance(NotificationService::class, $service);

    $this->artisan('notifications:process-push')->assertSuccessful();
    expect($notification->fresh()->status)->toBe('pending')
        ->and($notification->fresh()->attempts)->toBe(1)
        ->and($notification->fresh()->scheduled_at->between(now()->addMinutes(14), now()->addMinutes(16)))->toBeTrue();

    NotificationQueue::whereKey($notification->id)->update([
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
        'attempts' => 2,
        'batch_key' => null,
    ]);
    $this->artisan('notifications:process-push')->assertSuccessful();
    expect($notification->fresh()->status)->toBe('failed')
        ->and($notification->fresh()->attempts)->toBe(3)
        ->and($notification->fresh()->processed_at)->not->toBeNull();
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
    $service->shouldReceive('assertConfigured')->once();
    $service->shouldReceive('sendPushNotifications')
        ->once()
        ->withArgs(fn (Collection $subscriptions, array $payload) => $subscriptions->count() === 1
            && $payload['title'] === 'Daily Game Updates'
            && count($payload['data']['games']) === 2)
        ->andReturn(['sent' => 1, 'failed' => 0, 'pruned' => 0, 'errors' => []]);
    app()->instance(NotificationService::class, $service);

    $this->artisan('notifications:process-push')
        ->expectsOutput('Success: 2, Failed: 0')
        ->assertExitCode(0);

    expect(NotificationQueue::query()->where('status', 'sent')->count())->toBe(2)
        ->and(NotificationHistory::query()->count())->toBe(2)
        ->and(NotificationHistory::query()->first()->meta_data)->toBe(['digest' => true, 'digest_type' => 'daily']);
});

it('uses digest formatting even when only one digest row is due', function () {
    $user = User::factory()->create();
    $user->notificationPreferences()->create([
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => false,
        'notification_digest' => 'daily',
    ]);
    pushSubscriptionFor($user);
    [$game, $version] = queueCommandGame();
    NotificationQueue::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'channel' => 'browser',
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
        'payload' => ['title' => 'Individual title'],
    ]);

    $service = Mockery::mock(NotificationService::class);
    $service->shouldReceive('assertConfigured')->once();
    $service->shouldReceive('sendPushNotifications')
        ->once()
        ->withArgs(fn (Collection $subscriptions, array $payload): bool => $payload['title'] === 'Daily Game Updates' && count($payload['data']['games']) === 1)
        ->andReturn(['sent' => 1, 'failed' => 0, 'pruned' => 0, 'errors' => []]);
    app()->instance(NotificationService::class, $service);

    $this->artisan('notifications:process-push')->assertSuccessful();
    expect(NotificationHistory::firstOrFail()->meta_data)->toBe(['digest' => true, 'digest_type' => 'daily']);
});
