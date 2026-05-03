<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserGameProgress;
use Laravel\Sanctum\Sanctum;

function actAsNotificationApiUser(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['notifications']);

    return $user;
}

it('requires authentication and validates subscriber lookups', function () {
    $this->postJson('/api/notifications/subscribers')
        ->assertUnauthorized();

    actAsNotificationApiUser();

    $this->postJson('/api/notifications/subscribers')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['game_id', 'game_version_id', 'notification_type'], 'error');
});

it('requires a notification service token instead of a normal user session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/notifications/subscribers')
        ->assertUnauthorized();

    Sanctum::actingAs($user, ['profile']);

    $this->postJson('/api/notifications/subscribers')
        ->assertForbidden();
});

it('accepts a real bearer token with the notifications ability', function () {
    $token = User::factory()
        ->create()
        ->createToken('notifications-worker', ['notifications'])
        ->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/notifications/subscribers')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['game_id', 'game_version_id', 'notification_type'], 'error');
});

it('returns subscribers for browser discord telegram and email notification channels', function () {
    actAsNotificationApiUser();
    $game = Game::factory()->create(['name' => 'Notification VN']);
    $version = GameVersion::factory()->for($game)->latest()->create();

    $browserUser = User::factory()->create();
    $browserUser->notificationPreferences()->create([
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => false,
        'notification_digest' => 'asap',
    ]);
    UserGameProgress::factory()->for($browserUser)->for($game)->create([
        'game_version_id' => $version->id,
        'receive_updates' => true,
    ]);

    $discordUser = User::factory()->create();
    SocialAccount::factory()->discord()->for($discordUser)->create();
    $discordUser->notificationPreferences()->create([
        'browser_notifications_enabled' => false,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'daily',
    ]);
    UserGameProgress::factory()->for($discordUser)->for($game)->create([
        'game_version_id' => $version->id,
        'receive_updates' => true,
    ]);

    $telegramUser = User::factory()->create();
    SocialAccount::factory()->for($telegramUser)->create([
        'provider_name' => 'telegram',
        'provider_id' => 'telegram-42',
    ]);
    UserGameProgress::factory()->for($telegramUser)->for($game)->create([
        'game_version_id' => $version->id,
        'receive_updates' => true,
    ]);

    $emailUser = User::factory()->create(['email' => 'notify@example.com']);
    SocialAccount::factory()->for($emailUser)->create(['provider_name' => 'google']);
    UserGameProgress::factory()->for($emailUser)->for($game)->create([
        'game_version_id' => $version->id,
        'receive_updates' => true,
    ]);

    $mutedUser = User::factory()->create();
    $mutedUser->notificationPreferences()->create([
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'asap',
    ]);
    UserGameProgress::factory()->for($mutedUser)->for($game)->create([
        'game_version_id' => $version->id,
        'receive_updates' => false,
    ]);

    $payload = [
        'game_id' => $game->id,
        'game_version_id' => $version->id,
    ];

    $this->postJson('/api/notifications/subscribers', $payload + ['notification_type' => 'browser'])
        ->assertOk()
        ->assertJsonPath('user_ids.0', $browserUser->id)
        ->assertJsonPath('game.name', 'Notification VN');

    $this->postJson('/api/notifications/subscribers', $payload + ['notification_type' => 'discord'])
        ->assertOk()
        ->assertJsonPath('user_ids.0', $discordUser->id);

    $this->postJson('/api/notifications/subscribers', $payload + ['notification_type' => 'telegram'])
        ->assertOk()
        ->assertJsonPath('telegram_ids.0', 'telegram-42');

    $this->postJson('/api/notifications/subscribers', $payload + ['notification_type' => 'email'])
        ->assertOk()
        ->assertJsonPath('emails.0', 'notify@example.com');
});

it('records notification delivery history', function () {
    actAsNotificationApiUser();
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();

    $this->postJson('/api/notifications/record', [
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'browser',
        'success' => false,
        'meta_data' => ['reason' => 'expired'],
    ])->assertOk()
        ->assertJsonPath('message', 'Notification recorded successfully')
        ->assertJsonPath('notification.type', 'browser')
        ->assertJsonPath('notification.success', false);

    expect(NotificationHistory::query()->first()->meta_data)->toBe(['reason' => 'expired']);
});
