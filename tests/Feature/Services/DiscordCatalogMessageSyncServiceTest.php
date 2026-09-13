<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use App\Services\Discord\DiscordCatalogMessageSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Game::unsetEventDispatcher();
});

it('queues one edit when a rendered catalog message changed', function () {
    $game = Game::factory()->create([
        'name' => 'Synced VN',
        'slug' => 'synced-vn',
        'description' => 'Updated description',
        'is_visible' => true,
    ]);
    $server = DiscordServer::factory()->configured()->create(['available_channels' => [['id' => 'channel-1', 'name' => 'one', 'nsfw' => true], ['id' => 'channel-2', 'name' => 'two', 'nsfw' => true]]]);
    $server->config->update(['notification_channel_id' => 'channel-1']);
    DB::table('discord_catalog_messages')->insert([
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'discord_channel_id' => 'channel-1',
        'discord_message_id' => 'message-1',
        'discord_payload_hash' => 'old-hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sync = app(DiscordCatalogMessageSyncService::class);
    expect($sync->queueForGame($game))->toBe(1)
        ->and($sync->queueForGame($game))->toBe(0);

    $notification = DiscordNotificationHistory::query()->firstOrFail();
    expect($notification->delivery_mode)->toBe('edit')
        ->and($notification->message_id)->toBe('message-1')
        ->and($notification->channel_id)->toBe('channel-1')
        ->and($notification->payload_hash)->toHaveLength(64)
        ->and($notification->payload['embeds'][0]['title'])->toBe('Synced VN');
});

it('does not queue an edit when the rendered payload hash is current', function () {
    $game = Game::factory()->create(['name' => 'Current VN', 'slug' => 'current-vn', 'is_visible' => true]);
    $server = DiscordServer::factory()->configured()->create(['available_channels' => [['id' => 'channel-1', 'name' => 'one', 'nsfw' => true], ['id' => 'channel-2', 'name' => 'two', 'nsfw' => true]]]);
    $server->config->update(['notification_channel_id' => 'channel-1']);
    DB::table('discord_catalog_messages')->insert([
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'discord_channel_id' => 'channel-1',
        'discord_message_id' => 'message-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sync = app(DiscordCatalogMessageSyncService::class);
    expect($sync->queueForGame($game))->toBe(1);
    $notification = DiscordNotificationHistory::query()->firstOrFail();
    DB::table('discord_catalog_messages')->where('game_id', $game->id)->update([
        'discord_payload_hash' => $notification->payload_hash,
    ]);
    $notification->delete();

    expect($sync->queueForGame($game))->toBe(0);
});

it('bootstraps catalog rows that have a channel but no Discord message', function () {
    $game = Game::factory()->create(['name' => 'Bootstrap VN', 'slug' => 'bootstrap-vn', 'is_visible' => true]);
    $server = DiscordServer::factory()->configured()->create(['available_channels' => [['id' => 'channel-1', 'name' => 'one', 'nsfw' => true], ['id' => 'channel-2', 'name' => 'two', 'nsfw' => true]]]);
    $server->config->update(['notification_channel_id' => 'channel-1']);
    DB::table('discord_catalog_messages')->insert([
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'discord_channel_id' => 'channel-1',
        'discord_message_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(app(DiscordCatalogMessageSyncService::class)->queueForGame($game))->toBe(1);
    $notification = DiscordNotificationHistory::firstOrFail();
    expect($notification->notification_type)->toBe('new_game')
        ->and($notification->delivery_mode)->toBe('send')
        ->and($notification->message_id)->toBeNull();
});

it('cancels catalog work when a game is hidden or its channel is no longer routed', function (string $change) {
    $game = Game::factory()->create(['is_visible' => true, 'slug' => 'route-change-vn']);
    $server = DiscordServer::factory()->configured()->create(['available_channels' => [['id' => 'channel-1', 'name' => 'one', 'nsfw' => true], ['id' => 'channel-2', 'name' => 'two', 'nsfw' => true]]]);
    $server->config->update(['notification_channel_id' => 'channel-1']);
    DB::table('discord_catalog_messages')->insert([
        'discord_server_id' => $server->id, 'game_id' => $game->id, 'discord_channel_id' => 'channel-1',
        'discord_message_id' => 'message-1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $sync = app(DiscordCatalogMessageSyncService::class);
    expect($sync->queueForGame($game))->toBe(1);
    if ($change === 'hidden') {
        $game->update(['is_visible' => false]);
    } else {
        $server->config->update(['notification_channel_id' => 'channel-2']);
    }
    expect($sync->queueForGame($game))->toBe(0)
        ->and(DiscordNotificationHistory::firstOrFail()->delivery_status)->toBe('failed');
})->with(['hidden', 'routing']);

it('tracks and updates every catalog destination while retaining the legacy primary', function () {
    $game = Game::factory()->create(['is_visible' => true, 'is_nsfw' => false, 'slug' => 'multi-channel-game']);
    $server = DiscordServer::factory()->configured()->create(['available_channels' => [['id' => 'one'], ['id' => 'two']]]);
    $server->config->update(['routing_rules' => array_map(fn ($id) => [
        'id' => $id, 'name' => $id, 'enabled' => true, 'priority' => 1,
        'conditions' => [['field' => 'notification_type', 'operator' => 'equals', 'value' => 'new_game']],
        'action' => ['type' => 'route', 'channel_id' => $id],
    ], ['one', 'two'])]);
    $sync = app(DiscordCatalogMessageSyncService::class);
    $sync->trackMessage($server->id, $game->id, 'one', 'message-one', 'old');
    $sync->trackMessage($server->id, $game->id, 'two', 'message-two', 'old');
    expect(DB::table('discord_catalog_messages')->where('game_id', $game->id)->count())->toBe(2)
        ->and(DB::table('discord_server_games')->where('game_id', $game->id)->value('discord_channel_id'))->toBe('one')
        ->and($sync->queueForGame($game))->toBe(2)->and($sync->queueForGame($game))->toBe(0);
    expect(DiscordNotificationHistory::where('game_id', $game->id)->pluck('message_id')->sort()->values()->all())->toBe(['message-one', 'message-two']);
});

it('migrates the primary catalog row and recovers additional destinations from delivery history', function () {
    $server = DiscordServer::factory()->create();
    $game = Game::factory()->create();
    $migration = require database_path('migrations/2026_09_06_000003_fix_discord_delivery_state.php');
    $migration->down();
    $alert = AdditionRequest::factory()->create(['status' => 'pending', 'discord_notify_attempts' => 3, 'discord_claimed_at' => now()]);
    DB::table('discord_server_games')->insert(['discord_server_id' => $server->id, 'game_id' => $game->id,
        'discord_channel_id' => 'one', 'discord_message_id' => 'primary', 'created_at' => now(), 'updated_at' => now()]);
    foreach (['old', 'latest'] as $message) {
        DiscordNotificationHistory::create(['discord_server_id' => $server->id, 'game_id' => $game->id,
            'notification_type' => 'new_game', 'delivery_status' => 'sent', 'channel_id' => 'two', 'message_id' => $message, 'sent_at' => now()]);
    }
    $migration->up();
    expect(DB::table('discord_catalog_messages')->where('game_id', $game->id)->pluck('discord_message_id', 'discord_channel_id')->all())
        ->toBe(['one' => 'primary', 'two' => 'latest']);
    expect($server->fresh()->bot_present)->toBeTrue();
    expect($alert->fresh()->discord_notify_attempts)->toBe(0)->and($alert->fresh()->discord_claimed_at)->toBeNull();
});
