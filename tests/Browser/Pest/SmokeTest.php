<?php

declare(strict_types=1);

test('guest can render the public login page through Pest Browser', function () {
    visit('/login')
        ->waitForEvent('networkidle')
        ->assertNoJavascriptErrors()
        ->assertSourceHas('"component":"auth\/login"')
        ->assertSourceHas('Log in - FVN.li');
})->group('Browser');

test('guest can render the games index through Pest Browser', function () {
    visit('/games')
        ->waitForEvent('networkidle')
        ->assertNoJavascriptErrors()
        ->assertSourceHas('"component":"games\/index"')
        ->assertSourceHas('Visual Novels - FVN.li');
})->group('Browser');
