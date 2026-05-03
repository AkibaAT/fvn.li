<?php

declare(strict_types=1);

test('guest can render the public login page through Pest Browser', function () {
    visit('/login')
        ->assertSee('FVN.li')
        ->assertNoJavascriptErrors();
})->group('Browser');

test('guest can render the games index through Pest Browser', function () {
    visit('/games')
        ->assertSee('Visual Novels')
        ->assertNoJavascriptErrors();
})->group('Browser');
