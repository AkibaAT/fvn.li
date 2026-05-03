<?php

declare(strict_types=1);

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
