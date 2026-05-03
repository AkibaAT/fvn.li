<?php

declare(strict_types=1);

it('correctly identifies games listing page from URL', function () {
    // Test the URL matching logic used by the Svelte app layout

    // Should match games listing page (updated regex to exclude /my/games pattern)
    expect('https://example.com/games')->toMatch('/(?<!\/my)\/games$/');
    expect('https://example.com/games?search=test')->toMatch('/\/games\?/');

    // Should NOT match individual game pages
    expect('https://example.com/games/some-game-slug')->not->toMatch('/(?<!\/my)\/games$|\/games\?/');

    // Should NOT match my games pages (updated regex to exclude /my/games pattern)
    expect('https://example.com/my/games')->not->toMatch('/(?<!\/my)\/games$|\/games\?/');
    expect('https://example.com/my/games/some-game/edit')->not->toMatch('/(?<!\/my)\/games$|\/games\?/');
});

it('correctly identifies URL patterns that should show filter button', function () {
    $testCases = [
        // Should show filter button
        ['https://example.com/games', true],
        ['https://example.com/games?search=test', true],
        ['https://example.com/games?category=action', true],

        // Should NOT show filter button
        ['https://example.com/games/some-game-slug', false],
        ['https://example.com/my/games', false],
        ['https://example.com/my/games/some-game/edit', false],
        ['https://example.com/dashboard', false],
        ['https://example.com/', false],
    ];

    foreach ($testCases as [$url, $shouldShow]) {
        // This mimics the games-list filter visibility logic in the Svelte layout/search UI:
        // (currentUrl.endsWith('/games') && !currentUrl.includes('/my/games')) || currentUrl.includes('/games?')
        $isGamesPage = (str_ends_with($url, '/games') && ! str_contains($url, '/my/games')) || str_contains($url, '/games?');

        if ($shouldShow) {
            expect($isGamesPage)->toBeTrue("URL '{$url}' should show filter button");
        } else {
            expect($isGamesPage)->toBeFalse("URL '{$url}' should NOT show filter button");
        }
    }
});
