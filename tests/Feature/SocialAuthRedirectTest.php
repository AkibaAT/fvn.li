<?php

use App\Http\Controllers\SocialAuthController;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeSocialiteUser(
    string $id = 'provider-1',
    ?string $name = 'Provider User',
    ?string $email = 'provider@example.com',
    ?string $nickname = 'provider',
    ?string $avatar = 'https://example.com/avatar.png',
    array $raw = []
): SocialiteUser {
    $user = new SocialiteUser;
    $user->id = $id;
    $user->name = $name;
    $user->email = $email;
    $user->nickname = $nickname;
    $user->avatar = $avatar;
    $user->token = 'token-' . $id;
    $user->refreshToken = 'refresh-' . $id;
    $user->expiresIn = 3600;
    $user->user = $raw;

    return $user;
}

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

    $intendedUrl = route('dashboard') . '#my-games';

    $response = $this->get(route('auth.redirect', [
        'provider' => 'itchio',
        'intended' => $intendedUrl,
        'remember' => '1',
    ]));

    $response->assertRedirect('https://itch.io/user/oauth');
    $response->assertSessionHas('url.intended', $intendedUrl);
    $response->assertSessionHas('auth.remember', true);
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

test('itchio redirect ignores unsafe scheme intended urls even for the app host', function () {
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

    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $unsafeIntendedUrl = "javascript://{$appHost}/%0Aalert(document.domain)";

    $response = $this->get(route('auth.redirect', [
        'provider' => 'itchio',
        'intended' => $unsafeIntendedUrl,
    ]));

    $response->assertRedirect('https://itch.io/user/oauth');
    $response->assertSessionMissing('url.intended', $unsafeIntendedUrl);
});

test('itchio redirect ignores http intended urls even for the app host', function () {
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

    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $unsafeIntendedUrl = "http://{$appHost}/dashboard";

    $response = $this->get(route('auth.redirect', [
        'provider' => 'itchio',
        'intended' => $unsafeIntendedUrl,
    ]));

    $response->assertRedirect('https://itch.io/user/oauth');
    $response->assertSessionMissing('url.intended', $unsafeIntendedUrl);
});

test('itchio process rejects access tokens without matching oauth state', function () {
    $victim = User::factory()->create();

    Socialite::shouldReceive('driver')->never();

    $this->actingAs($victim)
        ->withSession(['state' => 'expected-state'])
        ->get(route('auth.itchio.process', [
            'hash' => http_build_query([
                'access_token' => 'attacker-token',
                'state' => 'attacker-state',
            ]),
        ]))
        ->assertRedirect(route('games.index'))
        ->assertSessionHas('error', 'Failed to authenticate with itchio');

    expect(Auth::id())->toBe($victim->id)
        ->and(SocialAccount::where('user_id', $victim->id)->where('provider_name', 'itchio')->exists())->toBeFalse();
});

test('itchio process accepts access tokens with matching oauth state', function () {
    Socialite::shouldReceive('driver')
        ->once()
        ->with('itchio')
        ->andReturn(new class
        {
            public function userFromToken(string $token): SocialiteUser
            {
                expect($token)->toBe('valid-token');

                return fakeSocialiteUser(
                    id: 'itchio-1',
                    name: 'Itch User',
                    email: null,
                    nickname: 'itch-user',
                    raw: ['username' => 'itch-user'],
                );
            }
        });

    $this->withSession(['state' => 'expected-state'])
        ->get(route('auth.itchio.process', [
            'hash' => http_build_query([
                'access_token' => 'valid-token',
                'state' => 'expected-state',
            ]),
        ]))
        ->assertRedirect(route('games.index'));

    expect(Auth::check())->toBeTrue()
        ->and(SocialAccount::where('provider_name', 'itchio')->where('provider_id', 'itchio-1')->exists())->toBeTrue();
});

test('itchio oauth logs omit access tokens and raw provider data', function () {
    Log::spy();

    Socialite::shouldReceive('driver')
        ->once()
        ->with('itchio')
        ->andReturn(new class
        {
            public function userFromToken(string $token): SocialiteUser
            {
                expect($token)->toBe('super-secret-access-token');

                $user = fakeSocialiteUser(
                    id: 'itchio-safe-log',
                    name: 'Itch User',
                    email: 'itch@example.com',
                    nickname: 'itch-user',
                    raw: [
                        'access_token' => 'raw-secret-token',
                        'email' => 'itch@example.com',
                    ],
                );
                $user->token = null;

                return $user;
            }
        });

    $this->withSession(['state' => 'expected-state'])
        ->get(route('auth.itchio.process', [
            'hash' => http_build_query([
                'access_token' => 'super-secret-access-token',
                'state' => 'expected-state',
            ]),
        ]))
        ->assertRedirect(route('games.index'));

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []) {
        return $message === 'Received itch.io OAuth callback'
            && ($context['has_access_token'] ?? false) === true
            && ! array_key_exists('token', $context);
    });

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []) {
        $encodedContext = json_encode($context);

        return $message === 'Received itch.io user profile'
            && ! array_key_exists('raw', $context)
            && ! array_key_exists('email', $context)
            && ! str_contains((string) $encodedContext, 'super-secret-access-token')
            && ! str_contains((string) $encodedContext, 'raw-secret-token');
    });
});

test('callback error logs omit raw request payloads and oauth secrets', function () {
    Log::spy();
    Socialite::shouldReceive('driver')->never();

    $this->withSession(['state' => 'expected-state'])
        ->get(route('auth.itchio.process', [
            'hash' => http_build_query([
                'access_token' => 'attacker-token',
                'state' => 'attacker-state',
            ]),
        ]))
        ->assertRedirect(route('games.index'))
        ->assertSessionHas('error', 'Failed to authenticate with itchio');

    Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context = []) {
        $encodedContext = json_encode($context);

        return str_starts_with($message, 'Social auth error with itchio:')
            && ! array_key_exists('exception', $context)
            && ! array_key_exists('request_data', $context)
            && ($context['request_keys'] ?? []) === ['hash']
            && ($context['has_oauth_hash'] ?? false) === true
            && ! str_contains($message, 'attacker-token')
            && ! str_contains((string) $encodedContext, 'attacker-token');
    });
});

test('provider callback creates a new user and social account', function () {
    Event::fake([Login::class]);

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(fakeSocialiteUser(
            id: 'google-1',
            name: 'Google Full Name',
            email: 'google@example.com',
            raw: ['given_name' => 'Google']
        ));

    $this->withSession(['url.intended' => route('dashboard')])
        ->get(route('auth.callback', ['provider' => 'google']))
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'google@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Google')
        ->and(Auth::check())->toBeTrue()
        ->and(SocialAccount::where('user_id', $user->id)
            ->where('provider_name', 'google')
            ->where('provider_id', 'google-1')
            ->exists())->toBeTrue();

    Event::assertDispatched(Login::class, fn (Login $event) => $event->user->is($user)
        && $event->remember === false);
});

test('provider callback ignores unsafe session intended urls', function () {
    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(fakeSocialiteUser(
            id: 'google-open-redirect',
            name: 'Redirect User',
            email: 'redirect@example.com'
        ));

    $this->withSession(['url.intended' => 'https://evil.example/phish?after=trusted-oauth'])
        ->get(route('auth.callback', ['provider' => 'google']))
        ->assertRedirect(route('games.index'))
        ->assertSessionMissing('url.intended');
});

test('provider callback only creates a persistent login when remember was requested', function () {
    Event::fake([Login::class]);

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(fakeSocialiteUser(
            id: 'google-remember',
            name: 'Remembered Google User',
            email: 'remembered@example.com',
            raw: ['given_name' => 'Remembered']
        ));

    $this->withSession([
        'auth.remember' => true,
        'url.intended' => route('dashboard'),
    ])
        ->get(route('auth.callback', ['provider' => 'google']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing('auth.remember');

    $user = User::where('email', 'remembered@example.com')->firstOrFail();

    Event::assertDispatched(Login::class, fn (Login $event) => $event->user->is($user)
        && $event->remember === true);
});

test('provider callback does not implicitly link to an existing user by email', function () {
    $existingUser = User::factory()->create([
        'name' => 'Existing',
        'email' => 'existing@example.com',
    ]);

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(fakeSocialiteUser(
            id: 'discord-1',
            name: 'Discord Name',
            email: 'existing@example.com',
            raw: ['global_name' => 'Global Discord']
        ));

    $this->get(route('auth.callback', ['provider' => 'discord']))
        ->assertRedirect(route('games.index'));

    $existingUser->refresh();
    expect($existingUser->name)->toBe('Existing')
        ->and($existingUser->socialAccounts()->count())->toBe(0)
        ->and(Auth::id())->not->toBe($existingUser->id);

    $newUser = Auth::user();
    expect($newUser)->not->toBeNull()
        ->and($newUser->email)->toBeNull()
        ->and($newUser->name)->toBe('Global Discord');

    $account = $newUser->socialAccounts()->where('provider_name', 'discord')->first();
    expect($account)->not->toBeNull()
        ->and($account->provider_id)->toBe('discord-1')
        ->and($account->token)->toBe('token-discord-1')
        ->and($account->refresh_token)->toBe('refresh-discord-1')
        ->and($account->token_expires_at)->not->toBeNull()
        ->and($account->toArray())->not->toHaveKeys(['token', 'refresh_token']);
});

test('explicit logged-in account merge links social account to the merging user', function () {
    $existingUser = User::factory()->create([
        'name' => 'Existing',
        'email' => 'existing@example.com',
    ]);

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(fakeSocialiteUser(
            id: 'discord-merge-1',
            name: 'Discord Name',
            email: 'existing@example.com',
            raw: ['global_name' => 'Global Discord']
        ));

    $this->actingAs($existingUser)
        ->withSession(['merging_user_id' => $existingUser->id])
        ->get(route('auth.callback', ['provider' => 'discord']))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Social account successfully linked.');

    expect($existingUser->socialAccounts()->where('provider_name', 'discord')->where('provider_id', 'discord-merge-1')->exists())->toBeTrue();
});

test('unsupported oauth providers return 404', function () {
    Socialite::shouldReceive('driver')->never();

    $this->get('/auth/unknown-provider/redirect')->assertNotFound();
    $this->get('/auth/unknown-provider/callback')->assertNotFound();
});

function postTelegramCallback(array $overrides = []): TestResponse
{
    config(['services.telegram.client_secret' => 'telegram-bot-token']);

    return test()
        ->withSession([SocialAuthController::TELEGRAM_LOGIN_SESSION_KEY => true])
        ->post(route('auth.telegram.callback'), [
            'id' => 'telegram-1',
            'first_name' => 'Tele',
            'last_name' => 'Gram',
            'username' => 'telegrammer',
            'photo_url' => 'https://example.com/telegram.png',
            'auth_date' => now()->timestamp,
            ...$overrides,
        ]);
}

function fakeTelegramUser(): SocialiteUser
{
    return fakeSocialiteUser(
        id: 'telegram-1',
        name: 'Tele Gram',
        email: null,
        nickname: 'telegrammer',
        avatar: 'https://example.com/telegram.png',
        raw: [
            'id' => 'telegram-1',
            'first_name' => 'Tele',
            'last_name' => 'Gram',
            'username' => 'telegrammer',
            'photo_url' => 'https://example.com/telegram.png',
        ],
    );
}

test('telegram callback creates account from verified provider data', function () {
    Socialite::shouldReceive('driver->user')->once()->andReturn(fakeTelegramUser());

    postTelegramCallback()->assertRedirect(route('games.index'));

    $account = SocialAccount::where('provider_name', 'telegram')
        ->where('provider_id', 'telegram-1')
        ->first();

    expect($account)->not->toBeNull()
        ->and($account->user->name)->toBe('Tele Gram')
        ->and($account->provider_data['username'])->toBe('telegrammer');
});

test('telegram callback rejects unsigned request data instead of trusting forged ids', function () {
    $victim = User::factory()->create();
    SocialAccount::factory()->for($victim)->create([
        'provider_name' => 'telegram',
        'provider_id' => 'victim-telegram-id',
    ]);

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andThrow(new RuntimeException('Invalid Telegram hash'));

    postTelegramCallback(['id' => 'victim-telegram-id'])
        ->assertRedirect(route('games.index'))
        ->assertSessionHas('error', 'Failed to authenticate with telegram');

    expect(Auth::check())->toBeFalse()
        ->and(SocialAccount::where('provider_name', 'telegram')->where('provider_id', 'victim-telegram-id')->count())->toBe(1);
});

test('telegram callback link opened by a logged in user links nothing', function () {
    config(['services.telegram.client_secret' => 'telegram-bot-token']);
    Socialite::shouldReceive('driver')->never();
    $victim = User::factory()->create();

    $this->actingAs($victim)
        ->withSession([SocialAuthController::TELEGRAM_LOGIN_SESSION_KEY => true])
        ->get(route('auth.callback', [
            'provider' => 'telegram',
            'id' => 'telegram-1',
            'auth_date' => now()->timestamp,
            'hash' => str_repeat('a', 64),
        ]))
        ->assertSessionHas('error', 'Failed to authenticate with telegram');

    expect($victim->socialAccounts()->count())->toBe(0);
});

test('telegram callback requires a login started on the telegram page', function () {
    config(['services.telegram.client_secret' => 'telegram-bot-token']);
    Socialite::shouldReceive('driver')->never();

    $this->post(route('auth.telegram.callback'), ['id' => 'telegram-1', 'auth_date' => now()->timestamp])
        ->assertSessionHas('error', 'Failed to authenticate with telegram');

    expect(Auth::check())->toBeFalse();
});

test('telegram callback rejects stale auth data', function () {
    Socialite::shouldReceive('driver')->never();

    postTelegramCallback(['auth_date' => now()->subMinutes(10)->timestamp])
        ->assertSessionHas('error', 'Failed to authenticate with telegram');

    expect(Auth::check())->toBeFalse();
});

test('telegram callback is refused when the bot token is not configured', function () {
    Socialite::shouldReceive('driver')->never();
    config(['services.telegram.client_secret' => null]);

    $this->withSession([SocialAuthController::TELEGRAM_LOGIN_SESSION_KEY => true])
        ->post(route('auth.telegram.callback'), ['id' => 'telegram-1', 'auth_date' => now()->timestamp])
        ->assertSessionHas('error', 'Failed to authenticate with telegram');

    expect(Auth::check())->toBeFalse();
});

test('telegram login page marks the session so the callback can be posted', function () {
    $this->get(route('auth.telegram'))
        ->assertOk()
        ->assertSessionHas(SocialAuthController::TELEGRAM_LOGIN_SESSION_KEY, true);
});

test('callback errors redirect to games index with a flash error', function () {
    Socialite::shouldReceive('driver->user')
        ->once()
        ->andThrow(new RuntimeException('OAuth failed'));

    $this->get(route('auth.callback', ['provider' => 'discord']))
        ->assertRedirect(route('games.index'))
        ->assertSessionHas('error', 'Failed to authenticate with discord');
});
