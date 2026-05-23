<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameSearchRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Queue::fake();
});

test('refresh for latest version invalidates homepage teasers', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create([
        'is_latest' => true,
    ]);

    Cache::put('home.teasers.version', 10);

    GameSearchRefreshService::refreshForLatestVersion($version, 'test');

    expect(Cache::get('home.teasers.version'))->toBe(11);
});

test('refresh for non latest version leaves homepage teasers alone', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create([
        'is_latest' => false,
    ]);

    Cache::put('home.teasers.version', 10);

    GameSearchRefreshService::refreshForLatestVersion($version, 'test');

    expect(Cache::get('home.teasers.version'))->toBe(10);
});
