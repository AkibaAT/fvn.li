<?php

use App\Http\Middleware\PreventRequestForgery;
use App\Models\Game;
use App\Models\Tag;
use App\Observers\TagObserver;
use App\Services\FlareSolverrSessionManager;
use App\Services\ItchIoProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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

function executeZeroCoverageCommandWithoutRealFlareSolverr(): void
{
    $sessionManager = Mockery::mock(FlareSolverrSessionManager::class);
    $sessionManager->shouldReceive('executeWithSession')
        ->byDefault()
        ->andReturnUsing(fn (string $commandName, callable $callback): mixed => $callback());

    app()->instance(FlareSolverrSessionManager::class, $sessionManager);
}

test('refresh feedless games command validates selection and handles empty selections', function () {
    executeZeroCoverageCommandWithoutRealFlareSolverr();

    $this
        ->artisan('games:refresh-feedless')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);

    Game::factory()->create([
        'name' => 'Not Feedless',
        'platform' => 'itch_io',
        'is_visible' => true,
        'is_feedless' => false,
    ]);

    $this
        ->artisan('games:refresh-feedless --all')
        ->expectsOutput('Starting version refresh for feedless games')
        ->expectsOutput('No games found matching the selection criteria')
        ->assertExitCode(1);
});

test('prevent request forgery keeps api exclusions and bypasses browser api only in testing', function () {
    $middleware = new PreventRequestForgery(app(), app('encrypter'));
    $method = new ReflectionMethod($middleware, 'inExceptArray');
    $method->setAccessible(true);

    expect($method->invoke($middleware, Request::create('/browser-api/games/1/reviews', 'POST')))->toBeTrue()
        ->and($method->invoke($middleware, Request::create('/api/game-reviews', 'POST')))->toBeTrue()
        ->and($method->invoke($middleware, Request::create('/dashboard', 'POST')))->toBeFalse();
});

test('tag observer refreshes visible related games and bumps recommendation cache version', function () {
    Config::set('scout.driver', null);
    Cache::put('games.recommendations.version', 0);
    Log::spy();

    $tag = Tag::create(['name' => 'Old Tag']);
    $visibleGame = Game::factory()->create(['is_visible' => true]);
    $hiddenGame = Game::factory()->create(['is_visible' => false]);
    $tag->games()->attach([$visibleGame->id, $hiddenGame->id]);

    $initialVersion = Cache::get('games.recommendations.version');

    $tag->name = 'New Tag';
    app(TagObserver::class)->updated($tag);

    expect(Cache::get('games.recommendations.version'))->toBe($initialVersion + 1);
    Log::shouldHaveReceived('info')
        ->with('Updated game search indexes for tag change', Mockery::on(
            fn (array $context) => $context['tag_id'] === $tag->id && $context['tag_name'] === 'New Tag'
        ))
        ->atLeast()
        ->once();

    app(TagObserver::class)->deleted($tag);
    expect(Cache::get('games.recommendations.version'))->toBe($initialVersion + 2);
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
