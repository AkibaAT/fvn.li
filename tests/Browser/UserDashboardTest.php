<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
use App\Models\VnList;
use App\Models\VnListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Dashboard User',
        'email' => 'dashboard@example.com',
    ]);

    // Initialize default VN lists
    $this->user->initializeDefaultLists();

    // Create sample games
    $this->games = Game::factory()->count(5)->create([
        'is_visible' => true,
        'status' => 'Published',
    ]);

    // Add games to user's lists
    $currentlyReading = $this->user->vnLists()->where('name', 'Currently Reading')->first();
    $completed = $this->user->vnLists()->where('name', 'Completed')->first();

    VnListEntry::factory()->for($currentlyReading)->create([
        'game_id' => $this->games[0]->id,
        'status' => 'reading',
        'progress' => 45,
    ]);

    VnListEntry::factory()->for($completed)->create([
        'game_id' => $this->games[1]->id,
        'status' => 'completed',
        'rating' => 5,
        'completion_date' => now(),
    ]);
});

test('user dashboard displays correctly', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    $page->assertSee('Welcome back, Dashboard User')
        ->assertSee('Your Visual Novel Lists')
        ->assertSee('Recently Updated')
        ->assertSee('Statistics')
        ->assertSee('Currently Reading (1)')
        ->assertSee('Completed (1)')
        ->assertNoJavascriptErrors();
});

test('user can view their VN lists', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    $page->click('[data-testid="currently-reading-list"]')
        ->wait(2)
        ->assertSee('Currently Reading')
        ->assertSee($this->games[0]->name)
        ->assertSee('45%') // Progress
        ->assertNoJavascriptErrors();
});

test('user can create a custom VN list', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    $page->click('[data-testid="create-list-button"]')
        ->wait(2)
        ->fill('[data-testid="list-name"]', 'My Favorites')
        ->fill('[data-testid="list-description"]', 'My favorite visual novels')
        ->click('[data-testid="list-public-checkbox"]')
        ->click('[data-testid="create-list"]')
        ->wait(2)
        ->assertSee('List created successfully')
        ->assertSee('My Favorites')
        ->assertNoJavascriptErrors();

    // Verify list was created
    expect($this->user->vnLists()->where('name', 'My Favorites')->exists())->toBeTrue();
});

test('user can edit VN list details', function () {
    $this->actingAs($this->user);

    $customList = VnList::factory()->for($this->user)->create([
        'name' => 'Custom List',
        'description' => 'Original description',
        'is_public' => false,
    ]);

    $page = visit("/lists/{$customList->id}");

    $page->click('[data-testid="edit-list-button"]')
        ->wait(2)
        ->fill('[data-testid="list-name"]', 'Updated List Name')
        ->fill('[data-testid="list-description"]', 'Updated description')
        ->click('[data-testid="list-public-checkbox"]')
        ->click('[data-testid="save-changes"]')
        ->wait(2)
        ->assertSee('List updated successfully')
        ->assertSee('Updated List Name')
        ->assertNoJavascriptErrors();
});

test('user can add game to VN list from game details', function () {
    $this->actingAs($this->user);

    $page = visit("/games/{$this->games[2]->slug}");

    $page->click('[data-testid="add-to-list"]')
        ->wait(2)
        ->click('[data-testid="list-option-plan-to-read"]')
        ->click('[data-testid="confirm-add"]')
        ->wait(2)
        ->assertSee('Added to Plan to Read')
        ->assertNoJavascriptErrors();
});

test('user can update game progress', function () {
    $this->actingAs($this->user);

    $currentlyReading = $this->user->vnLists()->where('name', 'Currently Reading')->first();

    $page = visit("/lists/{$currentlyReading->id}");

    $page->click('[data-testid="game-options"]:first-child')
        ->click('[data-testid="update-progress"]')
        ->wait(2)
        ->fill('[data-testid="progress-slider"]', '75')
        ->click('[data-testid="save-progress"]')
        ->wait(2)
        ->assertSee('75%')
        ->assertNoJavascriptErrors();
});

test('user can move game between lists', function () {
    $this->actingAs($this->user);

    $currentlyReading = $this->user->vnLists()->where('name', 'Currently Reading')->first();

    $page = visit("/lists/{$currentlyReading->id}");

    $page->click('[data-testid="game-options"]:first-child')
        ->click('[data-testid="move-to-list"]')
        ->wait(2)
        ->click('[data-testid="list-option-completed"]')
        ->fill('[data-testid="rating-input"]', '4')
        ->click('[data-testid="confirm-move"]')
        ->wait(2)
        ->assertSee('Moved to Completed')
        ->assertNoJavascriptErrors();
});

test('user can remove game from list', function () {
    $this->actingAs($this->user);

    $currentlyReading = $this->user->vnLists()->where('name', 'Currently Reading')->first();
    $gameName = $this->games[0]->name;

    $page = visit("/lists/{$currentlyReading->id}");

    $page->click('[data-testid="game-options"]:first-child')
        ->click('[data-testid="remove-from-list"]')
        ->wait(2)
        ->click('[data-testid="confirm-remove"]')
        ->wait(2)
        ->assertDontSee($gameName)
        ->assertNoJavascriptErrors();
});

test('user can sort games in list', function () {
    $this->actingAs($this->user);

    // Add more games to list for sorting
    $completed = $this->user->vnLists()->where('name', 'Completed')->first();
    VnListEntry::factory()->for($completed)->count(3)->create([
        'status' => 'completed',
        'completion_date' => now()->subDays(rand(1, 30)),
    ]);

    $page = visit("/lists/{$completed->id}");

    $page->click('[data-testid="sort-dropdown"]')
        ->click('[data-testid="sort-rating"]')
        ->wait(2)
        ->assertNoJavascriptErrors();

    $page->click('[data-testid="sort-dropdown"]')
        ->click('[data-testid="sort-completion-date"]')
        ->wait(2)
        ->assertNoJavascriptErrors();
});

test('user statistics display correctly', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    $page->assertSee('Total Games: 2')
        ->assertSee('Currently Reading: 1')
        ->assertSee('Completed: 1')
        ->assertSee('Average Rating:')
        ->assertNoJavascriptErrors();
});

test('recent activity feed displays correctly', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    $page->scroll('[data-testid="recent-activity"]')
        ->assertSee('Recent Activity')
        ->assertSee('Added')
        ->assertSee('to')
        ->assertNoJavascriptErrors();
});

test('user can export their lists', function () {
    $this->actingAs($this->user);

    $currentlyReading = $this->user->vnLists()->where('name', 'Currently Reading')->first();

    $page = visit("/lists/{$currentlyReading->id}");

    $page->click('[data-testid="export-list"]')
        ->wait(2)
        ->click('[data-testid="export-csv"]')
        ->wait(2)
        ->assertSee('Export ready for download')
        ->assertNoJavascriptErrors();
});

test('user can import games to list', function () {
    $this->actingAs($this->user);

    $planToRead = $this->user->vnLists()->where('name', 'Plan to Read')->first();

    $page = visit("/lists/{$planToRead->id}");

    $page->click('[data-testid="import-games"]')
        ->wait(2)
        ->fill('[data-testid="import-textarea"]', "Game Title 1\nGame Title 2\nGame Title 3")
        ->click('[data-testid="start-import"]')
        ->wait(2)
        ->wait(2)
        ->assertSee('Import completed')
        ->assertNoJavascriptErrors();
});

test('list sharing works correctly', function () {
    $this->actingAs($this->user);

    $publicList = VnList::factory()->for($this->user)->create([
        'name' => 'Public Favorites',
        'is_public' => true,
    ]);

    $page = visit("/lists/{$publicList->id}");

    $page->click('[data-testid="share-list"]')
        ->wait(2)
        ->assertSee('Share this list')
        ->click('[data-testid="copy-link"]')
        ->assertSee('Link copied to clipboard')
        ->assertNoJavascriptErrors();
});

test('user can view public lists from other users', function () {
    $otherUser = User::factory()->create();
    $publicList = VnList::factory()->for($otherUser)->create([
        'name' => 'Community Recommendations',
        'is_public' => true,
    ]);

    $this->actingAs($this->user);

    $page = visit('/community/lists');

    $page->assertSee('Community Lists')
        ->assertSee('Community Recommendations')
        ->click("[data-testid='list-{$publicList->id}']")
        ->assertSee($otherUser->name)
        ->assertSee('Community Recommendations')
        ->assertNoJavascriptErrors();
});

test('dashboard widgets are interactive', function () {
    $this->actingAs($this->user);

    $page = visit('/dashboard');

    // Test reading progress widget
    $page->click('[data-testid="reading-progress-widget"]')
        ->wait(2)
        ->assertSee('Reading Progress Details')
        ->assertNoJavascriptErrors();

    // Test completion statistics widget
    $page->click('[data-testid="completion-stats-widget"]')
        ->wait(2)
        ->assertVisible('[data-testid="stats-chart"]')
        ->assertNoJavascriptErrors();
});
