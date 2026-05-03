<?php

declare(strict_types=1);

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\DiscordServerConfig;
use App\Models\DiscordServerTag;
use App\Models\Game;
use App\Models\GameDiscordSubscription;
use App\Models\GameVersion;
use App\Models\Tag;
use App\Services\MultiServerNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function discordServerWithConfig(array $serverAttributes = [], array $configAttributes = []): DiscordServer
{
    $server = DiscordServer::create(array_merge([
        'discord_server_id' => fake()->unique()->numerify('########'),
        'discord_server_name' => fake()->company(),
        'is_active' => true,
        'bot_joined_at' => now(),
    ], $serverAttributes));

    DiscordServerConfig::create(array_merge([
        'discord_server_id' => $server->id,
        'notification_channel_id' => fake()->numerify('########'),
        'notification_format' => 'compact',
        'include_game_description' => false,
        'include_thumbnail' => false,
        'include_ratings' => false,
    ], $configAttributes));

    return $server->fresh('config');
}

it('queues update notifications for directly subscribed Discord servers', function () {
    $service = app(MultiServerNotificationService::class);
    $game = Game::factory()->create(['name' => 'Direct VN']);
    $version = GameVersion::factory()->create(['game_id' => $game->id]);
    $server = discordServerWithConfig();
    $inactiveServer = discordServerWithConfig(['is_active' => false]);

    GameDiscordSubscription::create([
        'game_id' => $game->id,
        'discord_server_id' => $server->id,
        'is_active' => true,
        'subscribed_at' => now(),
    ]);
    GameDiscordSubscription::create([
        'game_id' => $game->id,
        'discord_server_id' => $inactiveServer->id,
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $service->queueGameUpdate($game, $version);

    expect(DiscordNotificationHistory::query()->count())->toBe(1);
    $notification = DiscordNotificationHistory::query()->first();
    expect($notification->discord_server_id)->toBe($server->id)
        ->and($notification->game_id)->toBe($game->id)
        ->and($notification->notification_type)->toBe('update')
        ->and($notification->delivery_status)->toBe('pending')
        ->and($notification->channel_id)->toBe($server->config->notification_channel_id);
});

it('skips unconfigured servers and queues tag subscriptions without duplicating direct subscriptions', function () {
    $service = app(MultiServerNotificationService::class);
    $game = Game::factory()->create(['name' => 'Tagged VN']);
    $tag = Tag::create(['name' => 'Drama']);
    $game->tags()->attach($tag->id);

    $directServer = discordServerWithConfig();
    $tagServer = discordServerWithConfig();
    $unconfiguredServer = DiscordServer::create([
        'discord_server_id' => 'unconfigured',
        'discord_server_name' => 'Unconfigured',
        'is_active' => true,
    ]);

    GameDiscordSubscription::create([
        'game_id' => $game->id,
        'discord_server_id' => $directServer->id,
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    foreach ([$directServer, $tagServer, $unconfiguredServer] as $server) {
        DiscordServerTag::create([
            'discord_server_id' => $server->id,
            'tag_name' => 'Drama',
            'is_subscribed' => true,
        ]);
    }

    $service->queueTagBasedNotifications($game);

    expect(DiscordNotificationHistory::query()->pluck('discord_server_id')->all())
        ->toBe([$tagServer->id]);
});

it('returns pending notifications and marks notification delivery state', function () {
    $service = app(MultiServerNotificationService::class);
    $game = Game::factory()->create();
    $server = discordServerWithConfig();
    $first = DiscordNotificationHistory::create([
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'notification_type' => 'update',
        'channel_id' => 'channel-a',
        'delivery_status' => 'pending',
    ]);
    DiscordNotificationHistory::create([
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'notification_type' => 'manual',
        'channel_id' => 'channel-a',
        'delivery_status' => 'sent',
    ]);

    expect($service->getPendingNotifications($server))->toHaveCount(1)
        ->and($service->getAllPendingNotifications())->toHaveCount(1);

    $service->markAsSent($first, 'message-1');
    $first->refresh();
    expect($first->delivery_status)->toBe('sent')
        ->and($first->message_id)->toBe('message-1');

    $service->markAsFailed($first, 'discord error');
    $first->refresh();
    expect($first->delivery_status)->toBe('failed')
        ->and($first->error_message)->toBe('discord error');
});

it('formats notifications and records manual updates', function () {
    $service = app(MultiServerNotificationService::class);
    $game = Game::factory()->create([
        'name' => 'Manual VN',
        'description' => 'Short description',
        'url' => ['itch_io' => 'https://example.itch.io/manual-vn'],
    ]);
    $plainServer = DiscordServer::create([
        'discord_server_id' => 'plain',
        'discord_server_name' => 'Plain',
        'is_active' => true,
    ]);
    $customServer = discordServerWithConfig([], [
        'notification_format' => 'custom',
        'custom_template' => '{game_name}|{notification_type}|{game_url}',
    ]);

    expect($service->formatNotification($plainServer, $game))->toBe('Manual VN has been updated!')
        ->and($service->formatNotification($customServer, $game, 'manual'))->toContain('Manual VN|manual|');

    $service->recordManualUpdate($customServer, $game, 'manual-message');

    $notification = DiscordNotificationHistory::query()->where('notification_type', 'manual')->first();
    expect($notification)->not->toBeNull()
        ->and($notification->discord_server_id)->toBe($customServer->id)
        ->and($notification->message_id)->toBe('manual-message')
        ->and($notification->delivery_status)->toBe('sent');
});
