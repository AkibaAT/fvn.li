<?php

declare(strict_types=1);

use App\Services\FlareSolverrClient;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function flareSolverrClientWithResponses(array $responses, array &$history = []): FlareSolverrClient
{
    config([
        'services.flaresolverr.url' => 'http://flaresolverr.test',
        'services.flaresolverr.max_timeout' => 12345,
    ]);

    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    $service = new FlareSolverrClient;
    $reflection = new ReflectionClass($service);
    $client = $reflection->getProperty('client');
    $client->setValue($service, new Client(['handler' => $stack]));

    return $service;
}

it('sends requests through FlareSolverr and updates the supplied cookie jar', function () {
    $history = [];
    $service = flareSolverrClientWithResponses([
        new Response(200, [], json_encode([
            'status' => 'ok',
            'solution' => [
                'status' => 200,
                'headers' => ['content-type' => 'text/html'],
                'response' => '<html>ok</html>',
                'userAgent' => 'agent',
                'cookies' => [[
                    'name' => 'itchio',
                    'value' => 'session-cookie',
                    'domain' => 'itch.io',
                    'path' => '/',
                ]],
            ],
        ])),
    ], $history);
    $cookieJar = new CookieJar;

    $result = $service->request('https://itch.io/login', 'POST', ['username' => 'me'], $cookieJar, false);

    expect($result['status'])->toBe(200)
        ->and($result['response'])->toBe('<html>ok</html>')
        ->and($result['userAgent'])->toBe('agent')
        ->and($cookieJar->getCookieByName('itchio')?->getValue())->toBe('session-cookie');

    $payload = json_decode((string) $history[0]['request']->getBody(), true);
    expect($payload['cmd'])->toBe('request.post')
        ->and($payload['url'])->toBe('https://itch.io/login')
        ->and($payload['maxTimeout'])->toBe(12345)
        ->and($payload['postData'])->toBe('username=me')
        ->and($payload)->not->toHaveKey('session');
});

it('serializes an explicit session override in request payloads', function () {
    $history = [];
    $service = flareSolverrClientWithResponses([
        new Response(200, [], json_encode([
            'status' => 'ok',
            'solution' => [
                'status' => 200,
                'headers' => [],
                'response' => '<html>ok</html>',
            ],
        ])),
    ], $history);

    $service->request('https://itch.io/game', sessionId: 'games_refresh');

    $payload = json_decode((string) $history[0]['request']->getBody(), true);
    expect($payload['session'])->toBe('games_refresh');
});

it('throws wrapped exceptions for failed requests and CAPTCHA responses', function () {
    $service = flareSolverrClientWithResponses([
        new Response(200, [], json_encode([
            'status' => 'error',
            'message' => 'captcha required',
        ])),
    ]);

    expect(fn () => $service->request('https://itch.io/protected'))
        ->toThrow(Exception::class, 'FlareSolverr error: FlareSolverr cannot solve CAPTCHA');

    $service = flareSolverrClientWithResponses([
        new Response(200, [], json_encode([
            'status' => 'ok',
            'solution' => ['response' => '<div class="g-recaptcha"></div>'],
        ])),
    ]);

    expect(fn () => $service->request('https://itch.io/protected'))
        ->toThrow(Exception::class, 'FlareSolverr error: FlareSolverr encountered an unsolvable CAPTCHA');
});

it('creates lists reuses and destroys sessions', function () {
    $history = [];
    $service = flareSolverrClientWithResponses([
        new Response(200, [], json_encode(['status' => 'ok', 'session' => 'session-1'])),
        new Response(200, [], json_encode(['status' => 'ok', 'sessions' => ['session-1', 'session-2']])),
        new Response(200, [], json_encode(['status' => 'ok', 'sessions' => ['session-1']])),
        new Response(200, [], json_encode(['status' => 'ok', 'sessions' => ['session-1']])),
        new Response(200, [], json_encode(['status' => 'ok'])),
        new Response(200, [], json_encode(['status' => 'ok'])),
    ], $history);

    expect($service->createSession('requested-session'))->toBe('session-1')
        ->and($service->listSessions())->toBe(['session-1', 'session-2']);

    $service->destroySession('missing-session');
    $service->destroySession('session-1');

    expect($service->getSessionId())->toBe('');

    $commands = array_map(
        fn ($entry) => json_decode((string) $entry['request']->getBody(), true)['cmd'],
        $history
    );
    expect($commands)->toBe([
        'sessions.create',
        'sessions.list',
        'sessions.list',
        'sessions.list',
        'sessions.destroy',
        'sessions.create',
    ]);
});

it('reports availability from the health endpoint and handles failures', function () {
    $service = flareSolverrClientWithResponses([
        new Response(200, [], 'ok'),
    ]);
    expect($service->isAvailable())->toBeTrue();

    $service = flareSolverrClientWithResponses([
        new Response(503, [], 'down'),
    ]);
    expect($service->isAvailable())->toBeFalse();
});
