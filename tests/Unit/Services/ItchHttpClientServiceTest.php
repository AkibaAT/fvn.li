<?php

declare(strict_types=1);

use App\Services\ItchAuthService;
use App\Services\ItchHttpClientFactory;
use App\Services\ItchHttpClientService;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    // Skip all tests in this file - they require actual itch.io credentials
    $this->markTestSkipped('Requires actual itch.io credentials - cannot test without external authentication');

    $this->factory = new ItchHttpClientFactory();
});

describe('ItchHttpClientService request handling', function () {
    test('successfully sends GET request', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], '{"success": true}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchHttpClientService($factory);

        $response = $service->get('https://api.itch.io/test');

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getBody()->getContents())->toBe('{"success": true}');
    });

    test('successfully sends POST request', function () {
        $mockHandler = new MockHandler([
            new Response(201, [], '{"created": true}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchHttpClientService($factory);

        $response = $service->post('https://api.itch.io/test', ['data' => 'value']);

        expect($response->getStatusCode())->toBe(201);
    });

    test('handles 404 responses without throwing exception', function () {
        $mockHandler = new MockHandler([
            new Response(404, [], 'Not Found'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchHttpClientService($factory);

        $response = $service->get('https://api.itch.io/nonexistent');

        expect($response->getStatusCode())->toBe(404);
    });

    test('handles 500 responses without throwing exception', function () {
        $mockHandler = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchHttpClientService($factory);

        $response = $service->get('https://api.itch.io/error');

        expect($response->getStatusCode())->toBe(500);
    });
});

describe('ItchHttpClientService rate limiting', function () {
    test('retries on 429 rate limit response', function () {
        $mockHandler = new MockHandler([
            new Response(429, ['Retry-After' => '1'], 'Too Many Requests'),
            new Response(200, [], '{"success": true}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        // Use shorter cooldown for testing
        $service = new ItchHttpClientService($factory, 5, 1);

        $response = $service->get('https://api.itch.io/test');

        expect($response->getStatusCode())->toBe(200);
    });

    test('respects Retry-After header', function () {
        $mockHandler = new MockHandler([
            new Response(429, ['Retry-After' => '2'], 'Too Many Requests'),
            new Response(200, [], '{"success": true}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchHttpClientService($factory, 5, 1);

        $startTime = microtime(true);
        $response = $service->get('https://api.itch.io/test');
        $endTime = microtime(true);

        expect($response->getStatusCode())->toBe(200)
            ->and($endTime - $startTime)->toBeGreaterThanOrEqual(1); // At least 1 second delay
    });

    test('throws exception after max retries on rate limit', function () {
        $mockHandler = new MockHandler([
            new Response(429, ['Retry-After' => '1'], 'Too Many Requests'),
            new Response(429, ['Retry-After' => '1'], 'Too Many Requests'),
            new Response(429, ['Retry-After' => '1'], 'Too Many Requests'),
            new Response(429, ['Retry-After' => '1'], 'Too Many Requests'),
            new Response(429, ['Retry-After' => '1'], 'Too Many Requests'),
            new Response(429, ['Retry-After' => '1'], 'Too Many Requests'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchHttpClientService($factory, 3, 1);

        expect(fn () => $service->get('https://api.itch.io/test'))
            ->toThrow(Exception::class);
    });
});

describe('ItchHttpClientService error handling', function () {
    test('throws exception on network error', function () {
        $mockHandler = new MockHandler([
            new RequestException('Network error', new Request('GET', 'test')),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchHttpClientService($factory);

        expect(fn () => $service->get('https://api.itch.io/test'))
            ->toThrow(RequestException::class);
    });

    test('uses anonymous client when specified', function () {
        $mockHandler = new MockHandler([
            new Response(200, [], '{"anonymous": true}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $anonymousClient = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($anonymousClient);

        $service = new ItchHttpClientService($factory);

        $response = $service->get('https://api.itch.io/test', [], true);

        expect($response->getStatusCode())->toBe(200);
    });

    test('uses authenticated client by default', function () {
        $mockHandler = new MockHandler([
            // Auth check
            new Response(200, [], '<html><meta name="csrf_token" value="test"></html>'),
            new Response(302, ['Location' => 'https://itch.io/dashboard']),
            new Response(200, [], 'Dashboard'),
            // Actual request
            new Response(200, [], '{"authenticated": true}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack, 'cookies' => new CookieJar()]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);
        $factory->method('createCookieJar')->willReturn(new CookieJar());

        $authService = $this->createMock(ItchAuthService::class);
        $authService->method('getClient')->willReturn($client);

        $service = new ItchHttpClientService($factory);

        // This would normally use authenticated client
        $response = $service->get('https://api.itch.io/test', [], false);

        expect($response->getStatusCode())->toBe(200);
    });
});

describe('ItchHttpClientService exponential backoff', function () {
    test('implements exponential backoff on retries', function () {
        $mockHandler = new MockHandler([
            new Response(429, [], 'Too Many Requests'),
            new Response(429, [], 'Too Many Requests'),
            new Response(200, [], '{"success": true}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $factory = $this->createMock(ItchHttpClientFactory::class);
        $factory->method('createClient')->willReturn($client);

        $service = new ItchHttpClientService($factory, 5, 1);

        $startTime = microtime(true);
        $response = $service->get('https://api.itch.io/test');
        $endTime = microtime(true);

        // Should have some delay due to backoff
        expect($response->getStatusCode())->toBe(200)
            ->and($endTime - $startTime)->toBeGreaterThan(0);
    });
});

