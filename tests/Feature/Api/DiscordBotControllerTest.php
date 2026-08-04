<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function actAsDiscordBotApiUser(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['discord-bot']);

    return $user;
}

function createDiscordBotGame(array $attributes = [], array $versionAttributes = []): Game
{
    $game = Game::factory()->create(array_merge([
        'name' => 'Discord Bot Match',
        'platform' => 'itch_io',
        'url' => ['itch_io' => 'https://developer.itch.io/discord-bot-match'],
        'is_visible' => true,
    ], $attributes));

    GameVersion::factory()->for($game)->latest()->create(array_merge([
        'version' => '2.0',
        'published_at' => now()->subHour(),
        'devlog' => 'https://developer.itch.io/discord-bot-match/devlog/2',
    ], $versionAttributes));

    return $game->fresh('latestVersion');
}

it('requires authentication for Discord bot routes', function () {
    $this->postJson('/api/discord/search', ['name' => 'match'])
        ->assertUnauthorized();
});

it('requires a Discord bot token ability for Discord bot routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/discord/search', ['name' => 'match'])
        ->assertUnauthorized();

    Sanctum::actingAs($user, ['profile']);

    $this->postJson('/api/discord/search', ['name' => 'match'])
        ->assertForbidden();
});

it('keeps legacy Discord bot routes available when the server bot is disabled', function () {
    config(['services.discord.server_bot_enabled' => false]);
    actAsDiscordBotApiUser();

    $this->postJson('/api/discord/search', ['name' => 'match'])
        ->assertOk();
});

it('searches visible itch games for the Discord bot', function () {
    actAsDiscordBotApiUser();
    $game = createDiscordBotGame(['name' => 'Moonlight Match']);
    createDiscordBotGame([
        'name' => 'Hidden Moonlight Match',
        'is_visible' => false,
    ]);
    createDiscordBotGame([
        'name' => 'Steam Moonlight Match',
        'platform' => 'steam',
        'url' => ['steam' => 'https://store.steampowered.com/app/123456/Steam_Moonlight/'],
    ]);

    $this->postJson('/api/discord/search', ['name' => 'Moonlight'])
        ->assertOk()
        ->assertJsonPath('matches', 1)
        ->assertJsonPath('games.0.name', 'Moonlight Match')
        ->assertJsonPath('games.0.version', '2.0')
        ->assertJsonPath('games.0.primary_url', 'https://developer.itch.io/discord-bot-match')
        ->assertJsonPath('games.0.url', config('app.url') . '/games/' . $game->slug)
        ->assertJsonPath('search_url', config('app.url') . '/games/search?q=Moonlight');

    $this->postJson('/api/discord/search', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.name.0', 'The name field is required.');
});

it('finds games by URL, slug fallback, bulk URL lookup, and id', function () {
    actAsDiscordBotApiUser();
    $game = createDiscordBotGame([
        'name' => 'URL Lookup Game',
        'url' => ['itch_io' => 'https://lookup.itch.io/url-game'],
        'steam_app_id' => '555',
        'platform' => 'itch_io',
        'description' => 'Lookup description',
        'status' => 'Released',
        'thumb_url' => 'https://cdn.example/thumb.jpg',
        'content_type' => 'visual_novel',
    ], [
        'version' => '3.0',
        'published_at' => now()->subDay(),
    ]);
    $hiddenByUrl = createDiscordBotGame([
        'name' => 'Hidden URL Lookup Game',
        'url' => ['itch_io' => 'https://hidden.itch.io/hidden-url-only'],
        'is_visible' => false,
        'description' => 'Hidden URL description',
    ]);
    $hiddenBySlug = createDiscordBotGame([
        'name' => 'Hidden Slug Lookup Game',
        'slug' => 'hidden-slug-game',
        'url' => ['itch_io' => 'https://hidden.itch.io/original'],
        'is_visible' => false,
        'description' => 'Hidden slug description',
    ]);

    $this->postJson('/api/bot/find-by-url', ['url' => 'https://lookup.itch.io/url-game'])
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('game.id', $game->id)
        ->assertJsonPath('game.itch_id', $game->itch_id)
        ->assertJsonPath('game.steam_app_id', 555);

    $this->postJson('/api/bot/find-by-url', ['url' => 'https://someone.itch.io/' . $game->slug])
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('game.id', $game->id);

    $this->postJson('/api/bot/find-by-url', ['url' => 'https://hidden.itch.io/hidden-url-only'])
        ->assertNotFound()
        ->assertJsonPath('found', false);

    $this->postJson('/api/bot/find-by-url', ['url' => 'https://someone.itch.io/hidden-slug-game'])
        ->assertNotFound()
        ->assertJsonPath('found', false);

    $this->postJson('/api/bot/find-by-url', ['url' => 'not-a-url'])
        ->assertUnprocessable()
        ->assertJsonPath('error.url.0', 'The url field must be a valid URL.');

    $this->postJson('/api/bot/find-by-url', ['url' => 'https://missing.itch.io/game'])
        ->assertNotFound()
        ->assertJsonPath('found', false);

    $bulkResponse = $this->postJson('/api/bot/bulk-find-by-url', [
        'urls' => [
            'https://lookup.itch.io/url-game',
            'https://hidden.itch.io/hidden-url-only',
            'https://someone.itch.io/hidden-slug-game',
            'https://missing.itch.io/nope',
        ],
    ]);

    $bulkResponse->assertOk()
        ->assertJsonPath('matched', 1)
        ->assertJsonPath('unmatched', 3);

    expect($bulkResponse->json('results')['https://lookup.itch.io/url-game']['found'])->toBeTrue()
        ->and($bulkResponse->json('results')['https://hidden.itch.io/hidden-url-only']['found'])->toBeFalse()
        ->and($bulkResponse->json('results')['https://someone.itch.io/hidden-slug-game']['found'])->toBeFalse()
        ->and($bulkResponse->json('results')['https://missing.itch.io/nope']['found'])->toBeFalse();

    $this->getJson('/api/bot/games/' . $game->id)
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('game.id', $game->id)
        ->assertJsonPath('game.latest_version.version', '3.0')
        ->assertJsonPath('game.fvn_li_url', config('app.url') . '/games/' . $game->slug);

    $this->getJson('/api/bot/games/' . $hiddenByUrl->id)
        ->assertNotFound()
        ->assertJsonPath('found', false)
        ->assertJsonMissing(['description' => 'Hidden URL description']);

    $this->getJson('/api/bot/games/999999')
        ->assertNotFound()
        ->assertJsonPath('found', false)
        ->assertJsonPath('message', 'Game not found');

    expect($hiddenBySlug->exists)->toBeTrue();
});
