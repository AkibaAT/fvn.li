<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('homepage loads successfully', function () {
    // Visit homepage
    $page = visit('/');

    // Basic assertions
    $page->assertSee('FVN.li');
    $page->assertNoJavascriptErrors();
});

test('user can view game listings', function () {
    // Create a test user
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@fvn.test',
    ]);

    $this->actingAs($user);

    // Visit games page
    $page = visit('/games');

    $page->assertSee('Games');
    $page->assertNoJavascriptErrors();
});

test('authenticated user can access dashboard', function () {
    // Create and authenticate user
    $user = User::factory()->create([
        'name' => 'Dashboard User',
        'email' => 'dashboard@fvn.test',
    ]);

    // Initialize default lists for the user
    $user->initializeDefaultLists();

    $this->actingAs($user);

    // Visit dashboard
    $page = visit('/dashboard');

    $page->assertSee('Dashboard');
    $page->assertSee('Your Visual Novel Lists');
    $page->assertNoJavascriptErrors();
});
