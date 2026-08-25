<?php

use App\Services\ItchIoProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use SocialiteProviders\Manager\Config as SocialiteConfig;
use Tests\Support\TestableItchIoProvider;

function testableItchProvider(?MockHandler $mockHandler = null): TestableItchIoProvider
{
    $provider = new TestableItchIoProvider(
        Request::create('/auth/itchio/redirect'),
        'client-id',
        'client-secret',
        route('auth.itchio.callback')
    );

    if ($mockHandler) {
        $provider->setHttpClient(new Client([
            'handler' => HandlerStack::create($mockHandler),
        ]));
    }

    return $provider;
}

test('itch io callback page renders without metadata', function () {
    $this->get(route('auth.itchio.callback'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/itchio-callback')
            ->missing('metaTags'));
});

test('itch io provider exposes implicit oauth fields and maps api users', function () {
    $provider = testableItchProvider();

    expect(ItchIoProvider::additionalConfigKeys())->toBe(['client_id', 'client_secret', 'redirect'])
        ->and($provider->getAccessTokenResponse('token-123'))->toBe([
            'access_token' => 'token-123',
            'token_type' => 'Bearer',
        ])
        ->and($provider->setConfig(new SocialiteConfig('config-client', 'config-secret', 'https://callback.example'))->exposedTokenUrl())
        ->toBe('https://itch.io/api/v1/oauth/token');

    $authUrl = $provider->exposedAuthUrl('state-123');
    expect($authUrl)->toContain('https://itch.io/user/oauth')
        ->and($authUrl)->toContain('client_id=client-id')
        ->and($authUrl)->toContain('state=state-123');

    expect($provider->exposedTokenFields('code-123'))->toMatchArray([
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'code' => 'code-123',
        'redirect_uri' => route('auth.itchio.callback'),
        'grant_type' => 'authorization_code',
    ]);

    expect($provider->exposedCodeFields('state-456'))->toMatchArray([
        'client_id' => 'client-id',
        'redirect_uri' => route('auth.itchio.callback'),
        'response_type' => 'token',
        'scope' => 'profile:me profile:games',
        'state' => 'state-456',
    ]);

    $user = $provider->exposedMapUser([
        'id' => 123,
        'username' => 'itch-dev',
        'display_name' => 'Itch Dev',
        'cover_url' => 'https://img.example/avatar.png',
    ]);

    expect($user->getId())->toBe(123)
        ->and($user->getNickname())->toBe('itch-dev')
        ->and($user->getName())->toBe('Itch Dev')
        ->and($user->getEmail())->toBeNull()
        ->and($user->getAvatar())->toBe('https://img.example/avatar.png');
});

test('itch io provider fetches users by token and raises api errors', function () {
    $successProvider = testableItchProvider(new MockHandler([
        new GuzzleResponse(200, [], json_encode([
            'user' => [
                'id' => 456,
                'username' => 'api-dev',
            ],
        ], JSON_THROW_ON_ERROR)),
    ]));

    expect($successProvider->exposedUserByToken('access-token'))->toMatchArray([
        'id' => 456,
        'username' => 'api-dev',
    ]);

    $errorProvider = testableItchProvider(new MockHandler([
        new GuzzleResponse(200, [], json_encode([
            'errors' => ['bad token', 'expired'],
        ], JSON_THROW_ON_ERROR)),
    ]));

    expect(fn () => $errorProvider->exposedUserByToken('bad-token'))
        ->toThrow(Exception::class, 'itch.io API error: bad token, expired');
});
