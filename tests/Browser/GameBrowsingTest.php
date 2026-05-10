<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create sample games for testing
    $this->games = Game::factory()->count(10)->create([
        'is_visible' => true,
        'status' => 'Published',
    ]);

    // Create a featured game
    $this->featuredGame = Game::factory()->create([
        'name' => 'Featured Visual Novel',
        'description' => 'An amazing visual novel with great characters',
        'is_visible' => true,
        'status' => 'Published',
        'rating_score' => 4.8,
        'rating_count' => 150,
        'trending_score' => 95.5,
    ]);

    // Create versions for games
    GameVersion::factory()->for($this->featuredGame)->create();
});

test('games listing page displays correctly', function () {
    $page = visit('/games');

    $page->assertSee('Visual Novels')
        ->assertSee('Featured Visual Novel')
        ->assertSee('Games per page')
        ->assertSee('Sort by')
        ->assertNoJavascriptErrors();
});

test('user can browse games with pagination', function () {
    // Create more games to test pagination
    Game::factory()->count(50)->create([
        'is_visible' => true,
        'status' => 'Published',
    ]);

    $page = visit('/games');

    $page->assertSee('Next')
        ->click('Next')
        ->wait(2)
        ->assertSee('Previous')
        ->assertNoJavascriptErrors();
});

test('user can sort games by different criteria', function () {
    $page = visit('/games');

    $page->click('[data-testid="sort-dropdown"]')
        ->click('Rating')
        ->wait(2)
        ->assertSee('Featured Visual Novel') // Should appear first with high rating
        ->assertNoJavascriptErrors();

    $page->click('[data-testid="sort-dropdown"]')
        ->click('Name (A-Z)')
        ->wait(2)
        ->assertNoJavascriptErrors();
});

test('user can filter games by status', function () {
    Game::factory()->create([
        'name' => 'Demo Game',
        'status' => 'In Development',
        'is_visible' => true,
    ]);

    $page = visit('/games');

    $page->click('[data-testid="status-filter"]')
        ->click('In Development')
        ->wait(2)
        ->assertSee('Demo Game')
        ->assertNoJavascriptErrors();
});

test('user can search games by name', function () {
    $page = visit('/games');

    $page->fill('[data-testid="search-input"]', 'Featured')
        ->press('Enter')
        ->wait(2)
        ->assertSee('Featured Visual Novel')
        ->assertNoJavascriptErrors();
});

test('user can view game details', function () {
    $page = visit('/games');

    $page->click('[data-testid="game-card"]:first-child')
        ->wait(2)
        ->assertSee('Description')
        ->assertSee('Screenshots')
        ->assertSee('Versions')
        ->assertNoJavascriptErrors();
});

test('game details page shows all relevant information', function () {
    $page = visit("/games/{$this->featuredGame->slug}");

    $page->assertSee('Featured Visual Novel')
        ->assertSee('An amazing visual novel')
        ->assertSee('4.8') // Rating
        ->assertSee('150 ratings')
        ->assertSee('Add to List')
        ->assertNoJavascriptErrors();
});

test('authenticated user can add game to their list', function () {
    $this->actingAs($this->user);

    $page = visit("/games/{$this->featuredGame->slug}");

    $page->click('Add to List')
        ->wait(2)
        ->click('Currently Reading')
        ->wait(2)
        ->assertSee('Added to Currently Reading')
        ->assertNoJavascriptErrors();
});

test('guest user sees login prompt when trying to add game to list', function () {
    $page = visit("/games/{$this->featuredGame->slug}");

    $page->click('Add to List')
        ->wait(2)
        ->assertSee('Please log in to add games to your lists')
        ->assertSee('Log In')
        ->assertNoJavascriptErrors();
});

test('user can view game screenshots in gallery', function () {
    $gameWithScreenshots = Game::factory()->create([
        'name' => 'Screenshot Game',
        'screenshots' => [
            ['url' => 'https://example.com/screenshot1.jpg'],
            ['url' => 'https://example.com/screenshot2.jpg'],
            ['url' => 'https://example.com/screenshot3.jpg'],
        ],
        'is_visible' => true,
    ]);

    $page = visit("/games/{$gameWithScreenshots->slug}");

    $page->click('[data-testid="screenshot"]:first-child')
        ->wait(2)
        ->assertVisible('[data-testid="screenshot-gallery"]')
        ->click('[data-testid="next-screenshot"]')
        ->click('[data-testid="close-modal"]')
        ->assertNotVisible('[data-testid="screenshot-modal"]')
        ->assertNoJavascriptErrors();
});

test('nsfw games show blur overlay by default', function () {
    $nsfwGame = Game::factory()->create([
        'name' => 'NSFW Visual Novel',
        'is_nsfw' => true,
        'is_visible' => true,
    ]);

    $page = visit("/games/{$nsfwGame->slug}");

    $page->assertSee('This content is marked as NSFW')
        ->assertHasClass('[data-testid="screenshot"]', 'blur')
        ->click('Show NSFW Content')
        ->assertNotHasClass('[data-testid="screenshot"]', 'blur')
        ->assertNoJavascriptErrors();
});

test('user can rate a game', function () {
    $this->actingAs($this->user);

    $page = visit("/games/{$this->featuredGame->slug}");

    $page->click('[data-testid="rating-stars"] [data-rating="5"]')
        ->wait(2)
        ->assertSee('Thank you for rating')
        ->assertNoJavascriptErrors();
});

test('user can write and submit a review', function () {
    $this->actingAs($this->user);

    $page = visit("/games/{$this->featuredGame->slug}");

    $page->scroll('[data-testid="reviews-section"]')
        ->fill('[data-testid="review-text"]', 'This is an amazing visual novel with great storytelling!')
        ->click('[data-testid="submit-review"]')
        ->wait(2)
        ->assertSee('Review submitted successfully')
        ->assertSee('This is an amazing visual novel')
        ->assertNoJavascriptErrors();
});

test('infinite scroll works on games listing', function () {
    // Create many games for infinite scroll
    Game::factory()->count(100)->create([
        'is_visible' => true,
        'status' => 'Published',
    ]);

    $page = visit('/games');

    $initialCount = count($page->elements('[data-testid="game-card"]'));

    $page->scroll('[data-testid="games-list"]', 'bottom')
        ->wait(2) // Wait for new games to load
        ->evaluate('() => document.querySelectorAll("[data-testid=game-card]").length > '.$initialCount)
        ->assertNoJavascriptErrors();
});

test('game search suggestions work correctly', function () {
    Game::factory()->create([
        'name' => 'Doki Doki Literature Club',
        'is_visible' => true,
    ]);

    $page = visit('/games');

    $page->fill('[data-testid="search-input"]', 'Doki')
        ->wait(2)
        ->assertSee('Doki Doki Literature Club')
        ->click('[data-testid="suggestion"]:first-child')
        ->assertUrl('*/games/doki-doki-literature-club')
        ->assertNoJavascriptErrors();
});

test('advanced search filters work correctly', function () {
    $page = visit('/games');

    $page->click('[data-testid="advanced-search"]')
        ->wait(2)
        ->select('[data-testid="genre-filter"]', 'Romance')
        ->select('[data-testid="language-filter"]', 'English')
        ->click('[data-testid="free-only-checkbox"]')
        ->click('[data-testid="apply-filters"]')
        ->wait(2)
        ->assertNoJavascriptErrors();
});

test('game grid and list view toggle works', function () {
    $page = visit('/games');

    $page->click('[data-testid="view-toggle-list"]')
        ->assertHasClass('[data-testid="games-container"]', 'list-view')
        ->click('[data-testid="view-toggle-grid"]')
        ->assertHasClass('[data-testid="games-container"]', 'grid-view')
        ->assertNoJavascriptErrors();
});
