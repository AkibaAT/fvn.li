<?php

declare(strict_types=1);

use App\Models\DiscordServer;
use App\Models\DiscordServerTag;
use App\Models\Game;
use App\Models\GameDiscordSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function discordSubscriptionOwner(): array
{
    $user = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($user);

    $server = DiscordServer::create([
        'discord_server_id' => 'guild-1',
        'discord_server_name' => 'Guild One',
        'owner_user_id' => $user->id,
        'is_active' => true,
    ]);

    return [$user, $server];
}

it('requires ownership for Discord server subscriptions', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($other);
    $server = DiscordServer::create([
        'discord_server_id' => 'guild-private',
        'discord_server_name' => 'Private Guild',
        'owner_user_id' => $owner->id,
        'is_active' => true,
    ]);

    $this->getJson("/api/discord-servers/{$server->id}/subscriptions")
        ->assertForbidden();
});

it('subscribes reuses lists bulk subscribes and unsubscribes games', function () {
    [, $server] = discordSubscriptionOwner();
    $game = Game::factory()->create(['name' => 'Discord Sub Game']);
    $secondGame = Game::factory()->create();

    $this->postJson("/api/discord-servers/{$server->id}/subscribe", ['game_id' => $game->id])
        ->assertCreated()
        ->assertJsonPath('message', 'Subscribed successfully')
        ->assertJsonPath('subscription.game_id', $game->id);

    $subscription = GameDiscordSubscription::query()->first();
    $subscription->update(['is_active' => false]);

    $this->postJson("/api/discord-servers/{$server->id}/subscribe", ['game_id' => $game->id])
        ->assertOk()
        ->assertJsonPath('message', 'Already subscribed to this game');

    expect($subscription->fresh()->is_active)->toBeTrue();

    $this->postJson("/api/discord-servers/{$server->id}/bulk-subscribe", [
        'game_ids' => [$game->id, $secondGame->id],
    ])->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('skipped', 1);

    $this->getJson("/api/discord-servers/{$server->id}/subscriptions")
        ->assertOk()
        ->assertJsonPath('data.0.game.name', 'Discord Sub Game');

    $this->deleteJson("/api/discord-servers/{$server->id}/games/{$game->slug}")
        ->assertOk()
        ->assertJsonPath('message', 'Unsubscribed successfully');

    expect(GameDiscordSubscription::where('game_id', $game->id)->exists())->toBeFalse();
});

it('manages Discord server tag subscriptions', function () {
    [, $server] = discordSubscriptionOwner();

    $this->postJson("/api/discord-servers/{$server->id}/subscribe-tag", ['tag_name' => 'Kinetic Novel'])
        ->assertCreated()
        ->assertJsonPath('message', 'Tag subscription created')
        ->assertJsonPath('tag.is_subscribed', true);

    $this->getJson("/api/discord-servers/{$server->id}/tags")
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('tags.0.tag_name', 'Kinetic Novel');

    $this->deleteJson("/api/discord-servers/{$server->id}/tags/Kinetic%20Novel")
        ->assertOk()
        ->assertJsonPath('message', 'Tag unsubscribed');

    expect(DiscordServerTag::query()->count())->toBe(0);
});

it('stores per-server Discord game metadata and rating arrays in the metadata table', function () {
    [, $server] = discordSubscriptionOwner();
    $game = Game::factory()->create([
        'name' => 'Metadata Game',
        'content_type' => 'visual_novel',
        'url' => ['itch_io' => 'https://metadata.itch.io/game'],
    ]);
    GameDiscordSubscription::create([
        'game_id' => $game->id,
        'discord_server_id' => $server->id,
        'is_active' => true,
    ]);

    $this->getJson("/api/discord-servers/{$server->id}/games/{$game->slug}/metadata")
        ->assertOk()
        ->assertJsonPath('name', 'Metadata Game')
        ->assertJsonPath('discord_likes', []);

    $this->postJson("/api/discord-servers/{$server->id}/games/{$game->slug}/metadata", [
        'discord_channel_id' => 'channel-1',
        'discord_message_id' => 'message-1',
        'discord_tags' => ['new'],
        'abbreviations' => ['mg'],
        'content_type' => 'adjacent',
    ])->assertOk()
        ->assertJsonPath('metadata.discord_channel_id', 'channel-1')
        ->assertJsonPath('metadata.discord_tags.0', 'new')
        ->assertJsonPath('metadata.content_type', 'adjacent');

    expect($game->fresh()->content_type)->toBe('adjacent')
        ->and(DB::table('discord_server_games')->where('discord_server_id', $server->id)->where('game_id', $game->id)->value('discord_channel_id'))->toBe('channel-1');

    $this->postJson("/api/discord-servers/{$server->id}/games/{$game->slug}/rating", [
        'user_id' => 'discord-user',
        'rating' => 'like',
    ])->assertOk()
        ->assertJsonPath('discord_likes.0', 'discord-user')
        ->assertJsonPath('discord_dislikes', []);

    $this->postJson("/api/discord-servers/{$server->id}/games/{$game->slug}/rating", [
        'user_id' => 'discord-user',
        'rating' => 'dislike',
    ])->assertOk()
        ->assertJsonPath('discord_likes', [])
        ->assertJsonPath('discord_dislikes.0', 'discord-user');

    $this->postJson("/api/discord-servers/{$server->id}/games/{$game->slug}/rating", [
        'user_id' => 'discord-user',
        'rating' => 'none',
    ])->assertOk()
        ->assertJsonPath('discord_likes', [])
        ->assertJsonPath('discord_dislikes', []);
});
