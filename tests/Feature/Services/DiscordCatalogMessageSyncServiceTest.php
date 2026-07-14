<?php

declare(strict_types=1);

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
    $server = DiscordServer::factory()->configured()->create();
    DB::table('discord_server_games')->insert([
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
    $server = DiscordServer::factory()->configured()->create();
    DB::table('discord_server_games')->insert([
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
    DB::table('discord_server_games')->where('game_id', $game->id)->update([
        'discord_payload_hash' => $notification->payload_hash,
    ]);
    $notification->delete();

    expect($sync->queueForGame($game))->toBe(0);
});
