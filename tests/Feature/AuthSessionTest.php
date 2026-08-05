<?php

use App\Models\User;

function authSessionInertiaHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
    ];
}

test('guest login renders the Svelte login page and stores the intended URL', function () {
    $previousUrl = route('games.index');

    $response = $this
        ->withHeaders([
            ...authSessionInertiaHeaders(),
            'Referer' => $previousUrl,
        ])
        ->get(route('login'));

    $response->assertOk()
        ->assertJsonPath('component', 'auth/login')
        ->assertSessionHas('url.intended', $previousUrl);
});

test('guest login ignores external referer URLs as intended destinations', function () {
    $response = $this
        ->withHeaders([
            ...authSessionInertiaHeaders(),
            'Referer' => 'https://evil.example/phish?after=login',
        ])
        ->get(route('login'));

    $response->assertOk()
        ->assertJsonPath('component', 'auth/login')
        ->assertSessionMissing('url.intended');
});

test('authenticated login route returns the home page instead of login', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withHeaders(authSessionInertiaHeaders())
        ->get(route('login'));

    $response->assertOk()
        ->assertJsonPath('component', 'home');
});

test('logout invalidates the session and redirects back to the previous page', function () {
    $user = User::factory()->create();
    $previousUrl = route('games.index');

    $response = $this
        ->actingAs($user)
        ->withHeader('Referer', $previousUrl)
        ->post(route('logout'));

    $response->assertRedirect($previousUrl);
    $this->assertGuest();
});
