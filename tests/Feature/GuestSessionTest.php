<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;
use App\Models\User;

function cookieNames($response): array
{
    return collect($response->headers->getCookies())->map->getName()->all();
}

test('anonymous browsing sets no session or csrf cookie', function () {
    $response = $this->get(route('users.reviews', User::factory()->create()));

    $response->assertOk();

    expect(cookieNames($response))->not->toContain(config('session.cookie'))->not->toContain('XSRF-TOKEN');
});

test('a guest gets a session once it holds state, such as the page to return to after login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));

    expect(cookieNames($response))->toContain(config('session.cookie'))->toContain('XSRF-TOKEN');
});

test('signed-in users keep their session and csrf cookies', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('developers'));

    expect(cookieNames($response))->toContain(config('session.cookie'))->toContain('XSRF-TOKEN');
});

test('a guest whose session no longer holds state has the cookie removed', function () {
    $response = $this->withUnencryptedCookies([config('session.cookie') => str_repeat('a', 40)])
        ->get(route('users.reviews', User::factory()->create()));

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie->getExpiresTime())->toBeLessThan(time());
});

test('anonymous link clicks share one visitor key per day without a session', function () {
    $game = Game::factory()->create(['is_visible' => true, 'url' => ['itch_io' => 'https://example.itch.io/game']]);

    $this->get(route('track.external-project', ['game_id' => $game->id]));
    $response = $this->get(route('track.external-project', ['game_id' => $game->id]));

    $keys = ClickStat::where('game_id', $game->id)->pluck('session_id')->unique();

    expect($keys)->toHaveCount(1)
        ->and($keys->first())->toStartWith('v_')
        ->and(cookieNames($response))->not->toContain(config('session.cookie'));
});
