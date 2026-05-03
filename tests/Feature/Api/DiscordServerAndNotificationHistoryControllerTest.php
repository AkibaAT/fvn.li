<?php

declare(strict_types=1);

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\DiscordServerConfig;
use App\Models\Game;
use App\Models\GameDiscordSubscription;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function discordApiOwnerWithServer(array $serverAttributes = []): array
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $server = DiscordServer::create($serverAttributes + [
        'discord_server_id' => 'guild-api-'.uniqid(),
        'discord_server_name' => 'API Guild',
        'owner_user_id' => $user->id,
        'is_active' => true,
    ]);

    return [$user, $server];
}

function discordHistory(DiscordServer $server, Game $game, array $attributes = []): DiscordNotificationHistory
{
    return DiscordNotificationHistory::create($attributes + [
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'notification_type' => 'update',
        'channel_id' => 'channel-1',
        'sent_at' => now(),
        'delivery_status' => 'pending',
    ]);
}

it('registers lists shows updates stats and deletes Discord servers', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/discord-servers/register', [
        'discord_server_id' => 'guild-created',
        'discord_server_name' => 'Created Guild',
    ])->assertCreated()
        ->assertJsonPath('message', 'Server registered successfully')
        ->assertJsonPath('server.config.notification_format', 'detailed');

    $server = DiscordServer::where('discord_server_id', 'guild-created')->firstOrFail();
    $game = Game::factory()->create();
    GameDiscordSubscription::create([
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'is_active' => true,
    ]);

    $this->getJson('/api/discord-servers')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('servers.0.discord_server_name', 'Created Guild');

    $this->getJson("/api/discord-servers/{$server->id}")
        ->assertOk()
        ->assertJsonPath('server.discord_server_id', 'guild-created');

    $this->postJson("/api/discord-servers/{$server->id}/config", [
        'notification_channel_id' => 'channel-2',
        'notification_format' => 'custom',
        'custom_template' => 'Updated {game_name}',
        'include_game_description' => true,
        'include_thumbnail' => true,
        'include_ratings' => false,
        'ping_role_id' => 'role-1',
    ])->assertOk()
        ->assertJsonPath('message', 'Configuration updated successfully')
        ->assertJsonPath('config.notification_channel_id', 'channel-2');

    $this->getJson("/api/discord-servers/{$server->id}/stats")
        ->assertOk()
        ->assertJsonPath('subscription_count', 1)
        ->assertJsonPath('is_configured', true);

    $this->deleteJson("/api/discord-servers/{$server->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Server unlinked successfully');

    expect(DiscordServer::whereKey($server->id)->exists())->toBeFalse();
});

it('filters shows summarizes resends tests and clears Discord notification history', function () {
    [, $server] = discordApiOwnerWithServer();
    DiscordServerConfig::create([
        'discord_server_id' => $server->id,
        'notification_channel_id' => 'channel-1',
        'notification_format' => 'detailed',
    ]);
    $game = Game::factory()->create(['name' => 'History Game']);
    $sent = discordHistory($server, $game, [
        'notification_type' => 'new_game',
        'delivery_status' => 'sent',
        'sent_at' => now()->subDays(2),
    ]);
    $failed = discordHistory($server, $game, [
        'delivery_status' => 'failed',
        'error_message' => 'Missing permissions',
        'sent_at' => now()->subDay(),
    ]);
    $old = discordHistory($server, $game, [
        'delivery_status' => 'sent',
        'sent_at' => now()->subDays(45),
    ]);

    $this->getJson("/api/discord-servers/{$server->id}/notifications?status=sent&type=new_game&from_date=".now()->subDays(3)->toDateString())
        ->assertOk()
        ->assertJsonPath('data.0.id', $sent->id)
        ->assertJsonPath('data.0.game.name', 'History Game');

    $this->getJson("/api/discord-servers/{$server->id}/notifications/{$failed->id}")
        ->assertOk()
        ->assertJsonPath('notification.id', $failed->id);

    $this->getJson("/api/discord-servers/{$server->id}/notifications-stats?days=30")
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('sent', 1)
        ->assertJsonPath('failed', 1)
        ->assertJsonPath('pending', 0);

    $this->postJson("/api/discord-servers/{$server->id}/notifications/{$sent->id}/resend")
        ->assertBadRequest()
        ->assertJsonPath('message', 'Only failed notifications can be resent');

    $this->postJson("/api/discord-servers/{$server->id}/notifications/{$failed->id}/resend")
        ->assertOk()
        ->assertJsonPath('message', 'Notification queued for resend')
        ->assertJsonPath('notification.delivery_status', 'pending')
        ->assertJsonPath('notification.error_message', null);

    $this->postJson("/api/discord-servers/{$server->id}/test-notification")
        ->assertCreated()
        ->assertJsonPath('message', 'Test notification queued')
        ->assertJsonPath('notification.game_id', null);

    $this->deleteJson("/api/discord-servers/{$server->id}/notifications/clear?days=30")
        ->assertOk()
        ->assertJsonPath('message', 'Notification history cleared')
        ->assertJsonPath('deleted_count', 1);

    expect(DiscordNotificationHistory::whereKey($old->id)->exists())->toBeFalse();
});

it('blocks access to notification history records from another server and unconfigured test sends', function () {
    [$user, $server] = discordApiOwnerWithServer();
    $otherServer = DiscordServer::create([
        'discord_server_id' => 'guild-other-'.uniqid(),
        'discord_server_name' => 'Other Guild',
        'owner_user_id' => $user->id,
        'is_active' => true,
    ]);
    $game = Game::factory()->create();
    $notification = discordHistory($otherServer, $game);

    $this->getJson("/api/discord-servers/{$server->id}/notifications/{$notification->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Unauthorized');

    $this->postJson("/api/discord-servers/{$server->id}/test-notification")
        ->assertBadRequest()
        ->assertJsonPath('message', 'Server is not configured with a notification channel');
});
