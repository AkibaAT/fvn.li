<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;
use Tighten\Ziggy\Ziggy;

test('every route name the frontend references is in the shared route table', function () {
    $shared = array_keys((new Ziggy)->toArray()['routes']);
    $referenced = [];

    foreach (Finder::create()->files()->in(resource_path('js'))->name(['*.svelte', '*.ts'])->notName('*.test.ts') as $file) {
        preg_match_all('/\broute\(\s*[\'"`]([A-Za-z0-9._-]+)[\'"`]/', $file->getContents(), $matches);
        preg_match_all('/[\'"]((?:browser-api|api)\.[a-z0-9.-]+)[\'"]/', $file->getContents(), $literals);
        $referenced = [...$referenced, ...$matches[1], ...$literals[1]];
    }

    expect($referenced)->not->toBeEmpty()
        ->and(array_values(array_diff(array_unique($referenced), $shared)))->toBe([]);
});
