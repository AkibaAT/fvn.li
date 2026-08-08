<?php

declare(strict_types=1);

use App\Models\NotificationQueue;
use App\Models\PushSubscription;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

it('returns per-channel health with Discord deliverability and bot heartbeat state', function () {
    config(['services.discord.client_id' => '123456789']);
    $user = User::factory()->create();
    SocialAccount::factory()->discord()->for($user)->create(['provider_id' => '123456789012345678']);
    $user->notificationPreferences()->create([
        'browser_notifications_enabled' => false,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'weekly',
        'discord_dm_status' => 'deliverable',
        'discord_dm_verified_at' => now(),
    ]);
    Cache::put('discord-bot:status', ['status' => 'ok', 'received_at' => now()->toISOString()], 600);

    $this->actingAs($user)->getJson(route('browser-api.dashboard.notification-health.show'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('health.browser.enabled', false)
        ->assertJsonPath('health.discord.enabled', true)
        ->assertJsonPath('health.discord.linked', true)
        ->assertJsonPath('health.discord.dmStatus', 'deliverable')
        ->assertJsonPath('health.discord.botOnline', true)
        ->assertJsonPath('health.digest.frequency', 'weekly')
        ->assertJsonPath('health.discord.userInstallUrl', route('dashboard.discord.user-install'));
});

it('queues a Discord test and resets deliverability to unverified', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->discord()->for($user)->create(['provider_id' => '123456789012345678']);
    $preferences = $user->notificationPreferences()->create([
        'browser_notifications_enabled' => false,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'asap',
        'discord_dm_status' => 'undeliverable',
        'discord_dm_status_reason' => 'cannot_dm',
    ]);

    $this->actingAs($user)->postJson(route('browser-api.dashboard.notification-health.test'), ['channel' => 'discord'])
        ->assertStatus(202)
        ->assertJsonPath('success', true);

    $notification = NotificationQueue::firstOrFail();
    expect($notification->game_id)->toBeNull()
        ->and($notification->game_version_id)->toBeNull()
        ->and($notification->payload)->toBe(['type' => 'test'])
        ->and($preferences->fresh()->discord_dm_status)->toBe('unverified');
});

it('sends a browser test synchronously through the push service', function () {
    $user = User::factory()->create();
    PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => 'https://push.example/test',
        'p256dh' => 'key',
        'auth' => 'auth',
        'subscription_data' => [],
    ]);
    $service = Mockery::mock(NotificationService::class);
    $service->shouldReceive('sendPushNotifications')
        ->once()
        ->withArgs(fn (Collection $subscriptions, array $payload): bool => $subscriptions->count() === 1 && $payload['data']['type'] === 'test')
        ->andReturn(['sent' => 1, 'failed' => 0, 'pruned' => 0, 'errors' => []]);
    app()->instance(NotificationService::class, $service);

    $this->actingAs($user)->postJson(route('browser-api.dashboard.notification-health.test'), ['channel' => 'browser'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('result.sent', 1);
});

it('reports a browser test action when no subscription exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('browser-api.dashboard.notification-health.test'), ['channel' => 'browser'])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'no_subscriptions');
});

it('does not retry a browser test against a rejected subscription', function () {
    $user = User::factory()->create();
    PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => 'https://push.example/rejected',
        'p256dh' => 'key',
        'auth' => 'auth',
        'subscription_data' => [],
        'delivery_status' => PushSubscription::STATUS_INVALID,
    ]);

    $this->actingAs($user)->postJson(route('browser-api.dashboard.notification-health.test'), ['channel' => 'browser'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'no_subscriptions');
});
