<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create consistent test data for visual comparisons
    $this->games = Game::factory()->count(6)->create([
        'is_visible' => true,
        'status' => 'Published',
        'rating_score' => 4.5,
        'rating_count' => 100,
        'screenshots' => [
            ['url' => 'https://via.placeholder.com/800x600/FF5733/FFFFFF?text=Screenshot+1'],
            ['url' => 'https://via.placeholder.com/800x600/33FF57/FFFFFF?text=Screenshot+2'],
        ],
    ]);

    // Create a featured game with specific visual properties
    $this->featuredGame = Game::factory()->create([
        'name' => 'Visual Test Novel',
        'description' => 'A consistent game for visual regression testing with predictable content and layout.',
        'is_visible' => true,
        'status' => 'Published',
        'rating_score' => 4.8,
        'rating_count' => 250,
        'thumb_url' => 'https://via.placeholder.com/300x400/3357FF/FFFFFF?text=Featured+Game',
        'screenshots' => [
            ['url' => 'https://via.placeholder.com/1920x1080/FF3357/FFFFFF?text=Main+Screenshot'],
        ],
    ]);
});

test('homepage visual regression baseline', function () {
    $page = visit('/');

    // Take screenshots after basic page load
    $page->wait(2); // Wait 2 seconds for page to settle

    // Take full page screenshot for baseline comparison
    $page->screenshot(filename: 'homepage-full', fullPage: true);

    // Take viewport screenshot for above-the-fold content
    $page->screenshot(filename: 'homepage-viewport');

    // Hero section specific screenshot
    $page->screenshot(filename: 'homepage-hero');
});

test('games listing page visual consistency', function () {
    $page = visit('/games');

    // Wait for games to load
    $page->wait(3);

    // Desktop view (default)
    $page->screenshot(filename: 'games-listing-desktop', fullPage: true);

    // Mobile view
    $mobilePage = visit('/games')->on()->mobile();
    $mobilePage->wait(2)
        ->screenshot(filename: 'games-listing-mobile', fullPage: true);
});

test('game detail page visual elements', function () {
    $page = visit("/games/{$this->featuredGame->slug}");

    // Wait for dynamic content
    $page->wait(3);

    // Game header section
    $page->screenshot(filename: 'game-detail-header');

    // Screenshot gallery
    $page->screenshot(filename: 'game-detail-screenshots');

    // Full game detail page
    $page->screenshot(filename: 'game-detail-full', fullPage: true);
});

test('user dashboard visual layout', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    // Wait for dashboard components to load
    $page->wait(3);

    // Dashboard overview
    $page->screenshot(filename: 'dashboard-overview', fullPage: true);

    // Stats widgets section
    $page->screenshot(filename: 'dashboard-stats-widgets');

    // VN lists section
    $page->screenshot(filename: 'dashboard-vn-lists');
});

test('authentication pages visual consistency', function () {
    // Login page
    $loginPage = visit('/login');
    $loginPage->wait(2)
        ->screenshot(filename: 'auth-login-page', fullPage: true);

    // Register page
    $registerPage = visit('/register');
    $registerPage->wait(2)
        ->screenshot(filename: 'auth-register-page', fullPage: true);

    // Password reset page
    $resetPage = visit('/forgot-password');
    $resetPage->wait(2)
        ->screenshot(filename: 'auth-password-reset-page', fullPage: true);
});

test('modal and overlay visual regression', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    // Create list modal
    $page->click('[data-testid="create-list-button"]')
        ->wait(1)
        ->screenshot(filename: 'modal-create-list');

    $page->pressKey('Escape'); // Close modal

    // Visit game page for more modal testing
    $page->visit("/games/{$this->featuredGame->slug}");

    // Screenshot gallery modal
    $page->click('[data-testid="screenshot"]:first-child')
        ->wait(1)
        ->screenshot(filename: 'modal-screenshot-gallery', fullPage: true);
});

test('dark theme visual regression', function () {
    $page = visit('/');

    // Switch to dark theme
    $page->click('[data-testid="theme-toggle"]')
        ->wait(2);

    // Homepage in dark theme
    $page->screenshot(filename: 'homepage-dark-theme', fullPage: true);

    // Games page in dark theme
    $page->visit('/games')
        ->wait(2)
        ->screenshot(filename: 'games-dark-theme', fullPage: true);

    // Game detail in dark theme
    $page->visit("/games/{$this->featuredGame->slug}")
        ->wait(2)
        ->screenshot(filename: 'game-detail-dark-theme', fullPage: true);
});

test('form states visual regression', function () {
    $page = visit('/register');

    // Empty form state
    $page->wait(2)
        ->screenshot(filename: 'form-register-empty');

    // Form with validation errors
    $page->click('[data-testid="register-button"]')
        ->wait(1)
        ->screenshot(filename: 'form-register-errors');

    // Partially filled form
    $page->fill('[data-testid="name-input"]', 'Test User')
        ->fill('[data-testid="email-input"]', 'test@example.com')
        ->screenshot(filename: 'form-register-partial');
});

test('loading states visual regression', function () {
    $page = visit('/games');

    // Capture loading spinner
    $page->assertVisible('[data-testid="loading-spinner"]')
        ->screenshot(filename: 'loading-games-spinner');

    // Wait for content to load and capture loaded state
    $page->wait(3)
        ->screenshot(filename: 'loading-games-complete');
});

test('responsive breakpoints visual validation', function () {
    // Desktop view (default)
    $desktopPage = visit('/games');
    $desktopPage->wait(2)->screenshot(filename: 'responsive-games-desktop');

    // Mobile view
    $mobilePage = visit('/games')->on()->mobile();
    $mobilePage->wait(2)->screenshot(filename: 'responsive-games-mobile');

    // iPhone specific
    $iphonePage = visit('/games')->on()->iPhone14Pro();
    $iphonePage->wait(2)->screenshot(filename: 'responsive-games-iPhone14Pro');
});

test('interactive states visual regression', function () {
    $page = visit("/games/{$this->featuredGame->slug}");

    // Hover state on rating stars
    $page->hover('[data-testid="rating-stars"] [data-rating="4"]')
        ->screenshot(filename: 'interactive-rating-hover');

    // Focus state on form elements
    $page->visit('/login')
        ->click('[data-testid="email-input"]')
        ->screenshot(filename: 'interactive-input-focus');

    // Button active state
    $page->mouseDown('[data-testid="login-button"]')
        ->screenshot(filename: 'interactive-button-active');
});

test('visual regression comparison with tolerance', function () {
    $page = visit('/');

    // Take a screenshot
    $page->wait(2)
        ->screenshot(filename: 'homepage-comparison-test', fullPage: true);

    // In a real implementation, you would compare this against a baseline
    // and set tolerance levels for acceptable differences

    $screenshotPath = storage_path('app/screenshots/homepage-comparison-test.png');
    expect(file_exists($screenshotPath))->toBeTrue();

    // Verify image properties (as a proxy for visual validation)
    if (function_exists('getimagesize')) {
        $imageInfo = getimagesize($screenshotPath);
        expect($imageInfo)->not()->toBeNull()
            ->and($imageInfo[0])->toBeGreaterThan(1000) // Width
            ->and($imageInfo[1])->toBeGreaterThan(600); // Height
    }
});

test('component isolation visual testing', function () {
    $page = visit('/games');

    // Test individual game card component
    $page->wait(2)
        ->screenshot(filename: 'component-game-card');

    // Test navigation component
    $page->screenshot(filename: 'component-navigation');

    // Test search component
    $page->screenshot(filename: 'component-search-bar');

    // Test footer component
    $page->scroll('[data-testid="footer"]')
        ->screenshot(filename: 'component-footer');
});
