<?php

declare(strict_types=1);

use App\Support\ViewPreference;
use Illuminate\Http\Request;

it('defaults to the grid layout', function () {
    expect(ViewPreference::mode(ViewPreference::GAMES_COOKIE))->toBe('grid');
});

it('treats only an explicit list value as the list layout', function () {
    $list = Request::create('/', 'GET', [], [ViewPreference::GAMES_COOKIE => 'list']);
    expect(ViewPreference::mode(ViewPreference::GAMES_COOKIE, $list))->toBe('list');

    $unknown = Request::create('/', 'GET', [], [ViewPreference::GAMES_COOKIE => 'carousel']);
    expect(ViewPreference::mode(ViewPreference::GAMES_COOKIE, $unknown))->toBe('grid');
});

it('reads each catalogue cookie independently', function () {
    $request = Request::create('/', 'GET', [], [
        ViewPreference::HOME_COOKIE => 'list',
        ViewPreference::GAMES_COOKIE => 'grid',
    ]);

    expect(ViewPreference::mode(ViewPreference::HOME_COOKIE, $request))->toBe('list')
        ->and(ViewPreference::mode(ViewPreference::GAMES_COOKIE, $request))->toBe('grid');
});
