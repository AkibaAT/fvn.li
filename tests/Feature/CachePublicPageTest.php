<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;

beforeEach(function () {
    config(['fvn.public_page_cache_ttl' => 300]);
});

test('public pages are shared-cacheable and cookie-free for visitors without state', function () {
    $response = $this->get('/feed/new');

    $response->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('s-maxage=300')
        ->and($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

test('public pages answer a matching validator with 304', function () {
    $etag = $this->get('/feed/new')->headers->get('ETag');

    $this->withHeader('If-None-Match', $etag)->get('/feed/new')->assertStatus(304);
});

test('the appearance cookie and the default view cookie do not make a visitor stateful', function () {
    $response = $this->withUnencryptedCookies(['appearance' => 'dark', 'home_view' => 'grid'])->get('/feed/new');

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->getCookies())->toBe([]);
});

test('visitors carrying a session, token or list-view cookie get a private response', function (array $cookies) {
    $response = $this->withUnencryptedCookies($cookies)->get('/feed/new');

    $response->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('private');
})->with([
    'session cookie' => fn () => [config('session.cookie') => 'abc'],
    'xsrf cookie' => [['XSRF-TOKEN' => 'abc']],
    'remember cookie' => [['remember_web_123' => 'abc']],
]);

test('a list-view cookie keeps only the page it controls private', function () {
    $gamesListOnly = $this->withUnencryptedCookies(['games_view' => 'list'])->get('/')->headers->get('Cache-Control');
    $homeList = $this->withUnencryptedCookies(['home_view' => 'list'])->get('/')->headers->get('Cache-Control');
    $elsewhere = $this->get('/feed/new')->headers->get('Cache-Control');

    expect($gamesListOnly)->toContain('public')
        ->and($homeList)->toContain('private')
        ->and($elsewhere)->toContain('public');
});

test('signed-in users get a private response', function () {
    $response = $this->actingAs(User::factory()->create())
        ->withUnencryptedCookies([config('session.cookie') => 'abc'])
        ->get('/feed/new');

    expect($response->headers->get('Cache-Control'))->toContain('private');
});

test('inertia visits stay private so a shared cache never stores them under the page URL', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])->get('/tags');

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->getCookies())->toBe([]);
});

test('pages outside the public cache stay private', function () {
    expect($this->get('/login')->headers->get('Cache-Control'))->toContain('private');
});

test('a zero ttl keeps public pages private', function () {
    config(['fvn.public_page_cache_ttl' => 0]);

    expect($this->get('/feed/new')->headers->get('Cache-Control'))->toContain('private');
});

test('shared pages render without a csrf token or visitor appearance', function () {
    $html = $this->withUnencryptedCookies(['appearance' => 'dark'])->get('/tags')->getContent();

    expect($html)->toContain('<meta name="csrf-token" content="">')
        ->and($html)->not->toContain('class="dark"');
});

test('public pages name their cache tags for edge purging', function () {
    $game = Game::factory()->create(['is_visible' => true]);

    expect($this->get('/feed/new')->headers->get('Cache-Tag'))->toBe('games')
        ->and($this->get(route('games.show', $game))->headers->get('Cache-Tag'))->toBe("game-{$game->id}")
        ->and($this->get('/system/status')->headers->has('Cache-Tag'))->toBeFalse();
});

test('game pages are shared-cacheable for visitors without state', function () {
    $response = $this->get(route('games.show', Game::factory()->create(['is_visible' => true])));

    $response->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->getCookies())->toBe([]);
});

test('tagged pages get the edge ttl only once purging is configured', function () {
    config(['fvn.edge_cache_ttl' => 86400]);

    $unconfigured = $this->get('/feed/new')->headers->get('Cache-Control');

    config(['services.cloudflare.zone_id' => 'zone', 'services.cloudflare.api_token' => 'token']);

    expect($unconfigured)->toContain('s-maxage=300')
        ->and($this->get('/feed/new')->headers->get('Cache-Control'))->toContain('s-maxage=86400')
        ->and($this->get('/system/status')->headers->get('Cache-Control'))->toContain('s-maxage=300');
});
