<?php

declare(strict_types=1);

use App\Services\RatingStatsCacheService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('uses versioned keys for expiring rating stats caches', function () {
    $firstKey = RatingStatsCacheService::key('ratings.count:test');

    RatingStatsCacheService::clear();

    expect(RatingStatsCacheService::key('ratings.count:test'))
        ->not->toBe($firstKey);
});

it('clears the stable global stats forever cache instead of accumulating versions', function () {
    Cache::forever(RatingStatsCacheService::GLOBAL_STATS_KEY, ['total' => 10]);

    RatingStatsCacheService::clear();

    expect(Cache::has(RatingStatsCacheService::GLOBAL_STATS_KEY))->toBeFalse();
});

it('removes legacy versioned global stats keys during invalidation', function () {
    $currentLegacyKey = RatingStatsCacheService::key('ratings.global_stats');
    Cache::forever($currentLegacyKey, ['stale' => true]);

    RatingStatsCacheService::clear();

    expect(Cache::has($currentLegacyKey))->toBeFalse();
});
