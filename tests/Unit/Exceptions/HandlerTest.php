<?php

declare(strict_types=1);

use App\Exceptions\Handler;
use Illuminate\Support\Facades\Config;

test('exception reporting falls back when laravel logger initialization fails', function () {
    $handler = app(Handler::class);

    app('log')->setApplication(new Config);

    expect(fn () => $handler->report(new RuntimeException('Original failure')))
        ->not->toThrow(Throwable::class);
});

test('deprecation logger falls back when config binding is unavailable', function () {
    $config = app('config');

    try {
        app()->instance('config', null);
        app('log')->forgetChannel('deprecations');

        expect(fn () => app('log')->channel('deprecations')->warning('Deprecated behavior'))
            ->not->toThrow(Throwable::class);
    } finally {
        app()->instance('config', $config);
    }
});

test('deprecation logger works when events binding is unavailable', function () {
    $events = app('events');

    try {
        app()->instance('events', null);
        app('log')->forgetChannel('deprecations');

        expect(fn () => app('log')->channel('deprecations')->warning('Deprecated behavior'))
            ->not->toThrow(Throwable::class);
    } finally {
        app()->instance('events', $events);
    }
});
