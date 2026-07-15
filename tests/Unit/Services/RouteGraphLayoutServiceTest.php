<?php

declare(strict_types=1);

use App\Services\RouteGraphLayoutService;

test('GraphViz layout uses fixed import process limits', function () {
    $service = app(RouteGraphLayoutService::class);
    $reflection = new ReflectionClass($service);

    expect($reflection->getConstant('PROCESS_TIMEOUT_SECONDS'))->toBe(600.0)
        ->and($reflection->getConstant('PROCESS_MEMORY_BYTES'))->toBe(1073741824)
        ->and($reflection->getConstant('PROCESS_CPU_SECONDS'))->toBe(600);
});
