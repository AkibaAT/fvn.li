<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Queue::fake();
});

test('changing latest version published date invalidates homepage teasers', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create([
        'is_latest' => true,
        'published_at' => now()->subDay(),
    ]);

    Cache::put('home.teasers.version', 10);

    $version->published_at = now();
    $version->save();

    expect(Cache::get('home.teasers.version'))->toBe(11);
});
