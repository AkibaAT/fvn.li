<?php

declare(strict_types=1);

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
            ->and($data['notifications'][0]['payload']['content'])->toBe('Test notification');
    });

    test('mark delivered updates notification', function () {
        $notification = DiscordNotificationHistory::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'notification_type' => 'update',
            'channel_id' => '111111111',
            'delivery_status' => 'processing',
        ]);

        $response = $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/delivered", [
                'message_id' => '987654321',
            ]);

        $response->assertStatus(200);
        expect($notification->fresh()->delivery_status)->toBe('sent')
            ->and($notification->fresh()->message_id)->toBe('987654321');
    });

    test('mark failed updates notification', function () {
        $notification = DiscordNotificationHistory::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'notification_type' => 'update',
            'channel_id' => '111111111',
            'delivery_status' => 'processing',
        ]);

        $response = $this->withToken($this->botToken)
            ->postJson("/api/bot/servers/notifications/{$notification->id}/failed", [
                'error_message' => 'Channel not found',
            ]);

        $response->assertStatus(200);
        expect($notification->fresh()->delivery_status)->toBe('failed')
            ->and($notification->fresh()->error_message)->toBe('Channel not found');
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

    test('sync members updates member records', function () {
        $linkedUser = User::factory()->create();
        SocialAccount::factory()->discord()->create([
            'user_id' => $linkedUser->id,
            'provider_id' => '111',
        ]);

        $response = $this->withToken($this->botToken)
            ->postJson('/api/bot/servers/sync-members', [
                'discord_server_id' => '99999999',
                'members' => [
                    ['discord_user_id' => '111', 'discord_username' => 'User1', 'is_admin' => true],
                    ['discord_user_id' => '222', 'discord_username' => 'User2', 'is_admin' => false],
                ],
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        expect($data['count'])->toBe(2)
            ->and($this->server->members()->where('discord_user_id', '111')->value('user_id'))->toBe($linkedUser->id);
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
});
