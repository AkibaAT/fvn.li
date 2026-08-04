<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;

it('handles platform URL helpers and platform/content labels', function () {
    $game = Game::factory()->make([
        'platform' => 'steam',
        'content_type' => 'adjacent',
        'url' => ['steam' => 'https://store.steampowered.com/app/123/Game/', 'itch_io' => 'https://dev.itch.io/game'],
    ]);

    expect($game->isSteamGame())->toBeTrue()
        ->and($game->isItchioGame())->toBeFalse()
        ->and($game->isOtherGame())->toBeFalse()
        ->and($game->getPlatformName())->toBe('Steam')
        ->and($game->getUrlForPlatform('steam'))->toBe('https://store.steampowered.com/app/123/Game/')
        ->and($game->getPrimaryUrl())->toBe('https://store.steampowered.com/app/123/Game/')
        ->and($game->getAllUrls())->toHaveKey('itch_io')
        ->and($game->hasUrlForPlatform('steam'))->toBeTrue()
        ->and($game->hasUrlForPlatform('other'))->toBeFalse()
        ->and($game->isAdjacentGame())->toBeTrue()
        ->and($game->isVisualNovel())->toBeFalse()
        ->and($game->isPublicContent())->toBeFalse()
        ->and($game->isBotOnlyContent())->toBeTrue()
        ->and($game->getContentTypeName())->toBe('Adjacent Game');

    $game->setUrlForPlatform('other', 'https://example.com/game');
    expect($game->getUrlForPlatform('other'))->toBe('https://example.com/game');

    expect(Game::factory()->make(['platform' => 'itch_io'])->getPlatformName())->toBe('itch.io')
        ->and(Game::factory()->make(['platform' => 'other'])->getPlatformName())->toBe('Other')
        ->and(Game::factory()->make(['platform' => 'unknown'])->getPlatformName())->toBe('Unknown')
        ->and(Game::factory()->make(['content_type' => 'visual_novel'])->getContentTypeName())->toBe('Visual Novel')
        ->and(Game::factory()->make(['content_type' => 'other'])->getContentTypeName())->toBe('Other Content')
        ->and(Game::factory()->make(['content_type' => 'unknown'])->getContentTypeName())->toBe('Unknown');
});

it('filters platform and content scopes', function () {
    $itch = Game::factory()->create(['platform' => 'itch_io', 'content_type' => 'visual_novel', 'url' => ['itch_io' => 'https://dev.itch.io/itch']]);
    $steam = Game::factory()->create(['platform' => 'steam', 'content_type' => 'adjacent', 'url' => ['steam' => 'https://store.steampowered.com/app/456/Game/']]);
    $other = Game::factory()->create(['platform' => 'other', 'content_type' => 'other', 'url' => ['other' => 'https://example.com/other']]);

    expect(Game::fromItchio()->pluck('id')->all())->toBe([$itch->id])
        ->and(Game::fromSteam()->pluck('id')->all())->toBe([$steam->id])
        ->and(Game::fromOther()->pluck('id')->all())->toBe([$other->id])
        ->and(Game::byUrl('https://store.steampowered.com/app/456/Game/')->value('id'))->toBe($steam->id)
        ->and(Game::byUrlForPlatform('https://example.com/other', 'other')->value('id'))->toBe($other->id)
        ->and(Game::visualNovels()->pluck('id')->all())->toBe([$itch->id])
        ->and(Game::adjacentGames()->pluck('id')->all())->toBe([$steam->id])
        ->and(Game::otherContent()->pluck('id')->all())->toBe([$other->id])
        ->and(Game::publicContent()->pluck('id')->all())->toBe([$itch->id])
        ->and(Game::botOnlyContent()->orderBy('id')->pluck('id')->all())->toBe([$steam->id, $other->id]);
});

it('sorts and filters additional links by release date', function () {
    $game = Game::factory()->make();
    $game->additional_links = [
        ['id' => 3, 'label' => 'Future', 'url' => 'https://example.com/future', 'sort_order' => 1, 'release_at' => now()->addDay()->toISOString()],
        ['id' => 2, 'label' => 'Second', 'url' => 'https://example.com/second', 'sort_order' => 2],
        ['id' => 1, 'label' => 'First', 'url' => 'https://example.com/first', 'sort_order' => 1],
        ['id' => 4, 'label' => 'Invalid Date', 'url' => 'https://example.com/invalid', 'sort_order' => 3, 'release_at' => 'not-a-date'],
    ];

    expect(array_column($game->additional_links, 'label'))->toBe(['First', 'Second'])
        ->and(array_column($game->getAllAdditionalLinks(), 'label'))->toBe(['First', 'Future', 'Second', 'Invalid Date'])
        ->and($game->hasAdditionalLinks())->toBeTrue()
        ->and(Game::factory()->make(['additional_links' => null])->additional_links)->toBe([])
        ->and(Game::factory()->make(['additional_links' => null])->getAllAdditionalLinks())->toBe([])
        ->and(Game::factory()->make(['additional_links' => null])->hasAdditionalLinks())->toBeFalse();
});

it('copies and updates custom page data and enforces edit permissions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);
    $game = Game::factory()->create([
        'full_description' => '<p>Original</p>',
        'screenshots' => [['url' => 'https://example.com/shot.png']],
    ]);

    expect($game->canUserEdit(null))->toBeFalse()
        ->and($game->canUserEdit($user))->toBeFalse()
        ->and($game->canUserEdit($admin))->toBeTrue();

    $game->enableCustomPage($admin);

    expect($game->refresh()->has_custom_page)->toBeTrue()
        ->and($game->custom_description)->toBe('<p>Original</p>')
        ->and($game->custom_screenshots)->toBe([['url' => 'https://example.com/shot.png']]);

    $game->updateCustomPage([
        'name' => 'Custom Name',
        'description' => '<p>Custom</p>',
        'screenshots' => [['url' => 'custom.png']],
        'assets' => ['image.png'],
    ], $admin);

    expect($game->refresh()->custom_name)->toBe('Custom Name')
        ->and($game->custom_description)->toBe('<p>Custom</p>')
        ->and($game->custom_assets)->toBe(['image.png']);

    $game->disableCustomPage();

    expect($game->refresh()->has_custom_page)->toBeFalse()
        ->and($game->custom_name)->toBeNull()
        ->and($game->custom_description)->toBeNull();
});

it('exposes latest-version backed appended attributes', function () {
    $game = Game::factory()->create(['rating_score' => 4.5, 'rating_count' => 12]);
    $version = GameVersion::factory()->for($game)->create([
        'devlog' => 'Version notes',
        'is_windows' => true,
        'is_linux' => false,
        'is_mac' => true,
        'is_android' => false,
        'is_web' => true,
    ]);
    $version->forceFill(['is_latest' => true])->save();
    $loaded = $game->fresh('latestVersion');

    expect($loaded->devlog)->toBe('Version notes')
        ->and((float) $loaded->rating)->toBe(4.5)
        ->and($loaded->rating_count)->toBe(12)
        ->and($loaded->platforms)->toBe([
            'windows' => true,
            'linux' => false,
            'mac' => true,
            'android' => false,
            'web' => true,
        ]);
});
