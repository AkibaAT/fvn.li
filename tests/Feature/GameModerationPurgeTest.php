<?php

declare(strict_types=1);

use App\Jobs\PurgeModeratedGame;
use App\Models\AdditionRequest;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\Tag;
use App\Services\DenKitStashPersistenceService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Meilisearch\Client;

function contentRichGame(): Game
{
    $game = Game::factory()->create([
        'is_visible' => true,
        'authors' => 'Moderated Author',
        'status' => 'Released',
        'is_nsfw' => true,
        'description' => 'Short blurb',
        'full_description' => '<p>Long description</p>',
        'thumb_url' => 'https://img.itch.zone/cover.png',
        'optimized_thumbnails' => ['default' => ['path' => 'thumbnails/cover_default.webp']],
        'screenshots' => [['url' => 'https://img.itch.zone/shot.png', 'optimized' => ['large' => ['path' => 'screenshots/shot_large.webp']]]],
    ]);
    $game->tags()->attach(Tag::create(['name' => 'Moderated tag']));
    $version = GameVersion::factory()->create(['game_id' => $game->id]);
    Character::factory()->create(['game_id' => $game->id, 'first_seen_in_version_id' => $version->id]);
    Rating::create([
        'game_id' => $game->id,
        'rater_id' => Rater::factory()->create()->id,
        'rating' => 4,
        'review' => '<p>Kept review</p>',
        'is_visible' => true,
        'is_reviewed' => true,
        'published_at' => now(),
        'event_id' => 1,
        'source_platform' => 'itch_io',
    ]);

    return $game;
}

it('purges stored content but keeps identity and reviews when a game is hidden by moderation', function () {
    Storage::fake('public');
    Storage::fake('local');
    $game = contentRichGame();
    Storage::disk('public')->put('thumbnails/cover_default.webp', 'x');
    Storage::disk('public')->put('screenshots/shot_large.webp', 'x');
    Storage::disk('public')->put("games/{$game->id}/screenshots/custom.webp", 'x');
    Storage::disk('public')->put("thumbnails/{$game->id}_stale.webp", 'x');
    Storage::disk('public')->put("editor/{$game->id}/inline.png", 'x');
    Storage::disk('public')->put('thumbnails/1' . $game->id . '_other.webp', 'x');
    Storage::put("games/{$game->id}/1/build.zip", 'x');
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index->deleteDocuments')->once()->with(['filter' => "game_id = {$game->id}"]);
    app()->instance(Client::class, $client);
    $this->mock(DenKitStashPersistenceService::class)->shouldReceive('deleteTarget')->once()->with(Mockery::on(fn (Game $target) => $target->is($game)));

    $game->update(['is_moderation_hidden' => true]);

    expect($game->refresh())
        ->is_visible->toBeFalse()
        ->description->toBeNull()
        ->full_description->toBeNull()
        ->thumb_url->toBeNull()
        ->screenshots->toBeNull()
        ->authors->toBeNull()
        ->status->toBe('In development')
        ->is_nsfw->toBeFalse()
        ->name->not->toBeNull()
        ->and($game->gameVersions()->count())->toBe(0)
        ->and($game->characters()->count())->toBe(0)
        ->and($game->tags()->count())->toBe(0)
        ->and($game->ratings()->count())->toBe(1);
    Storage::disk('public')->assertMissing([
        'thumbnails/cover_default.webp',
        'screenshots/shot_large.webp',
        "games/{$game->id}/screenshots/custom.webp",
        "thumbnails/{$game->id}_stale.webp",
        "editor/{$game->id}/inline.png",
    ]);
    Storage::disk('public')->assertExists('thumbnails/1' . $game->id . '_other.webp');
    Storage::assertMissing("games/{$game->id}/1/build.zip");
});

it('does not fetch content for a moderation-hidden game', function () {
    $game = Game::factory()->create(['platform' => 'itch_io', 'is_moderation_hidden' => true]);

    $game->loadFullDetails();
    $game->refreshVersion();

    expect($game->refresh()->gameVersions()->count())->toBe(0);
});

it('serves hidden game pages without stored content', function () {
    $game = contentRichGame();
    $game->forceFill(['is_visible' => false])->saveQuietly();

    $props = $this->get(route('games.show', $game))->assertOk()->viewData('page')['props'];

    expect($props['game'])
        ->effective_description->toBeEmpty()
        ->screenshots->toBeEmpty()
        ->thumb_url->toBeNull()
        ->tags->toBeEmpty()
        ->and($props['gameVersions']['data'])->toBeEmpty()
        ->and($props['reviews']['data'])->toHaveCount(1)
        ->and($props['metaTags']['description'])->not->toContain('Short blurb')
        ->and($props['metaTags']['description'])->not->toContain('Moderated Author')
        ->and($props['metaTags']['image'])->not->toContain('cover');
});

it('hides version data endpoints for hidden games', function () {
    $game = contentRichGame();
    $version = $game->gameVersions()->first();

    $this->getJson(route('browser-api.games.versions', $game->id))->assertOk();

    $game->forceFill(['is_visible' => false])->saveQuietly();

    $this->getJson(route('browser-api.games.versions', $game->id))->assertNotFound();
    $this->getJson(route('browser-api.games.version.character-stats', [$game, $version]))->assertNotFound();
    $this->get(route('games.route-map', $game))->assertNotFound();
    $this->getJson(route('browser-api.games.reviews', $game->id))->assertOk();
});

it('purges and rejects requests for a game created already hidden by moderation', function () {
    Bus::fake();
    $url = 'https://creator.itch.io/created-hidden';
    [$request] = AdditionRequest::findOrCreateForUrl($url);

    $game = Game::factory()->create(['url' => ['itch_io' => $url], 'is_moderation_hidden' => true]);

    Bus::assertDispatched(PurgeModeratedGame::class, fn (PurgeModeratedGame $job) => $job->gameId === $game->id);
    expect($request->refresh()->status)->toBe(AdditionRequest::STATUS_REJECTED);
});
