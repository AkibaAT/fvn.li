<?php

use App\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;

test('prevent request forgery keeps api exclusions and bypasses browser api only in testing', function () {
    $middleware = new PreventRequestForgery(app(), app('encrypter'));
    $method = new ReflectionMethod($middleware, 'inExceptArray');
    $method->setAccessible(true);

    expect($method->invoke($middleware, Request::create('/browser-api/games/1/reviews', 'POST')))->toBeTrue()
        ->and($method->invoke($middleware, Request::create('/api/game-reviews', 'POST')))->toBeTrue()
        ->and($method->invoke($middleware, Request::create('/dashboard', 'POST')))->toBeFalse();
});
