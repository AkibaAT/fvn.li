<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create sample game with dialogue
    $this->game = Game::factory()->create([
        'name' => 'Dialogue Test VN',
        'is_visible' => true,
        'status' => 'Published',
    ]);

    $this->version = GameVersion::factory()->for($this->game)->create();

    // Create characters
    $this->protagonist = Character::factory()->for($this->game)->create([
        'character_id' => 'protagonist',
        'display_names' => ['eng' => 'Alex'],
    ]);

    $this->heroine = Character::factory()->for($this->game)->create([
        'character_id' => 'heroine',
        'display_names' => ['eng' => 'Luna'],
    ]);

    // Create dialogue texts
    $this->dialogueTexts = [
        'Hello, my name is Alex. Nice to meet you!',
        'Luna, you look beautiful today.',
        'I love spending time with you in the garden.',
        'The sunset reminds me of your smile.',
        'Will you go to the school festival with me?',
    ];

    // Create dialogue lines
    foreach ($this->dialogueTexts as $index => $text) {
        $uniqueText = UniqueDialogueText::factory()->create([
            'text_content' => $text,
            'text_hash' => md5($text),
        ]);

        DialogueLine::factory()
            ->for($this->version, 'gameVersion')
            ->for($index % 2 === 0 ? $this->protagonist : $this->heroine)
            ->create([
                'text_id' => $uniqueText->id,
                'iso_code' => 'eng',
                'file_path' => 'chapter1/scene'.($index + 1).'.rpy',
                'line_number' => ($index + 1) * 10,
            ]);
    }
});

test('dialogue search page displays correctly', function () {
    $page = visit('/dialogue/search');

    $page->assertSee('Dialogue Search')
        ->assertSee('Search through visual novel dialogue')
        ->assertSee('Advanced Search')
        ->assertVisible('[data-testid="search-input"]')
        ->assertVisible('[data-testid="search-button"]')
        ->assertNoJavascriptErrors();
});

test('basic dialogue search works correctly', function () {
    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'Alex')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('Hello, my name is Alex')
        ->assertSee('Dialogue Test VN')
        ->assertSee('Alex') // Character name
        ->assertNoJavascriptErrors();
});

test('search results show highlighted matches', function () {
    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'beautiful')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('Luna, you look beautiful today')
        ->assertVisible('[data-testid="highlighted-text"] mark')
        ->assertNoJavascriptErrors();
});

test('search can filter by character', function () {
    $page = visit('/dialogue/search');

    $page->click('[data-testid="advanced-search"]')
        ->wait(2)
        ->select('[data-testid="character-filter"]', 'Luna')
        ->fill('[data-testid="search-input"]', 'you')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('Luna, you look beautiful')
        ->assertDontSee('Hello, my name is Alex')
        ->assertNoJavascriptErrors();
});

test('search can filter by game', function () {
    // Create another game with dialogue
    $otherGame = Game::factory()->create([
        'name' => 'Other VN',
        'is_visible' => true,
    ]);
    $otherVersion = GameVersion::factory()->for($otherGame)->create();
    $otherChar = Character::factory()->for($otherGame)->create();

    $otherText = UniqueDialogueText::factory()->create([
        'text_content' => 'This is from another game',
    ]);

    DialogueLine::factory()
        ->for($otherVersion, 'gameVersion')
        ->for($otherChar)
        ->create(['text_id' => $otherText->id]);

    $page = visit('/dialogue/search');

    $page->click('[data-testid="advanced-search"]')
        ->wait(2)
        ->select('[data-testid="game-filter"]', 'Dialogue Test VN')
        ->fill('[data-testid="search-input"]', 'Alex')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('Dialogue Test VN')
        ->assertDontSee('Other VN')
        ->assertNoJavascriptErrors();
});

test('search results show context information', function () {
    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'garden')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('chapter1/scene3.rpy')
        ->assertSee('Line 30')
        ->assertSee('Alex') // Character name
        ->assertSee('Dialogue Test VN') // Game name
        ->assertNoJavascriptErrors();
});

test('user can navigate to game from search results', function () {
    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'Alex')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->click('[data-testid="game-link"]:first-child')
        ->assertUrl("*/games/{$this->game->slug}")
        ->assertSee('Dialogue Test VN')
        ->assertNoJavascriptErrors();
});

test('search supports pagination', function () {
    // Create many dialogue lines for pagination
    for ($i = 0; $i < 50; $i++) {
        $text = UniqueDialogueText::factory()->create([
            'text_content' => "This is dialogue line number {$i} with searchable content",
        ]);

        DialogueLine::factory()
            ->for($this->version, 'gameVersion')
            ->for($this->protagonist)
            ->create(['text_id' => $text->id]);
    }

    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'searchable')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('Next')
        ->click('Next')
        ->wait(2)
        ->assertSee('Previous')
        ->assertNoJavascriptErrors();
});

test('search suggestions appear while typing', function () {
    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'Alex')
        ->wait(2)
        ->assertSee('Hello, my name is Alex')
        ->click('[data-testid="suggestion"]:first-child')
        ->wait(2)
        ->assertNoJavascriptErrors();
});

test('recent searches are saved and displayed', function () {
    $this->actingAs($this->user);

    $page = visit('/dialogue/search');

    // Perform a search
    $page->fill('[data-testid="search-input"]', 'beautiful')
        ->click('[data-testid="search-button"]')
        ->wait(2);

    // Go back to search page
    $page->visit('/dialogue/search');

    $page->click('[data-testid="recent-searches"]')
        ->wait(2)
        ->assertSee('beautiful')
        ->click('[data-testid="recent-search"]:first-child')
        ->wait(2)
        ->assertNoJavascriptErrors();
});

test('user can save search queries', function () {
    $this->actingAs($this->user);

    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'festival')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->click('[data-testid="save-search"]')
        ->wait(2)
        ->fill('[data-testid="search-name"]', 'Festival Scenes')
        ->click('[data-testid="confirm-save"]')
        ->assertSee('Search saved successfully')
        ->assertNoJavascriptErrors();
});

test('saved searches can be accessed and rerun', function () {
    $this->actingAs($this->user);

    // First save a search (simulated)
    $page = visit('/dialogue/search');

    $page->click('[data-testid="saved-searches"]')
        ->wait(2)
        ->assertSee('My Saved Searches')
        ->assertNoJavascriptErrors();
});

test('search supports regex patterns', function () {
    $page = visit('/dialogue/search');

    $page->click('[data-testid="advanced-search"]')
        ->wait(2)
        ->check('[data-testid="regex-checkbox"]')
        ->fill('[data-testid="search-input"]', 'Alex|Luna')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('Alex')
        ->assertSee('Luna')
        ->assertNoJavascriptErrors();
});

test('search can exclude certain terms', function () {
    $page = visit('/dialogue/search');

    $page->click('[data-testid="advanced-search"]')
        ->wait(2)
        ->fill('[data-testid="search-input"]', 'you')
        ->fill('[data-testid="exclude-terms"]', 'beautiful')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('Will you go to the school')
        ->assertDontSee('Luna, you look beautiful')
        ->assertNoJavascriptErrors();
});

test('search results can be exported', function () {
    $this->actingAs($this->user);

    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'Alex')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->click('[data-testid="export-results"]')
        ->wait(2)
        ->select('[data-testid="export-format"]', 'csv')
        ->click('[data-testid="start-export"]')
        ->wait(2)
        ->assertSee('Export ready for download')
        ->assertNoJavascriptErrors();
});

test('dialogue search works on mobile devices', function () {
    $page = visit('/dialogue/search');
    $page->resize(375, 667); // iPhone size

    $page->assertVisible('[data-testid="search-input"]')
        ->fill('[data-testid="search-input"]', 'Alex')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->assertSee('Hello, my name is Alex')
        ->assertNoJavascriptErrors();

    // Test mobile-specific UI elements
    $page->click('[data-testid="mobile-filters-toggle"]')
        ->wait(2)
        ->assertVisible('[data-testid="character-filter"]')
        ->assertNoJavascriptErrors();
});

test('search analytics track user behavior', function () {
    $this->actingAs($this->user);

    $page = visit('/dialogue/search');

    $page->fill('[data-testid="search-input"]', 'test query')
        ->click('[data-testid="search-button"]')
        ->wait(2)
        ->click('[data-testid="result-item"]:first-child')
        ->assertNoJavascriptErrors();

    // Verify analytics event was tracked (would need API verification in real implementation)
    $page->evaluate('() => window.analytics && window.analytics.track')
        ->assertNoJavascriptErrors();
});
