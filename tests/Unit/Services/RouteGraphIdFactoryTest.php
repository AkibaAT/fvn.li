<?php

declare(strict_types=1);

use App\Services\RouteGraphIdFactory;

test('synthetic ending IDs preserve normal route labels', function () {
    expect((new RouteGraphIdFactory)->syntheticEndingId('finale'))->toBe('finale:ending');
});

test('synthetic ending IDs remain bounded and unique for overlong labels', function () {
    $factory = new RouteGraphIdFactory;
    $first = $factory->syntheticEndingId(str_repeat('a', 249));
    $second = $factory->syntheticEndingId(str_repeat('a', 248) . 'b');

    expect(mb_strlen($first))->toBe(RouteGraphIdFactory::MAX_SYNTHETIC_ID_LENGTH)
        ->and($first)->toEndWith(':ending')
        ->and($second)->toEndWith(':ending')
        ->and($second)->not->toBe($first)
        ->and($factory->syntheticEndingId(str_repeat('a', 249)))->toBe($first);
});
