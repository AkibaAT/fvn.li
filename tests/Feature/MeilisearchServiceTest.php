<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rating;
use App\Models\Tag;
use App\Models\UniqueDialogueText;
use App\Models\User;
use App\Services\MeilisearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MeilisearchService();
    
    // Skip if Meilisearch is not available
    if (! config('scout.meilisearch.host')) {
        $this->markTestSkipped('Meilisearch is not configured');
    }
});

describe('Game Search', function () {
    test('searches games by name', function () {
        // Create games
        $game1 = Game::factory()->create(['name' => 'Doki Doki Literature Club']);
        $game2 = Game::factory()->create(['name' => 'Katawa Shoujo']);
        $game3 = Game::factory()->create(['name' => 'Everlasting Summer']);

        // Wait for indexing
        sleep(1);

        $results = $this->service->searchGames('Doki');

        expect($results->total())->toBeGreaterThan(0);
        
        $resultNames = $results->pluck('name')->toArray();
        expect($resultNames)->toContain('Doki Doki Literature Club');
    })->skip('Requires Meilisearch server');

    test('searches games with tag filters', function () {
        $tag = Tag::factory()->create(['name' => 'Romance']);
        
        $game1 = Game::factory()->create(['name' => 'Romance Game']);
        $game1->tags()->attach($tag);
        
        $game2 = Game::factory()->create(['name' => 'Action Game']);

        sleep(1);

        $results = $this->service->searchGames('Game', [
            'tags' => ['Romance'],
        ]);

        expect($results->total())->toBe(1);
        expect($results->first()->name)->toBe('Romance Game');
    })->skip('Requires Meilisearch server');

    test('searches games with platform filters', function () {
        $game1 = Game::factory()->create([
            'name' => 'Windows Game',
            'platforms' => ['windows'],
        ]);
        
        $game2 = Game::factory()->create([
            'name' => 'Linux Game',
            'platforms' => ['linux'],
        ]);

        sleep(1);

        $results = $this->service->searchGames('Game', [
            'platforms' => ['windows'],
        ]);

        expect($results->total())->toBe(1);
        expect($results->first()->name)->toBe('Windows Game');
    })->skip('Requires Meilisearch server');

    test('searches games with language filters', function () {
        $game1 = Game::factory()->create([
            'name' => 'English Game',
            'supported_languages' => ['en'],
        ]);
        
        $game2 = Game::factory()->create([
            'name' => 'Japanese Game',
            'supported_languages' => ['ja'],
        ]);

        sleep(1);

        $results = $this->service->searchGames('Game', [
            'languages' => ['en'],
        ]);

        expect($results->total())->toBe(1);
        expect($results->first()->name)->toBe('English Game');
    })->skip('Requires Meilisearch server');

    test('handles typos with fuzzy matching', function () {
        $game = Game::factory()->create(['name' => 'Katawa Shoujo']);

        sleep(1);

        // Search with typo
        $results = $this->service->searchGames('Katwa Shojo');

        expect($results->total())->toBeGreaterThan(0);
        expect($results->first()->name)->toBe('Katawa Shoujo');
    })->skip('Requires Meilisearch server');

    test('paginates search results', function () {
        // Create 25 games
        for ($i = 1; $i <= 25; $i++) {
            Game::factory()->create(['name' => "Test Game {$i}"]);
        }

        sleep(1);

        $page1 = $this->service->searchGames('Test Game', [], 10, 1);
        $page2 = $this->service->searchGames('Test Game', [], 10, 2);

        expect($page1->count())->toBe(10)
            ->and($page2->count())->toBeGreaterThan(0)
            ->and($page1->total())->toBe(25);
    })->skip('Requires Meilisearch server');

    test('sorts search results', function () {
        $game1 = Game::factory()->create([
            'name' => 'Game A',
            'first_visible_at' => now()->subDays(10),
        ]);
        
        $game2 = Game::factory()->create([
            'name' => 'Game B',
            'first_visible_at' => now()->subDays(5),
        ]);

        sleep(1);

        $results = $this->service->searchGames('Game', [], 20, 1, 'first_visible_at', 'desc');

        expect($results->first()->name)->toBe('Game B'); // More recent
    })->skip('Requires Meilisearch server');
});

describe('Dialogue Search', function () {
    test('searches dialogue by text', function () {
        $text1 = UniqueDialogueText::factory()->create([
            'text' => 'Hello, how are you?',
        ]);
        
        $text2 = UniqueDialogueText::factory()->create([
            'text' => 'Goodbye, see you later!',
        ]);

        sleep(1);

        $results = $this->service->searchDialogue('Hello');

        expect($results->total())->toBeGreaterThan(0);
        expect($results->first()->text)->toContain('Hello');
    })->skip('Requires Meilisearch server');

    test('filters dialogue by language', function () {
        $text1 = UniqueDialogueText::factory()->create([
            'text' => 'Hello',
            'languages' => ['en'],
        ]);
        
        $text2 = UniqueDialogueText::factory()->create([
            'text' => 'こんにちは',
            'languages' => ['ja'],
        ]);

        sleep(1);

        $results = $this->service->searchDialogue('', [
            'language' => 'en',
        ]);

        expect($results->total())->toBe(1);
        expect($results->first()->text)->toBe('Hello');
    })->skip('Requires Meilisearch server');

    test('filters dialogue by game', function () {
        $game1 = Game::factory()->create(['name' => 'Game 1']);
        $game2 = Game::factory()->create(['name' => 'Game 2']);

        $text1 = UniqueDialogueText::factory()->create([
            'text' => 'Dialogue from game 1',
            'game_names' => ['Game 1'],
        ]);
        
        $text2 = UniqueDialogueText::factory()->create([
            'text' => 'Dialogue from game 2',
            'game_names' => ['Game 2'],
        ]);

        sleep(1);

        $results = $this->service->searchDialogue('Dialogue', [
            'game_names' => ['Game 1'],
        ]);

        expect($results->total())->toBe(1);
        expect($results->first()->text)->toContain('game 1');
    })->skip('Requires Meilisearch server');

    test('filters dialogue by character', function () {
        $text1 = UniqueDialogueText::factory()->create([
            'text' => 'Hello from Alice',
            'character_names' => ['Alice'],
        ]);
        
        $text2 = UniqueDialogueText::factory()->create([
            'text' => 'Hello from Bob',
            'character_names' => ['Bob'],
        ]);

        sleep(1);

        $results = $this->service->searchDialogue('Hello', [
            'character_names' => ['Alice'],
        ]);

        expect($results->total())->toBe(1);
        expect($results->first()->text)->toContain('Alice');
    })->skip('Requires Meilisearch server');
});

describe('Review Search', function () {
    test('searches reviews by text', function () {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $rating1 = Rating::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'review' => 'This is an amazing game!',
        ]);
        
        $rating2 = Rating::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'review' => 'This is a terrible game!',
        ]);

        sleep(1);

        $results = $this->service->searchReviews('amazing');

        expect($results->total())->toBeGreaterThan(0);
        expect($results->first()->review)->toContain('amazing');
    })->skip('Requires Meilisearch server');

    test('filters reviews by rating range', function () {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $rating1 = Rating::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'rating' => 5,
            'review' => 'Great game',
        ]);
        
        $rating2 = Rating::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'rating' => 2,
            'review' => 'Bad game',
        ]);

        sleep(1);

        $results = $this->service->searchReviews('game', [
            'min_rating' => 4,
        ]);

        expect($results->total())->toBe(1);
        expect($results->first()->rating)->toBe(5);
    })->skip('Requires Meilisearch server');
});

