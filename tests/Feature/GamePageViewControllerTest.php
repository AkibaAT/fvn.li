<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;

const VISITOR_ID = '6f1c1c3a-2f0e-4b8e-9d55-0b2f3d1e8a11';

test('a page view is recorded under the visitor id without starting a session', function () {
    $game = Game::factory()->create(['is_visible' => true]);

    $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0')
        ->postJson("/api/games/{$game->id}/view", ['visitor_id' => VISITOR_ID, 'referrer' => 'https://example.com/']);

    $response->assertNoContent();

    $view = ClickStat::where('game_id', $game->id)->sole();

    expect($view->type)->toBe(ClickStat::TYPE_PAGE_VIEW)
        ->and($view->session_id)->toBe(VISITOR_ID)
        ->and($view->user_id)->toBeNull()
        ->and($view->referrer)->toBe('https://example.com/')
        ->and($view->bot_reason)->toBeNull()
        ->and($response->headers->getCookies())->toBe([]);
});

test('a page view needs a uuid visitor id', function (mixed $visitorId) {
    $game = Game::factory()->create(['is_visible' => true]);

    $this->postJson(route('api.games.view', $game), ['visitor_id' => $visitorId])->assertUnprocessable();

    expect(ClickStat::count())->toBe(0);
})->with([null, 'anonymized_123', 'not-a-uuid']);

test('hidden games do not record page views', function () {
    $game = Game::factory()->create(['is_visible' => false]);

    $this->postJson(route('api.games.view', $game), ['visitor_id' => VISITOR_ID])->assertNotFound();

    expect(ClickStat::count())->toBe(0);
});

test('rendering a game page records no view on its own', function () {
    $game = Game::factory()->create(['is_visible' => true]);

    $this->get(route('games.show', $game))->assertOk();

    expect(ClickStat::count())->toBe(0);
});
