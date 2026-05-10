<?php

declare(strict_types=1);

use App\Http\Middleware\PerformanceMonitoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['performance.log_queries' => true]);
    DB::flushQueryLog();
    DB::disableQueryLog();
});

afterEach(function () {
    DB::flushQueryLog();
    DB::disableQueryLog();
});

it('does not retain query logs across requests on a persistent connection', function () {
    $middleware = new PerformanceMonitoring;
    $request = Request::create('/games', 'GET');

    $firstResponse = $middleware->handle($request, function () {
        DB::select('select 1 as first_query');

        return response('OK');
    });

    expect((int) $firstResponse->headers->get('X-Query-Count'))->toBe(1)
        ->and(DB::getQueryLog())->toBe([]);

    DB::enableQueryLog();
    DB::select('select 2 as stale_query');
    expect(DB::getQueryLog())->toHaveCount(1);

    $secondResponse = $middleware->handle($request, function () {
        DB::select('select 3 as second_query');

        return response('OK');
    });

    expect((int) $secondResponse->headers->get('X-Query-Count'))->toBe(1)
        ->and(DB::getQueryLog())->toBe([]);
});

it('cleans up query logging when downstream handling throws', function () {
    $middleware = new PerformanceMonitoring;
    $request = Request::create('/games', 'GET');

    expect(fn () => $middleware->handle($request, function () {
        DB::select('select 1 as failing_query');

        throw new RuntimeException('downstream failure');
    }))->toThrow(RuntimeException::class, 'downstream failure');

    expect(DB::getQueryLog())->toBe([]);
});
