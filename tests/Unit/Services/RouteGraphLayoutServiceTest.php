<?php

declare(strict_types=1);

use App\Services\RouteGraphLayoutService;

test('GraphViz layout uses fixed import process limits', function () {
    $service = app(RouteGraphLayoutService::class);
    $reflection = new ReflectionClass($service);

    expect($reflection->getConstant('PROCESS_TIMEOUT_SECONDS'))->toBe(120.0)
        ->and($reflection->getConstant('PROCESS_MEMORY_BYTES'))->toBe(1073741824)
        ->and($reflection->getConstant('PROCESS_CPU_SECONDS'))->toBe(120);
});

test('GraphViz layout falls back to an engine that handles large graphs', function () {
    $service = app(RouteGraphLayoutService::class);
    $reflection = new ReflectionClass($service);

    // dot gives the clearest shape for a story flow but cannot lay out the
    // largest graphs, so a second engine stands in rather than losing the map.
    expect($reflection->getConstant('PRIMARY_ENGINE'))->toBe('dot')
        ->and($reflection->getConstant('FALLBACK_ENGINE'))->toBe('sfdp');
});

test('a graph that cannot be positioned is still returned', function () {
    $service = app(RouteGraphLayoutService::class);
    $method = new ReflectionMethod($service, 'unpositioned');

    $layout = $method->invoke($service, 'engine unavailable');

    expect($layout['nodes'])->toBe([])
        ->and($layout['width'])->toBe(0.0)
        ->and($layout['height'])->toBe(0.0)
        ->and($layout['engine'])->toBeString();
});
