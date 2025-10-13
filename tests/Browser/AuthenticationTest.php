<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test user for authentication tests
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    // Initialize default VN lists for the user
    $this->user->initializeDefaultLists();
});

test('guest can view homepage', function () {
    $page = visit('/');

    $page->assertSee('FVN.li')
        ->assertSee('Visual Novel')
        ->assertNoJavascriptErrors();
});

test('guest can navigate to login page', function () {
    $page = visit('/');

    $page->click('Sign In')
        ->assertSee('Log in')
        ->assertSee('Email')
        ->assertSee('Password')
        ->assertNoJavascriptErrors();
});

test('user can login with valid credentials', function () {
    $page = visit('/login');

    $page->fill('email', 'test@example.com')
        ->fill('password', 'password123')
        ->click('Log in')
        ->wait(2) // Wait for user menu to appear
        ->assertSee('Test User')
        ->assertNoJavascriptErrors();
});

test('user cannot login with invalid credentials', function () {
    $page = visit('/login');

    $page->fill('email', 'test@example.com')
        ->fill('password', 'wrongpassword')
        ->click('Log in')
        ->assertSee('These credentials do not match our records')
        ->assertNoJavascriptErrors();
});

test('user can access protected dashboard after login', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    $page->assertSee('Dashboard')
        ->assertSee('Your Visual Novel Lists')
        ->assertSee('Currently Reading')
        ->assertSee('Completed')
        ->assertSee('Plan to Read')
        ->assertNoJavascriptErrors();
});

test('guest is redirected to login when accessing protected pages', function () {
    $page = visit('/dashboard');

    $page->assertSee('Log in')
        ->assertUrl('*/login')
        ->assertNoJavascriptErrors();
});

test('user can logout successfully', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    $page->click('[data-testid="user-menu"]')
        ->click('Logout')
        ->assertSee('Log in') // Redirected to login page
        ->assertNoJavascriptErrors();
});

test('user registration flow works correctly', function () {
    $page = visit('/register');

    $page->fill('name', 'New Test User')
        ->fill('email', 'newuser@example.com')
        ->fill('password', 'password123')
        ->fill('password_confirmation', 'password123')
        ->click('Register')
        ->wait(2)
        ->assertSee('Dashboard')
        ->assertSee('New Test User')
        ->assertNoJavascriptErrors();

    // Verify user was created in database
    expect(User::where('email', 'newuser@example.com')->exists())->toBeTrue();
});

test('registration validates required fields', function () {
    $page = visit('/register');

    $page->click('Register')
        ->assertSee('The name field is required')
        ->assertSee('The email field is required')
        ->assertSee('The password field is required')
        ->assertNoJavascriptErrors();
});

test('registration validates password confirmation', function () {
    $page = visit('/register');

    $page->fill('name', 'Test User')
        ->fill('email', 'test@example.com')
        ->fill('password', 'password123')
        ->fill('password_confirmation', 'different')
        ->click('Register')
        ->assertSee('The password field confirmation does not match')
        ->assertNoJavascriptErrors();
});

test('user can reset password', function () {
    $page = visit('/login');

    $page->click('Forgot Your Password?')
        ->fill('email', 'test@example.com')
        ->click('Email Password Reset Link')
        ->assertSee('We have emailed your password reset link')
        ->assertNoJavascriptErrors();
});

test('social authentication buttons are present', function () {
    $page = visit('/login');

    $page->assertSee('Continue with Discord')
        ->assertSee('Continue with Google')
        ->assertSee('Continue with Steam')
        ->assertNoJavascriptErrors();
});

test('user profile page displays correctly', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');

    $page->assertSee('Profile')
        ->assertSee('Test User')
        ->assertSee('test@example.com')
        ->assertSee('Update Profile')
        ->assertNoJavascriptErrors();
});

test('user can update profile information', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');

    $page->fill('name', 'Updated Name')
        ->click('Update Profile')
        ->assertSee('Profile updated successfully')
        ->assertSee('Updated Name')
        ->assertNoJavascriptErrors();

    // Verify database was updated
    expect($this->user->fresh()->name)->toBe('Updated Name');
});

test('dark mode toggle works correctly', function () {
    $page = visit('/');

    $page->click('[data-testid="theme-toggle"]')
        ->assertHasClass('html', 'dark')
        ->click('[data-testid="theme-toggle"]')
        ->assertNotHasClass('html', 'dark')
        ->assertNoJavascriptErrors();
});

test('mobile navigation works correctly', function () {
    $page = visit('/');
    $page->resize(375, 667); // iPhone size

    $page->click('[data-testid="mobile-menu-button"]')
        ->assertVisible('[data-testid="mobile-menu"]')
        ->click('Games')
        ->assertUrl('*/games')
        ->assertNoJavascriptErrors();
});
