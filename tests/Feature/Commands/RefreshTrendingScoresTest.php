<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;
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

    recordPageView($visibleChanged);
    recordPageView($visibleChanged);
    recordPageView($hiddenChanged);
    recordPageView($alreadyCurrent);

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

function recordPageView(Game $game): void
{
    ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => fake()->uuid(),
        'clicked_at' => now(),
    ]);
}
