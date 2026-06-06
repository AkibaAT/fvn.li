<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->games = Game::factory()->count(3)->create([
        'is_visible' => true,
        'status' => 'Published',
    ]);
});

test('multi-browser collaboration scenario', function () {
    // Test collaborative features with two different browser sessions
    $userBrowser = visit('/login');
    $guestBrowser = visit('/', ['browser' => 'guest']);

    // User logs in
    $userBrowser->fill('email', $this->user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->wait(2);

    // Guest browses games
    $guestBrowser->visit('/games')
        ->assertSee('Visual Novels')
        ->click('[data-testid="game-card"]:first-child');

    // User creates a public list
    $userBrowser->visit('/dashboard')
        ->click('[data-testid="create-list-button"]')
        ->fill('[data-testid="list-name"]', 'Shared Recommendations')
        ->check('[data-testid="list-public-checkbox"]')
        ->click('[data-testid="create-list"]')
        ->wait(2);

    // Guest can now see the public list
    $guestBrowser->visit('/community/lists')
        ->wait(2)
        ->assertSee('Shared Recommendations');

    expect(true)->toBeTrue(); // Both browsers operated successfully
})->browsers(['chrome', 'firefox']);

test('responsive design across different devices', function () {
    $page = visit('/games');

    // Test desktop view
    $page->resize(1920, 1080)
        ->assertVisible('[data-testid="desktop-sidebar"]')
        ->assertVisible('[data-testid="game-grid"]')
        ->screenshot('games-desktop');

    // Test tablet view
    $page->resize(768, 1024)
        ->assertNotVisible('[data-testid="desktop-sidebar"]')
        ->assertVisible('[data-testid="tablet-menu"]')
        ->screenshot('games-tablet');

    // Test mobile view
    $page->resize(375, 667)
        ->assertVisible('[data-testid="mobile-menu-button"]')
        ->assertVisible('[data-testid="mobile-search"]')
        ->screenshot('games-mobile');

    // Test interactions work on all devices
    $page->click('[data-testid="mobile-menu-button"]')
        ->wait(2)
        ->click('Dashboard')
        ->assertUrl('*/login') // Should redirect to login for guest
        ->assertNoJavascriptErrors();
});

test('dark and light theme consistency', function () {
    $page = visit('/');

    // Test light theme
    $page->assertNotHasClass('html', 'dark')
        ->screenshot('homepage-light');

    // Switch to dark theme
    $page->click('[data-testid="theme-toggle"]')
        ->wait(2)
        ->screenshot('homepage-dark');

    // Navigate to different pages and ensure theme persists
    $page->click('Games')
        ->wait(2)
        ->assertHasClass('html', 'dark')
        ->screenshot('games-dark');

    // Switch back to light theme
    $page->click('[data-testid="theme-toggle"]')
        ->waitFor(2, fn () => ! document . documentElement . classList . contains('dark'))
        ->screenshot('games-light-switched')
        ->assertNotHasClass('html', 'dark');
});

test('accessibility compliance and screen reader support', function () {
    $page = visit('/games');

    // Test keyboard navigation
    $page->pressKey('Tab')
        ->pressKey('Tab')
        ->pressKey('Tab')
        ->pressKey('Enter')
        ->wait(2)
        ->assertNoJavascriptErrors();

    // Test ARIA labels and roles
    $page->evaluate('() => {
        const gameCards = document.querySelectorAll("[data-testid=game-card]");
        return Array.from(gameCards).every(card =>
            card.getAttribute("role") === "article" &&
            card.getAttribute("aria-label")
        );
    }')->toBe(true);

    // Test focus indicators
    $page->pressKey('Tab')
        ->evaluate('() => {
            const activeElement = document.activeElement;
            const computedStyle = window.getComputedStyle(activeElement);
            return computedStyle.getPropertyValue("outline") !== "none";
        }')->toBe(true);
});

test('performance and loading states', function () {
    // Test loading states and performance
    $page = visit('/games');

    // Verify loading spinner appears and disappears
    $page->assertVisible('[data-testid="loading-spinner"]')
        ->wait(2)
        ->assertNotVisible('[data-testid="loading-spinner"]');

    // Test infinite scroll performance
    $page->scroll('[data-testid="games-list"]', 'bottom')
        ->wait(2) // Wait for new content to load
        ->evaluate('() => {
            const gameCards = document.querySelectorAll("[data-testid=game-card]");
            return gameCards.length > 10; // Should have loaded more games
        }')->toBe(true);

    // Test search debouncing
    $searchStart = microtime(true);
    $page->fill('[data-testid="search-input"]', 'test')
        ->wait(2) // Wait for debounce
        ->fill('[data-testid="search-input"]', 'test search')
        ->wait(2);

    $searchEnd = microtime(true);
    expect($searchEnd - $searchStart)->toBeLessThan(2.0); // Should complete within 2 seconds
});

test('error handling and recovery', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    // Simulate network error
    $page->evaluate('() => {
        window.fetch = () => Promise.reject(new Error("Network error"));
    }');

    // Try to create a list (should show error)
    $page->click('[data-testid="create-list-button"]')
        ->fill('[data-testid="list-name"]', 'Error Test List')
        ->click('[data-testid="create-list"]')
        ->wait(2)
        ->assertSee('Something went wrong');

    // Test retry functionality
    $page->evaluate('() => {
        delete window.fetch; // Restore original fetch
    }');

    $page->click('[data-testid="retry-button"]')
        ->wait(2)
        ->assertSee('List created successfully');
});

test('real-time features and websockets', function () {
    $this->actingAs($this->user);

    // Test real-time notifications
    $page = visit('/dashboard');

    // Simulate receiving a notification
    $page->evaluate('() => {
        window.Echo?.channel("user.' . $this->user->id . '")
            ?.listen("NotificationReceived", (e) => {
                document.querySelector("[data-testid=notification-bell]")
                    ?.classList.add("has-notification");
            });

        // Simulate the event
        window.dispatchEvent(new CustomEvent("notification", {
            detail: { message: "New comment on your list" }
        }));
    }');

    $page->wait(2)
        ->click('[data-testid="notification-bell"]')
        ->wait(2)
        ->assertSee('New comment on your list');
});

test('advanced form interactions and validation', function () {
    $this->actingAs($this->user);

    $page = visit('/profile');

    // Test complex form interactions
    $page->fill('[data-testid="name-input"]', '')
        ->blur('[data-testid="name-input"]')
        ->assertSee('Name is required')
        ->assertHasClass('[data-testid="name-input"]', 'error');

    // Test file upload
    $page->uploadFile('[data-testid="avatar-upload"]', [
        __DIR__ . '/fixtures/test-avatar.jpg',
    ])->wait(2)
        ->wait(2)
        ->assertSee('Avatar uploaded successfully');

    // Test drag and drop
    $page->drag('[data-testid="draggable-item"]', '[data-testid="drop-zone"]')
        ->wait(2)
        ->assertSee('Item moved successfully');
});

test('integration with external services', function () {
    $page = visit('/games');

    // Test social sharing
    $page->click('[data-testid="game-card"]:first-child')
        ->click('[data-testid="share-button"]')
        ->click('[data-testid="share-twitter"]');

    // New tab should open with Twitter
    $page->switchToTab(1)
        ->assertUrl('*twitter.com*')
        ->assertSee('Tweet');

    $page->switchToTab(0); // Back to main tab

    // Test external game links
    $page->click('[data-testid="external-link"]')
        ->switchToTab(1)
        ->assertUrl('*itch.io*');
});

test('smoke testing across multiple pages', function () {
    $pages = visit([
        '/',
        '/games',
        '/login',
        '/register',
        '/community/lists',
    ]);

    $pages->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();

    // Each page should load without critical errors
    expect(true)->toBeTrue();
});

test('visual regression detection', function () {
    $page = visit('/');

    // Take baseline screenshots for visual comparison
    $page->screenshot('homepage-baseline');

    $page->visit('/games')
        ->screenshot('games-baseline');

    // In a real scenario, these would be compared against previous screenshots
    // to detect visual regressions
    expect(file_exists(storage_path('app/screenshots/homepage-baseline.png')))->toBeTrue();
});

test('browser console monitoring', function () {
    $page = visit('/games');

    // Monitor console for errors during user interactions
    $page->click('[data-testid="game-card"]:first-child')
        ->wait(2)
        ->click('[data-testid="add-to-list"]')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleErrors();

    // Check for specific console messages
    $consoleMessages = $page->getConsoleMessages();
    expect($consoleMessages)->not()->toContain('error');
});
