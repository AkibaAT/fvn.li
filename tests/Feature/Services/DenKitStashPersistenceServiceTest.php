<?php

declare(strict_types=1);

use App\Services\DenKitStashPersistenceService;
use App\Services\GameArchiveService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;

function invokeDenKitStashMethod(DenKitStashPersistenceService $service, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($service);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($service, $arguments);
}

it('requires explicit opt in before enabling DenKit Stash auto persist', function () {
    Config::set('services.denkit_stash.enabled', true);
    Config::set('services.denkit_stash.api_key', 'secret-key');
    Config::set('services.denkit_stash.auto_persist', false);

    $service = new DenKitStashPersistenceService(Mockery::mock(GameArchiveService::class));

    expect($service->isEnabled())->toBeTrue()
        ->and($service->isAutoPersistEnabled())->toBeFalse();

    Config::set('services.denkit_stash.auto_persist', true);

    expect($service->isAutoPersistEnabled())->toBeTrue();
});

it('looks up existing DenKit Stash builds through the HTTP API', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode([
            'build' => [
                'id' => 456,
                'upload_id' => 123,
                'user_version' => '1.2.3',
                'state' => 'completed',
                'created_at' => '2026-05-23T10:00:00Z',
            ],
        ])),
    ]));
    $handlerStack->push(Middleware::history($history));

    $service = new DenKitStashPersistenceService(
        Mockery::mock(GameArchiveService::class),
        new Client(['handler' => $handlerStack])
    );

    expect(invokeDenKitStashMethod($service, 'latestBuildId', ['fvn-li', 'dawn-chorus', 'main', '1.2.3']))->toBe(456);

    expect($history)->toHaveCount(1);
    $request = $history[0]['request'];
    parse_str($request->getUri()->getQuery(), $query);

    expect((string) $request->getUri())->toContain('https://stash.example/wharf/builds/latest')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer secret-key')
        ->and($query)->toMatchArray([
            'target' => 'fvn-li/dawn-chorus',
            'channel' => 'main',
            'user_version' => '1.2.3',
        ]);
});

it('treats missing DenKit Stash builds as absent archives', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $service = new DenKitStashPersistenceService(
        Mockery::mock(GameArchiveService::class),
        new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(404, [], '{"errors":["build not found"]}'),
        ]))])
    );

    expect(invokeDenKitStashMethod($service, 'latestBuildId', ['fvn-li', 'missing', 'main', '9.9.9']))->toBeNull();
});
