<?php

declare(strict_types=1);

use App\Services\FlareSolverrClient;
use App\Services\FlareSolverrSessionManager;
use App\Services\ItchAuthService;
use App\Services\ItchHttpClientFactory;
use App\Services\ItchHttpClientService;
use App\Services\ItchUrlSafetyValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['services.flaresolverr.enabled' => false]);
});

function itchClientServiceForResponses(array $responses, int $maxRetries = 0, int $baseCooldown = 0): ItchHttpClientService
{
    $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

    $factory = Mockery::mock(ItchHttpClientFactory::class);
    $factory->shouldReceive('createClient')->andReturn($client);

    $urlSafetyValidator = Mockery::mock(ItchUrlSafetyValidator::class);
    $urlSafetyValidator->shouldReceive('validate')->byDefault();
    $urlSafetyValidator->shouldReceive('isApiRequest')
        ->byDefault()
        ->andReturnUsing(fn (string $url): bool => parse_url($url, PHP_URL_HOST) === 'api.itch.io');

    return new ItchHttpClientService($factory, $urlSafetyValidator, $maxRetries, $baseCooldown);
}

it('sends anonymous GET POST PUT and DELETE requests without throwing on 4xx responses', function () {
    $service = itchClientServiceForResponses([
        new Response(200, [], '{"ok":true}'),
        new Response(201, [], '{"created":true}'),
        new Response(204, [], ''),
        new Response(404, [], 'Not found'),
    ]);

    expect($service->get('https://api.itch.io/test', [], true)->getStatusCode())->toBe(200)
        ->and($service->post('https://api.itch.io/test', ['json' => ['a' => 1]], true)->getStatusCode())->toBe(201)
        ->and($service->put('https://api.itch.io/test', ['json' => ['a' => 2]], true)->getStatusCode())->toBe(204)
        ->and($service->delete('https://api.itch.io/test', [], true)->getStatusCode())->toBe(404);
});

it('retries server errors and returns the eventual response', function () {
    $service = itchClientServiceForResponses([
        new Response(500, [], 'Server error'),
        new Response(502, [], 'Bad gateway'),
        new Response(200, [], 'Recovered'),
    ], maxRetries: 2, baseCooldown: 0);

    $response = $service->get('https://api.itch.io/test', [], true);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody()->getContents())->toBe('Recovered');
});

it('returns the final server error response after retries are exhausted', function () {
    $service = itchClientServiceForResponses([
        new Response(500, [], 'Server error'),
    ], maxRetries: 0, baseCooldown: 0);

    $response = $service->get('https://api.itch.io/test', [], true);

    expect($response->getStatusCode())->toBe(500);
});

it('throws immediately for exhausted 429 responses without sleeping', function () {
    $service = itchClientServiceForResponses([
        new Response(429, ['Retry-After' => '60'], 'Too many requests'),
    ], maxRetries: 0, baseCooldown: 0);

    expect(fn () => $service->get('https://api.itch.io/test', [], true))
        ->toThrow(Exception::class, '429 Too Many Requests');
});

it('caps remote retry after cooldown values', function () {
    $service = itchClientServiceForResponses([], maxRetries: 1, baseCooldown: 0);
    $method = new ReflectionMethod($service, 'calculateRateLimitCooldown');
    $method->setAccessible(true);

    expect($method->invoke($service, new Response(429, ['Retry-After' => '3600']), 1))->toBe(300)
        ->and($method->invoke($service, new Response(429, []), 1))->toBe(30);
});

it('throws network exceptions that are not retryable rate limits', function () {
    $service = itchClientServiceForResponses([
        new RequestException('Network error', new Request('GET', 'https://api.itch.io/test')),
    ]);

    expect(fn () => $service->get('https://api.itch.io/test', [], true))
        ->toThrow(RequestException::class, 'Network error');
});

it('uses the authenticated client by default and can invalidate Cloudflare-challenged auth', function () {
    Cache::put('itch_cookies', ['stale' => true]);

    $staleClient = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(403, ['Server' => 'cloudflare'], 'Checking your browser'),
    ]))]);
    $freshClient = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], 'Fresh auth response'),
    ]))]);

    $factory = Mockery::mock(ItchHttpClientFactory::class);
    $factory->shouldReceive('createClient')->andReturn(new Client);

    $authService = Mockery::mock(ItchAuthService::class);
    $authService->shouldReceive('getClient')->twice()->andReturn($staleClient, $freshClient);
    app()->instance(ItchAuthService::class, $authService);

    $urlSafetyValidator = Mockery::mock(ItchUrlSafetyValidator::class);
    $urlSafetyValidator->shouldReceive('validate')->byDefault();
    $urlSafetyValidator->shouldReceive('isApiRequest')
        ->byDefault()
        ->andReturnUsing(fn (string $url): bool => parse_url($url, PHP_URL_HOST) === 'api.itch.io');

    $service = new ItchHttpClientService($factory, $urlSafetyValidator, maxRetries: 0, baseCooldown: 0);
    $response = $service->get('https://api.itch.io/test');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody()->getContents())->toBe('Fresh auth response')
        ->and(Cache::has('itch_cookies'))->toBeFalse();
});

it('routes non-API HTML requests through FlareSolverr when enabled', function () {
    config(['services.flaresolverr.enabled' => true]);

    $factory = Mockery::mock(ItchHttpClientFactory::class);
    $factory->shouldReceive('createClient')->andReturn(new Client);

    $flareSolverr = Mockery::mock(FlareSolverrClient::class);
    $flareSolverr->shouldReceive('ensureSession')->once();
    $flareSolverr->shouldReceive('request')
        ->once()
        ->with('https://creator.itch.io/game', 'GET', [], null, true, null)
        ->andReturn([
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html'],
            'response' => '<html>ok</html>',
        ]);

    $sessionManager = Mockery::mock(FlareSolverrSessionManager::class);
    $sessionManager->shouldReceive('isSessionActive')->once()->andReturnFalse();

    app()->instance(FlareSolverrClient::class, $flareSolverr);
    app()->instance(FlareSolverrSessionManager::class, $sessionManager);

    $urlSafetyValidator = Mockery::mock(ItchUrlSafetyValidator::class);
    $urlSafetyValidator->shouldReceive('validate')->with('https://creator.itch.io/game')->once();
    $urlSafetyValidator->shouldReceive('isApiRequest')->with('https://creator.itch.io/game')->once()->andReturnFalse();

    $service = new ItchHttpClientService($factory, $urlSafetyValidator);
    $response = $service->get('https://creator.itch.io/game');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody()->getContents())->toBe('<html>ok</html>');
});

it('passes the active command FlareSolverr session to HTML requests', function () {
    config(['services.flaresolverr.enabled' => true]);

    $factory = Mockery::mock(ItchHttpClientFactory::class);
    $factory->shouldReceive('createClient')->andReturn(new Client);

    $flareSolverr = Mockery::mock(FlareSolverrClient::class);
    $flareSolverr->shouldNotReceive('ensureSession');
    $flareSolverr->shouldReceive('request')
        ->once()
        ->with('https://creator.itch.io/game', 'GET', [], null, true, 'games_refresh')
        ->andReturn([
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html'],
            'response' => '<html>session ok</html>',
        ]);

    $sessionManager = Mockery::mock(FlareSolverrSessionManager::class);
    $sessionManager->shouldReceive('isSessionActive')->once()->andReturnTrue();
    $sessionManager->shouldReceive('getActiveSessionId')->once()->andReturn('games_refresh');

    app()->instance(FlareSolverrClient::class, $flareSolverr);
    app()->instance(FlareSolverrSessionManager::class, $sessionManager);

    $urlSafetyValidator = Mockery::mock(ItchUrlSafetyValidator::class);
    $urlSafetyValidator->shouldReceive('validate')->with('https://creator.itch.io/game')->once();
    $urlSafetyValidator->shouldReceive('isApiRequest')->with('https://creator.itch.io/game')->once()->andReturnFalse();

    $service = new ItchHttpClientService($factory, $urlSafetyValidator);
    $response = $service->get('https://creator.itch.io/game');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody()->getContents())->toBe('<html>session ok</html>');
});

it('does not route API-like URLs through FlareSolverr', function () {
    config(['services.flaresolverr.enabled' => true]);

    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], '{"api":true}'),
    ]))]);

    $factory = Mockery::mock(ItchHttpClientFactory::class);
    $factory->shouldReceive('createClient')->andReturn($client);

    $flareSolverr = Mockery::mock(FlareSolverrClient::class);
    $flareSolverr->shouldNotReceive('request');
    app()->instance(FlareSolverrClient::class, $flareSolverr);
    app()->instance(FlareSolverrSessionManager::class, Mockery::mock(FlareSolverrSessionManager::class));

    $urlSafetyValidator = Mockery::mock(ItchUrlSafetyValidator::class);
    $urlSafetyValidator->shouldReceive('validate')->with('https://api.itch.io/games')->twice();
    $urlSafetyValidator->shouldReceive('isApiRequest')->with('https://api.itch.io/games')->once()->andReturnTrue();

    $service = new ItchHttpClientService($factory, $urlSafetyValidator);
    $response = $service->get('https://api.itch.io/games', [], true);

    expect($response->getStatusCode())->toBe(200);
});

it('rejects unsafe URLs before FlareSolverr or direct HTTP can fetch them', function (string $url) {
    config(['services.flaresolverr.enabled' => true]);

    $factory = Mockery::mock(ItchHttpClientFactory::class);
    $factory->shouldReceive('createClient')->andReturn(new Client);

    $urlSafetyValidator = Mockery::mock(ItchUrlSafetyValidator::class);
    $urlSafetyValidator->shouldReceive('validate')
        ->with($url)
        ->once()
        ->andThrow(new InvalidArgumentException('Unsafe itch URL'));
    $urlSafetyValidator->shouldNotReceive('isApiRequest');

    $flareSolverr = Mockery::mock(FlareSolverrClient::class);
    $flareSolverr->shouldNotReceive('request');
    $flareSolverr->shouldNotReceive('ensureSession');

    app()->instance(FlareSolverrClient::class, $flareSolverr);
    app()->instance(FlareSolverrSessionManager::class, Mockery::mock(FlareSolverrSessionManager::class));

    $service = new ItchHttpClientService($factory, $urlSafetyValidator);

    expect(fn () => $service->get($url, [], true))
        ->toThrow(InvalidArgumentException::class, 'Unsafe itch URL');
})->with([
    'localhost' => ['http://127.0.0.1:7777/internal-admin'],
    'docker service name' => ['http://redis:6379/'],
    'external non-itch host' => ['https://evil.example/page'],
]);

it('executes callbacks with success and error hooks', function () {
    $service = itchClientServiceForResponses([]);
    $success = [];
    $errors = [];

    expect($service->executeWithRetry(
        fn () => 'done',
        'sample operation',
        function (string $operation) use (&$success) {
            $success[] = $operation;
        }
    ))->toBe('done')
        ->and($success)->toBe(['sample operation']);

    try {
        $service->executeWithRetry(
            fn () => throw new RuntimeException('failed'),
            'failing operation',
            null,
            function (string $operation, string $message) use (&$errors) {
                $errors[] = [$operation, $message];
            }
        );

        $this->fail('Expected the failing operation to throw.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('failed');
    }

    expect($errors)->toBe([['failing operation', 'failed']]);
});

it('allows retry configuration to be updated fluently', function () {
    $service = itchClientServiceForResponses([]);

    expect($service->setMaxRetries(2))->toBe($service)
        ->and($service->setBaseCooldown(1))->toBe($service);
});

it('resolves with string retry configuration from environment-backed config', function () {
    config([
        'services.flaresolverr.enabled' => false,
        'services.itch.max_retries' => '7',
        'services.itch.retry_cooldown' => '45',
    ]);
    app()->forgetInstance(ItchHttpClientService::class);

    $service = app(ItchHttpClientService::class);
    $reflection = new ReflectionClass($service);

    expect($service)->toBeInstanceOf(ItchHttpClientService::class)
        ->and($reflection->getProperty('maxRetries')->getValue($service))->toBe(7)
        ->and($reflection->getProperty('baseCooldown')->getValue($service))->toBe(45);
});
