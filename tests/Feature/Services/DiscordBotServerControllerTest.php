<?php

declare(strict_types=1);

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Game::unsetEventDispatcher();

    $this->user = User::factory()->create();
    $this->botToken = $this->user->createToken('discord-bot-test', ['discord-bot'])->plainTextToken;
    $this->server = DiscordServer::factory()->create([
        'discord_server_id' => '99999999',
        'owner_user_id' => $this->user->id,
    ]);
    $this->game = Game::factory()->create();

    $this->server->config()->create([
        'discord_server_id' => $this->server->id,
        'notification_channel_id' => '111111111',
        'notification_format' => 'compact',
    ]);
});

describe('Bot server endpoints', function () {
    test('pending notifications returns queued items', function () {
        DiscordNotificationHistory::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'notification_type' => 'update',
            'channel_id' => '111111111',
            'delivery_status' => 'pending',
            'payload' => ['content' => 'Test notification'],
        ]);

        $response = $this->withToken($this->botToken)
            ->getJson('/api/bot/servers/pending-notifications?limit=10');

        $response->assertStatus(200);
        $data = $response->json();
        expect($data['notifications'])->toHaveCount(1)
            ->and($data['notifications'][0]['payload']['content'])->toBe('Test notification')
            ->and($data['batch_key'])->not->toBeEmpty()
            ->and(DiscordNotificationHistory::first()->batch_key)->toBe($data['batch_key']);
    });

    test('mark delivered updates notification', function () {
        $notification = DiscordNotificationHistory::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'notification_type' => 'update',
            'channel_id' => '111111111',
            'delivery_status' => 'processing',
            'batch_key' => 'delivery-batch',
        ]);

        $response = $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/delivered", [
                'message_id' => '987654321',
                'batch_key' => 'delivery-batch',
            ]);

        $response->assertStatus(200);
        expect($notification->fresh()->delivery_status)->toBe('sent')
            ->and($notification->fresh()->message_id)->toBe('987654321');
    });

    test('mark delivered enforces the batch and preserves an existing message id when omitted', function () {
        $notification = DiscordNotificationHistory::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'notification_type' => 'update',
            'channel_id' => '111111111',
            'message_id' => 'existing-message',
            'delivery_status' => 'processing',
            'batch_key' => 'expected-batch',
        ]);

        $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/delivered", ['batch_key' => 'wrong-batch'])
            ->assertConflict();
        expect($notification->fresh()->delivery_status)->toBe('processing');

        $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/delivered", ['batch_key' => 'expected-batch'])
            ->assertOk();
        expect($notification->fresh()->delivery_status)->toBe('sent')
            ->and($notification->fresh()->message_id)->toBe('existing-message');
    });

    test('mark delivered records the canonical catalog message and payload hash', function () {
        $notification = DiscordNotificationHistory::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'notification_type' => 'new_game',
            'delivery_mode' => 'edit',
            'payload_hash' => str_repeat('a', 64),
            'message_id' => 'old-message',
            'channel_id' => '111111111',
            'delivery_status' => 'processing',
            'batch_key' => 'catalog-batch',
        ]);

        $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/delivered", [
                'message_id' => 'replacement-message',
                'batch_key' => 'catalog-batch',
            ])
            ->assertOk();

        $metadata = DB::table('discord_server_games')
            ->where('discord_server_id', $this->server->id)
            ->where('game_id', $this->game->id)
            ->first();
        expect($metadata->discord_channel_id)->toBe('111111111')
            ->and($metadata->discord_message_id)->toBe('replacement-message')
            ->and($metadata->discord_payload_hash)->toBe(str_repeat('a', 64));
    });

    test('mark failed updates notification', function () {
        $notification = DiscordNotificationHistory::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'notification_type' => 'update',
            'channel_id' => '111111111',
            'delivery_status' => 'processing',
            'batch_key' => 'failure-batch',
        ]);

        $response = $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/failed", [
                'error_message' => 'Channel not found',
                'batch_key' => 'failure-batch',
            ]);

        $response->assertStatus(200);
        expect($notification->fresh()->delivery_status)->toBe('failed')
            ->and($notification->fresh()->error_message)->toBe('Channel not found');
    });

    test('mark failed requeues retryable work until attempts are exhausted', function () {
        $notification = DiscordNotificationHistory::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'notification_type' => 'update',
            'channel_id' => '111111111',
            'delivery_status' => 'processing',
            'batch_key' => 'retry-batch',
            'attempts' => 0,
        ]);

        $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/failed", [
                'batch_key' => 'retry-batch',
                'retryable' => true,
                'error_message' => 'Temporary outage',
            ])->assertOk();
        expect($notification->fresh()->delivery_status)->toBe('pending')
            ->and($notification->fresh()->attempts)->toBe(1);

        $notification->update(['delivery_status' => 'processing', 'batch_key' => 'final-batch', 'attempts' => 2]);
        $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/failed", [
                'batch_key' => 'final-batch',
                'retryable' => true,
                'error_message' => 'Still down',
            ])->assertOk();
        expect($notification->fresh()->delivery_status)->toBe('failed')
            ->and($notification->fresh()->attempts)->toBe(3);
    });

    test('sync channels updates server', function () {
        $channels = [
            ['id' => '111', 'name' => 'general', 'type' => 0],
            ['id' => '222', 'name' => 'updates', 'type' => 0],
        ];

        $response = $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/sync-channels', [
                'discord_server_id' => '99999999',
                'channels' => $channels,
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        expect($data['count'])->toBe(2)
            ->and($this->server->fresh()->available_channels)->toHaveCount(2);
    });

    test('sync channels returns 404 for unknown server', function () {
        $response = $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/sync-channels', [
                'discord_server_id' => 'nonexistent',
                'channels' => [['id' => '123', 'name' => 'general', 'type' => 0]],
            ]);

        $response->assertStatus(404);
    });

    test('bot joined registers new server', function () {
        $owner = User::factory()->create();
        SocialAccount::factory()->discord()->create([
            'user_id' => $owner->id,
            'provider_id' => '55555555',
        ]);

        $response = $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/bot-joined', [
                'discord_server_id' => '88888888',
                'discord_server_name' => 'New Server',
                'owner_discord_id' => '55555555',
                'channels' => [['id' => '333', 'name' => 'general', 'type' => 0]],
            ]);

        $response->assertStatus(200);
        expect($response->json('message'))->toBe('Server registration updated');
        $server = DiscordServer::where('discord_server_id', '88888888')->first();
        expect($server->discord_server_name)->toBe('New Server')
            ->and($server->is_active)->toBeTrue()
            ->and($server->owner_user_id)->toBe($owner->id);
    });

    test('reconcile guilds makes the supplied state authoritative', function () {
        $staleServer = DiscordServer::factory()->create([
            'discord_server_id' => 'stale-server',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/reconcile-guilds', [
                'guilds' => [[
                    'discord_server_id' => 'current-server',
                    'discord_server_name' => 'Current Server',
                    'channels' => [['id' => '333', 'name' => 'general', 'type' => 0]],
                ]],
            ]);

        $response->assertOk()->assertJsonPath('count', 1);
        expect($staleServer->fresh()->is_active)->toBeFalse();

        $currentServer = DiscordServer::where('discord_server_id', 'current-server')->firstOrFail();
        expect($currentServer->is_active)->toBeTrue()
            ->and($currentServer->available_channels)->toHaveCount(1)
            ->and($currentServer->config)->not->toBeNull();
    });

    test('an empty guild reconciliation snapshot is a no-op', function () {
        $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/reconcile-guilds', ['guilds' => []])
            ->assertOk()
            ->assertJsonPath('count', 0);

        expect($this->server->fresh()->is_active)->toBeTrue();
    });

    test('bot left marks server inactive', function () {
        $response = $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/99999999/bot-left');

        $response->assertStatus(200);
        expect($this->server->fresh()->is_active)->toBeFalse();
    });

    test('bot left handles nonexistent server gracefully', function () {
        $response = $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/nonexistent/bot-left');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Server marked as inactive']);
    });

    test('the retired member-sync endpoint is unavailable', function () {
        $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/sync-members', [
                'discord_server_id' => '99999999',
                'members' => [],
            ])
            ->assertNotFound();
    });

    test('unauthenticated requests are rejected', function () {
        $response = $this->getJson('/api/bot/servers/pending-notifications?limit=10');
        $response->assertStatus(401);
    });

    test('tokens without the discord-bot ability are rejected', function () {
        $token = $this->user->createToken('profile-test', ['profile'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/bot/servers/pending-notifications')
            ->assertForbidden();
    });

    test('server bot endpoints reject tokens without the discord-bot ability', function () {
        $token = $this->user->createToken('wrong-ability', ['discord-notifications'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/bot/servers/pending-notifications')
            ->assertForbidden();
    });
});
