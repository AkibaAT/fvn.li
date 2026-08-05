<?php

declare(strict_types=1);

it('renders the token-based 404 page in the requested appearance', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('<html lang="en" class="dark">', false)
        ->assertSee('Page Not Found')
        ->assertSee('Return to home');
});

it('renders standalone server and maintenance error pages', function () {
    expect(view('errors.500')->render())
        ->toContain('500', 'Server Error')
        ->and(view('errors.503')->render())
        ->toContain('503', 'Service Unavailable');
});
