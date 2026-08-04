<?php

declare(strict_types=1);

use App\Services\FlareSolverrClient;
use App\Services\ItchAuthService;
use App\Services\ItchHttpClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    config([
        'services.itch.username' => 'test-user',
        'services.itch.password' => 'test-password',
    ]);
    $this->factory = new ItchHttpClientFactory;
});

function itchioLoginFormHtml(string $csrf = 'test-csrf-token'): string
{
    return <<<HTML
        <html>
            <body>
                <div class="login_form_widget">
                    <form class="form">
                        <input type="hidden" name="csrf_token" value="{$csrf}">
                        <input type="text" name="username" value="">
                        <input type="password" name="password" value="">
                    </form>
                </div>
            </body>
        </html>
    HTML;
}

function itchioLoginFlareSolverr(string $html, int $loginStatus = 302, ?int $initialVerifyStatus = null): FlareSolverrClient
{
    $flareSolverr = Mockery::mock(FlareSolverrClient::class);

    if ($initialVerifyStatus !== null) {
        $flareSolverr->shouldReceive('request')
            ->once()
            ->with('https://itch.io/dashboard', 'GET', [], Mockery::type(CookieJar::class))
            ->andReturn([
                'status' => $initialVerifyStatus,
                'response' => '',
                'cookies' => [],
            ]);
    }

    $flareSolverr->shouldReceive('request')
        ->once()
        ->with('https://itch.io/login', 'GET', [], Mockery::type(CookieJar::class))
        ->andReturn([
            'status' => 200,
            'response' => $html,
            'cookies' => [],
        ]);
    $flareSolverr->shouldReceive('request')
        ->once()
        ->with(
            'https://itch.io/login',
            'POST',
            Mockery::on(fn (array $formData): bool => ($formData['username'] ?? null) === 'test-user'
                && ($formData['password'] ?? null) === 'test-password'),
            Mockery::type(CookieJar::class)
        )
        ->andReturn([
            'status' => $loginStatus,
            'response' => '',
            'cookies' => [],
        ]);

    if ($loginStatus === 200 || $loginStatus === 302) {
        $flareSolverr->shouldReceive('request')
            ->once()
            ->with('https://itch.io/dashboard', 'GET', [], Mockery::type(CookieJar::class))
            ->andReturn([
                'status' => 200,
                'response' => '<html>Dashboard</html>',
                'cookies' => [],
            ]);
    }

    return $flareSolverr;
}

function itchioSessionVerificationFlareSolverr(int $status): FlareSolverrClient
{
    $flareSolverr = Mockery::mock(FlareSolverrClient::class);
    $flareSolverr->shouldReceive('request')
        ->once()
        ->with('https://itch.io/dashboard', 'GET', [], Mockery::type(CookieJar::class))
        ->andReturn([
            'status' => $status,
            'response' => '',
            'cookies' => [],
        ]);

    return $flareSolverr;
}

describe('ItchAuthService authentication', function () {
    test('successfully authenticates with valid credentials', function () {
        $mockHandler = new MockHandler([]);

        $handlerStack = HandlerStack::create($mockHandler);
        $cookieJar = new CookieJar;
        $client = new Client([
            'handler' => $handlerStack,
            'cookies' => $cookieJar,
        ]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn($cookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory, itchioLoginFlareSolverr(itchioLoginFormHtml()));

        expect($service->getClient())->toBeInstanceOf(Client::class);
    });

    test('throws exception when authentication fails', function () {
        $mockHandler = new MockHandler([]);

        $handlerStack = HandlerStack::create($mockHandler);
        $cookieJar = new CookieJar;
        $client = new Client([
            'handler' => $handlerStack,
            'cookies' => $cookieJar,
        ]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn($cookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory, itchioLoginFlareSolverr(itchioLoginFormHtml(), 401));

        expect(fn () => $service->getClient())
            ->toThrow(RuntimeException::class, 'Failed to authenticate with itch.io');
    });

    test('uses cached cookies when available', function () {
        $cachedCookies = [
            [
                'Name' => 'itchio',
                'Value' => 'test-session',
                'Domain' => 'itch.io',
                'Path' => '/',
                'Expires' => time() + 3600,
            ],
        ];
        Cache::put('itch_cookies', $cachedCookies, now()->addWeek());

        $mockHandler = new MockHandler([
            // Verify session is valid
            new Response(200, [], '<html>Dashboard</html>'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $cookieJar = new CookieJar;
        $client = new Client([
            'handler' => $handlerStack,
            'cookies' => $cookieJar,
        ]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn($cookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory, itchioSessionVerificationFlareSolverr(200));

        expect($service->getClient())->toBeInstanceOf(Client::class);
    });

    test('clears invalid cached cookies and re-authenticates', function () {
        $cachedCookies = [
            [
                'Name' => 'itchio',
                'Value' => 'invalid-session',
                'Domain' => 'itch.io',
                'Path' => '/',
                'Expires' => time() + 3600,
            ],
        ];
        Cache::put('itch_cookies', $cachedCookies, now()->addWeek());

        $mockHandler = new MockHandler([]);

        $handlerStack = HandlerStack::create($mockHandler);
        $cookieJar = new CookieJar;
        $client = new Client([
            'handler' => $handlerStack,
            'cookies' => $cookieJar,
        ]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn($cookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory, itchioLoginFlareSolverr(itchioLoginFormHtml(), initialVerifyStatus: 302));

        expect($service->getClient())->toBeInstanceOf(Client::class)
            ->and(Cache::has('itch_cookies'))->toBeTrue();
    });
});

describe('ItchAuthService game ID extraction', function () {
    test('extracts game ID from game page', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], '<html><meta name="itch:path" content="games/12345"></html>'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn(new CookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory);

        expect($service->getGameId('https://example.itch.io/game'))->toBe(12345);
    });

    test('throws exception when game ID cannot be found', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], '<html>No game ID here</html>'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn(new CookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory);

        expect(fn () => $service->getGameId('https://example.itch.io/game'))
            ->toThrow(RuntimeException::class, 'Could not find game ID');
    });
});

describe('ItchAuthService CSRF token extraction', function () {
    test('extracts CSRF token from meta content attribute', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], '<html><meta name="csrf_token" content="test-csrf-123"></html>'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn(new CookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory);

        expect($service->getCsrfToken())->toBe('test-csrf-123');
    });

    test('falls back to CSRF token meta value attribute', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], '<html><meta name="csrf_token" value="fallback-csrf-123"></html>'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn(new CookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory);

        expect($service->getCsrfToken())->toBe('fallback-csrf-123');
    });

    test('returns null when CSRF token not found', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], '<html>No CSRF token here</html>'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn(new CookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory);

        expect($service->getCsrfToken())->toBeNull();
    });
});
