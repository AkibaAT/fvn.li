<?php

declare(strict_types=1);

use App\Models\DiscordServer;
use App\Models\DiscordServerConfig;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

describe('Discord guild filtering', function () {
    test('empty embed previews use the notification type default', function () {
        $user = User::factory()->createQuietly();
        $server = DiscordServer::factory()->create(['owner_user_id' => $user->id]);
        Game::factory()->create([
            'name' => 'Preview VN',
            'is_visible' => true,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('browser-api.discord.servers.preview-embed', ['server' => $server->id]),
            [
                'embed_template' => [],
                'notification_type' => 'update',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('embed.title', 'Preview VN')
            ->assertJsonPath('embed.footer.text', 'fvn.li');
    });

    test('test notifications queue a complete routable update payload', function () {
        $user = User::factory()->createQuietly();
        $server = DiscordServer::factory()->create(['owner_user_id' => $user->id]);
        DiscordServerConfig::create([
            'discord_server_id' => $server->id,
            'notification_channel_id' => 'channel-123',
            'notification_format' => 'detailed',
            'update_embed' => null,
        ]);
        $game = Game::factory()->create([
            'name' => 'Delivery Test VN',
            'slug' => 'delivery-test-vn',
            'is_visible' => true,
            'is_nsfw' => false,
            'thumb_url' => 'https://example.com/thumbnail.webp',
        ]);
        GameVersion::factory()->latest()->create([
            'game_id' => $game->id,
            'version' => '2.0',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('browser-api.discord.servers.test-notification', ['server' => $server->id]),
        );

        $response->assertOk()
            ->assertJsonPath('notification.notification_type', 'update')
            ->assertJsonPath('notification.channel_id', 'channel-123')
            ->assertJsonPath('notification.delivery_status', 'pending')
            ->assertJsonPath('notification.payload.embeds.0.title', 'Delivery Test VN')
            ->assertJsonPath('notification.payload.embeds.0.fields.0.value', '2.0')
            ->assertJsonPath('notification.payload.embeds.0.url', route('games.show', $game->slug));
    });

    test('guilds endpoint only returns servers the user can manage', function () {
        $user = User::factory()->createQuietly();

        $user->socialAccounts()->create([
            'provider_name' => 'discord',
            'provider_id' => 'discord-user-1',
            'token' => 'test-token',
            'provider_data' => [
                'guilds' => [],
            ],
        ]);

        Http::fake([
            'https://discord.com/api/v10/users/@me/guilds' => Http::response([
                [
                    'id' => 'owner-guild',
                    'name' => 'Owner Guild',
                    'owner' => true,
                    'permissions' => '0',
                ],
                [
                    'id' => 'admin-guild',
                    'name' => 'Admin Guild',
                    'owner' => false,
                    'permissions' => '8',
                ],
                [
                    'id' => 'manage-guild',
                    'name' => 'Manage Guild',
                    'owner' => false,
                    'permissions' => '32',
                ],
                [
                    'id' => 'member-guild',
                    'name' => 'Member Guild',
                    'owner' => false,
                    'permissions' => '2048',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson(route('browser-api.discord.guilds'));

        $response->assertOk();
        $response->assertJson([
            'has_discord' => true,
        ]);

        expect(collect($response->json('guilds'))->pluck('id')->all())
            ->toBe(['owner-guild', 'admin-guild', 'manage-guild']);
    });

    test('guilds endpoint records Discord managers as local server admins', function () {
        $user = User::factory()->createQuietly();
        $user->socialAccounts()->create([
            'provider_name' => 'discord',
            'provider_id' => 'discord-admin',
            'token' => 'test-token',
            'provider_data' => ['guilds' => []],
        ]);
        $server = DiscordServer::factory()->create([
            'discord_server_id' => 'managed-guild',
            'is_active' => true,
        ]);

        Http::fake([
            'https://discord.com/api/v10/users/@me/guilds' => Http::response([[
                'id' => 'managed-guild',
                'name' => 'Managed Guild',
                'owner' => false,
                'permissions' => '32',
            ]], 200),
        ]);

        $response = $this->actingAs($user)->getJson(route('browser-api.discord.guilds'));

        $response->assertOk()
            ->assertJsonPath('guilds.0.id', 'managed-guild')
            ->assertJsonPath('guilds.0.has_bot', true);
        $this->assertDatabaseHas('discord_server_members', [
            'discord_server_id' => $server->id,
            'discord_user_id' => 'discord-admin',
            'user_id' => $user->id,
            'is_admin' => true,
        ]);
    });

    test('roles endpoint fetches non-managed guild roles with the bot token', function () {
        $user = User::factory()->createQuietly();
        $server = DiscordServer::factory()->create([
            'owner_user_id' => $user->id,
            'discord_server_id' => 'guild-123',
        ]);

        Http::fake([
            'https://discord.com/api/v10/guilds/guild-123/roles' => Http::response([
                [
                    'id' => 'guild-123',
                    'name' => '@everyone',
                    'managed' => false,
                    'position' => 0,
                ],
                [
                    'id' => 'role-bot',
                    'name' => 'Bot Managed',
                    'managed' => true,
                    'position' => 10,
                ],
                [
                    'id' => 'role-mods',
                    'name' => 'Mods',
                    'managed' => false,
                    'mentionable' => true,
                    'color' => 16711680,
                    'position' => 5,
                ],
                [
                    'id' => 'role-vips',
                    'name' => 'VIPs',
                    'managed' => false,
                    'mentionable' => false,
                    'color' => 255,
                    'position' => 2,
                ],
            ], 200),
        ]);

        config()->set('services.discord.bot_token', 'test-bot-token');

        $response = $this->actingAs($user)->getJson(route('browser-api.discord.servers.roles', ['server' => $server->id]));

        $response->assertOk();
        expect($response->json('roles'))->toBe([
            [
                'id' => 'role-mods',
                'name' => 'Mods',
                'color' => 16711680,
                'mentionable' => true,
                'position' => 5,
            ],
            [
                'id' => 'role-vips',
                'name' => 'VIPs',
                'color' => 255,
                'mentionable' => false,
                'position' => 2,
            ],
        ]);
    });

    test('install callback registers the server and links it to the current user', function () {
        $user = User::factory()->createQuietly();

        SocialAccount::factory()->discord()->create([
            'user_id' => $user->id,
            'provider_id' => 'discord-user-1',
            'token' => 'test-token',
            'provider_data' => [
                'guilds' => [
                    [
                        'id' => 'managed-guild',
                        'name' => 'Managed Guild',
                        'owner' => false,
                        'permissions' => '32',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'discord_bot_install' => [
                    'state' => 'install-state',
                    'guild_id' => 'managed-guild',
                    'user_id' => $user->id,
                ],
            ])
            ->get(route('dashboard.discord.install.callback', [
                'state' => 'install-state',
                'guild_id' => 'managed-guild',
                'code' => 'oauth-code',
            ]));

        $server = DiscordServer::where('discord_server_id', 'managed-guild')->first();

        $response->assertRedirect(route('dashboard.discord.server', ['server' => $server->id]));
        expect($server)->not->toBeNull()
            ->and($server->discord_server_name)->toBe('Managed Guild')
            ->and($server->owner_user_id)->toBe($user->id)
            ->and($server->is_active)->toBeTrue()
            ->and($server->config)->not->toBeNull()
            ->and($server->members()->where('user_id', $user->id)->where('is_admin', true)->exists())->toBeTrue();
    });
});
