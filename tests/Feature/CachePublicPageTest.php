<?php

declare(strict_types=1);

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

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and(collect($response->headers->getCookies())->map->getName()->all())->toContain(config('session.cookie'));
})->with([
    'session cookie' => fn () => [config('session.cookie') => 'abc'],
    'xsrf cookie' => [['XSRF-TOKEN' => 'abc']],
    'remember cookie' => [['remember_web_123' => 'abc']],
    'list view cookie' => [['games_view' => 'list']],
]);

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

test('pages outside the public cache keep their session', function () {
    $response = $this->get('/login');

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and(collect($response->headers->getCookies())->map->getName()->all())->toContain(config('session.cookie'));
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
