<?php

use Laravel\Socialite\Facades\Socialite;

test('itchio redirect uses a normal external redirect and stores explicit intended url', function () {
    Socialite::shouldReceive('driver')
        ->once()
        ->with('itchio')
        ->andReturn(new class
        {
            public function setScopes(array $scopes): static
            {
                return $this;
            }

            public function redirect()
            {
                return redirect()->away('https://itch.io/user/oauth');
            }
        });

    $intendedUrl = route('dashboard').'#my-games';

    $response = $this->get(route('auth.redirect', [
        'provider' => 'itchio',
        'intended' => $intendedUrl,
    ]));

    $response->assertRedirect('https://itch.io/user/oauth');
    $response->assertSessionHas('url.intended', $intendedUrl);
});

test('itchio redirect ignores unsafe external intended urls', function () {
    Socialite::shouldReceive('driver')
        ->once()
        ->with('itchio')
        ->andReturn(new class
        {
            public function setScopes(array $scopes): static
            {
                return $this;
            }

            public function redirect()
            {
                return redirect()->away('https://itch.io/user/oauth');
            }
        });

    $response = $this->get(route('auth.redirect', [
        'provider' => 'itchio',
        'intended' => 'https://example.com/phishing',
    ]));

    $response->assertRedirect('https://itch.io/user/oauth');
    $response->assertSessionMissing('url.intended', 'https://example.com/phishing');
});
