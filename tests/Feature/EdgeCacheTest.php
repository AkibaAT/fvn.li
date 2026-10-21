<?php

declare(strict_types=1);

use App\Jobs\PurgeEdgeCache;
use App\Models\Game;
use App\Services\EdgeCache;
use App\Services\RatingStatsCacheService;
use App\Services\VnListCacheService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function configureCloudflare(): void
{
    config(['services.cloudflare.zone_id' => 'zone-1', 'services.cloudflare.api_token' => 'secret']);
}

test('purging does nothing until cloudflare is configured', function () {
    Queue::fake();

    EdgeCache::purge(['games']);

    Queue::assertNothingPushed();
});

test('purges collect into one pending set behind a single queued job', function () {
    configureCloudflare();
    Queue::fake();

    EdgeCache::purge(['games', 'game-1']);
    EdgeCache::purge(['games', 'lists']);

    Queue::assertPushed(PurgeEdgeCache::class);
    expect(EdgeCache::takePending())->toEqualCanonicalizing(['games', 'game-1', 'lists'])
        ->and(EdgeCache::takePending())->toBe([]);
});

test('the purge job sends the pending tags to cloudflare', function () {
    configureCloudflare();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
    Queue::fake();
    EdgeCache::purge(['games', 'game-7']);

    (new PurgeEdgeCache)->handle();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-1/purge_cache'
        && $request->hasHeader('Authorization', 'Bearer secret')
        && collect($request['tags'])->sort()->values()->all() === ['game-7', 'games']);
});

test('more tags than one request holds purge the whole zone', function () {
    configureCloudflare();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

    EdgeCache::send(array_map(fn (int $id) => "game-{$id}", range(1, EdgeCache::MAX_TAGS_PER_REQUEST + 1)));

    Http::assertSent(fn (Request $request) => $request['purge_everything'] === true);
});

test('a failed purge keeps its tags pending for the retry', function () {
    configureCloudflare();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false], 500)]);
    Queue::fake();
    EdgeCache::purge(['games']);

    expect(fn () => (new PurgeEdgeCache)->handle())->toThrow(Exception::class)
        ->and(EdgeCache::takePending())->toBe(['games']);
});

test('content changes purge the pages that show them', function () {
    configureCloudflare();
    Queue::fake();
    $game = Game::factory()->create(['is_visible' => true]);
    EdgeCache::takePending();

    $game->update(['name' => 'Renamed']);
    app(VnListCacheService::class)->clearPublicListsCache();
    RatingStatsCacheService::clear();

    expect(EdgeCache::takePending())->toEqualCanonicalizing(['games', "game-{$game->id}", 'lists', 'ratings']);
});

test('the purge command clears the whole zone when configured and is a no-op otherwise', function () {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->artisan('edge-cache:purge')->assertSuccessful();
    Http::assertNothingSent();

    configureCloudflare();
    $this->artisan('edge-cache:purge')->assertSuccessful();
    Http::assertSent(fn (Request $request) => $request['purge_everything'] === true);
});
