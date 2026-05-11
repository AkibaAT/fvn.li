<?php

declare(strict_types=1);

test('footer new tab external links disable opener', function () {
    $footer = file_get_contents(resource_path('js/components/footer/Footer.svelte'));

    preg_match_all('/<a\b[^>]*target="_blank"[^>]*>/s', $footer, $matches);

    expect($matches[0])->not->toBeEmpty();

    foreach ($matches[0] as $anchor) {
        expect($anchor)->toContain('rel=')
            ->and($anchor)->toContain('noopener');
    }
});
