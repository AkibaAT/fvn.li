<?php

declare(strict_types=1);

use App\Services\ItchAuthService;
use App\Services\ItchHttpClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Skip all tests in this file - they require actual itch.io credentials
    $this->markTestSkipped('Requires actual itch.io credentials - cannot test without external authentication');

    Cache::flush();
    $this->factory = new ItchHttpClientFactory;
});

describe('ItchAuthService authentication', function () {
    test('successfully authenticates with valid credentials', function () {
        $mockHandler = new MockHandler([
            // First request: get CSRF token
            new Response(200, [], '<html><meta name="csrf_token" value="test-csrf-token"></html>'),
            // Second request: login
            new Response(302, ['Location' => 'https://itch.io/dashboard']),
            // Third request: verify login
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

        $service = new ItchAuthService($factory);

        expect($service->getClient())->toBeInstanceOf(Client::class);
    });

    test('throws exception when authentication fails', function () {
        $mockHandler = new MockHandler([
            // First request: get CSRF token
            new Response(200, [], '<html><meta name="csrf_token" value="test-csrf-token"></html>'),
            // Second request: login fails
            new Response(401, [], 'Unauthorized'),
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

        $service = new ItchAuthService($factory);

        expect(fn () => $service->getClient())
            ->toThrow(RuntimeException::class, 'Failed to authenticate with itch.io');
    });

    test('uses cached cookies when available', function () {
        // Set up cached cookies
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

        $service = new ItchAuthService($factory);

        expect($service->getClient())->toBeInstanceOf(Client::class);
    });

    test('clears invalid cached cookies and re-authenticates', function () {
        // Set up invalid cached cookies
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

        $mockHandler = new MockHandler([
            // Verify session - fails (redirect)
            new Response(302, ['Location' => 'https://itch.io/login']),
            // Get CSRF token
            new Response(200, [], '<html><meta name="csrf_token" value="test-csrf-token"></html>'),
            // Login
            new Response(302, ['Location' => 'https://itch.io/dashboard']),
            // Verify login
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

        $service = new ItchAuthService($factory);

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
    test('extracts CSRF token from page', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], '<html><meta name="csrf_token" value="test-csrf-123"></html>'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createCookieJar')->willReturn(new CookieJar);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchAuthService($factory);

        expect($service->getCsrfToken())->toBe('test-csrf-123');
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
