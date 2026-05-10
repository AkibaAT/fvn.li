<?php

declare(strict_types=1);

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

afterEach(function () {
    SymfonyRequest::setTrustedProxies([], -1);
    TrustProxies::flushState();
});

function requestThroughTrustProxies(array $server = [], array $headers = []): Request
{
    $request = Request::create(
        'http://fvn.test/proxy-check',
        'GET',
        server: array_merge([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_HOST' => 'fvn.test',
        ], $server)
    );

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    app(TrustProxies::class)->handle($request, fn () => new Response('ok'));

    return $request;
}

it('ignores spoofed forwarded headers by default', function () {
    config(['trustedproxy.proxies' => null]);

    $request = requestThroughTrustProxies(headers: [
        'X-Forwarded-For' => '198.51.100.77',
        'X-Forwarded-Host' => 'evil.example',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Port' => '444',
    ]);

    expect($request->ip())->toBe('203.0.113.10')
        ->and($request->getHost())->toBe('fvn.test')
        ->and($request->getScheme())->toBe('http')
        ->and($request->getPort())->toBe(80);
});

it('uses forwarded headers only from configured trusted proxies', function () {
    config(['trustedproxy.proxies' => ['203.0.113.10']]);

    $request = requestThroughTrustProxies(headers: [
        'X-Forwarded-For' => '198.51.100.77',
        'X-Forwarded-Host' => 'fvn.example',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Port' => '443',
    ]);

    expect($request->ip())->toBe('198.51.100.77')
        ->and($request->getHost())->toBe('fvn.example')
        ->and($request->getScheme())->toBe('https')
        ->and($request->getPort())->toBe(443);
});
