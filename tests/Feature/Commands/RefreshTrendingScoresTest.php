<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Queue::fake();
});

test('it updates only changed trending scores and refreshes visible changed games', function () {
    $visibleChanged = Game::factory()->create([
        'is_visible' => true,
        'trending_score' => 0,
    ]);
    $hiddenChanged = Game::factory()->create([
        'is_visible' => false,
        'trending_score' => 0,
    ]);
    $staleVisible = Game::factory()->create([
        'is_visible' => true,
        'trending_score' => 7,
    ]);
    $alreadyCurrent = Game::factory()->create([
        'is_visible' => true,
        'trending_score' => 1,
    ]);

    recordPageView($visibleChanged, '203.0.113.1');
    recordPageView($visibleChanged, '203.0.113.2');
    recordPageView($hiddenChanged, '203.0.113.3');
    recordPageView($alreadyCurrent, '203.0.113.4');

    Cache::put('home.teasers.version', 10);

    $this->artisan('games:refresh-trending-scores')
        ->expectsOutput('Updated trending scores for 3 games; queued 2 visible games for search refresh.')
        ->assertSuccessful();

    expect($visibleChanged->refresh()->trending_score)->toBe(2)
        ->and($hiddenChanged->refresh()->trending_score)->toBe(1)
        ->and($staleVisible->refresh()->trending_score)->toBe(0)
        ->and($alreadyCurrent->refresh()->trending_score)->toBe(1)
        ->and(Cache::get('home.teasers.version'))->toBe(11);

    $this->artisan('games:refresh-trending-scores')
        ->expectsOutput('Trending scores are already current.')
        ->assertSuccessful();
});

test('it counts a repeat visitor once per day', function () {
    $game = Game::factory()->create(['is_visible' => true, 'trending_score' => 0]);

    foreach (range(1, 20) as $ignored) {
        recordPageView($game, '203.0.113.9');
    }

    $this->artisan('games:refresh-trending-scores')->assertSuccessful();

    expect($game->refresh()->trending_score)->toBe(1);
});

test('it separates visitors sharing a subnet by user agent', function () {
    $game = Game::factory()->create(['is_visible' => true, 'trending_score' => 0]);

    recordPageView($game, '203.0.113.10', 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36');
    recordPageView($game, '203.0.113.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Firefox/152.0');

    $this->artisan('games:refresh-trending-scores')->assertSuccessful();

    expect($game->refresh()->trending_score)->toBe(2);
});

test('it counts the same visitor again after 24 hours', function () {
    $game = Game::factory()->create(['is_visible' => true, 'trending_score' => 0]);

    recordPageView($game, '203.0.113.11', clickedAt: now()->subDays(3));
    recordPageView($game, '203.0.113.11', clickedAt: now());

    $this->artisan('games:refresh-trending-scores')->assertSuccessful();

    expect($game->refresh()->trending_score)->toBe(2);
});

test('it ignores views flagged as automated', function () {
    $game = Game::factory()->create(['is_visible' => true, 'trending_score' => 0]);

    foreach (range(1, 50) as $index) {
        recordPageView($game, '198.51.100.' . $index, botReason: 'blocked_network');
    }

    recordPageView($game, '203.0.113.12');

    $this->artisan('games:refresh-trending-scores')->assertSuccessful();

    expect($game->refresh()->trending_score)->toBe(1);
});

test('it ignores views older than the trending window', function () {
    $game = Game::factory()->create(['is_visible' => true, 'trending_score' => 0]);

    recordPageView($game, '203.0.113.13', clickedAt: now()->subDays(ClickStat::TRENDING_WINDOW_DAYS + 1));

    $this->artisan('games:refresh-trending-scores')->assertSuccessful();

    expect($game->refresh()->trending_score)->toBe(0);
});

test('the searchable trending score matches the command', function () {
    $game = Game::factory()->create(['is_visible' => true, 'trending_score' => 0]);

    recordPageView($game, '203.0.113.14');
    recordPageView($game, '203.0.113.14');
    recordPageView($game, '203.0.113.15');
    recordPageView($game, '198.51.100.1', botReason: 'blocked_network');

    $this->artisan('games:refresh-trending-scores')->assertSuccessful();

    expect($game->getTrendingScore())->toBe(2)
        ->and($game->refresh()->trending_score)->toBe(2);
});

function recordPageView(
    Game $game,
    string $ipAddress = '203.0.113.100',
    string $userAgent = 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36',
    ?string $botReason = null,
    ?Carbon $clickedAt = null,
): void {
    ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => fake()->uuid(),
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'bot_reason' => $botReason,
        'clicked_at' => $clickedAt ?? now(),
    ]);
}
