<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\Tag;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Home Page Game Card Regression Tests
 *
 * These tests ensure that game cards on the home page always include
 * complete platform and language information. This prevents regressions
 * where game card data gets lost during refactors (like the multi-platform rework).
 *
 * Key areas tested:
 * - Platform flags (Windows, Linux, Mac, Android, Web)
 * - Language information (iso_code, ref_name, flag_code)
 * - English word count
 * - All required GameCard component properties
 * - Data consistency across all teaser sections
 *
 * If these tests fail, the home page game cards are missing critical information
 * that users rely on to make decisions about which games to explore.
 */
describe('Home Page Game Cards', function () {
    test('home page loads successfully', function () {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
    });

    test('game cards include platform information', function () {
        // Create a game with a latest version that has platform data
        $game = Game::factory()->create([
            'name' => 'Test Game',
            'is_visible' => true,
        ]);

        $version = GameVersion::factory()->create([
            'game_id' => $game->id,
            'is_windows' => true,
            'is_linux' => true,
            'is_mac' => false,
            'is_android' => true,
            'is_web' => false,
        ]);

        // Set as latest version
        $version->is_latest = true;
        $version->save();

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        // Get the teasers data from Inertia
        $teasers = $response->viewData('page')['props']['teasers'];

        // Check all teaser sections (recentlyAdded, recentlyUpdated, mostPopular)
        foreach (['recentlyAdded', 'recentlyUpdated', 'mostPopular'] as $section) {
            if (isset($teasers[$section]) && count($teasers[$section]) > 0) {
                $firstGame = $teasers[$section][0];

                // Verify platform flags are present
                expect($firstGame)->toHaveKeys([
                    'is_windows',
                    'is_linux',
                    'is_mac',
                    'is_android',
                    'is_web',
                ]);

                // Verify the platform data matches what we set
                if ($firstGame['id'] === $game->id) {
                    expect($firstGame['is_windows'])->toBe(true);
                    expect($firstGame['is_linux'])->toBe(true);
                    expect($firstGame['is_mac'])->toBe(false);
                    expect($firstGame['is_android'])->toBe(true);
                    expect($firstGame['is_web'])->toBe(false);
                }
            }
        }
    });

    test('game cards include language information with correct structure', function () {
        // Create languages with all required fields for ISO 639-3
        $english = Language::create([
            'id' => 'eng',
            'scope' => 'I',  // Individual language
            'type' => 'L',   // Living language
            'ref_name' => 'English',
            'part1' => 'en',
            'flag_code' => 'gb',
        ]);

        $japanese = Language::create([
            'id' => 'jpn',
            'scope' => 'I',  // Individual language
            'type' => 'L',   // Living language
            'ref_name' => 'Japanese',
            'part1' => 'ja',
            'flag_code' => 'jp',
        ]);

        // Create a game with a latest version
        $game = Game::factory()->create([
            'name' => 'Multilingual Game',
            'is_visible' => true,
        ]);

        $version = GameVersion::factory()->create([
            'game_id' => $game->id,
        ]);

        // Set as latest version
        $version->is_latest = true;
        $version->save();

        // Add supported languages
        VersionSupportedLanguage::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'is_available' => true,
        ]);

        VersionSupportedLanguage::create([
            'game_version_id' => $version->id,
            'iso_code' => 'jpn',
            'is_available' => true,
        ]);

        // Add English word count
        VersionLanguageStats::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'words' => 50000,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        // Get the teasers data from Inertia
        $teasers = $response->viewData('page')['props']['teasers'];

        // Check all teaser sections
        foreach (['recentlyAdded', 'recentlyUpdated', 'mostPopular'] as $section) {
            if (isset($teasers[$section]) && count($teasers[$section]) > 0) {
                $firstGame = $teasers[$section][0];

                // Verify supported_languages exists and is an array/collection
                expect($firstGame)->toHaveKey('supported_languages');

                // If this is our test game, verify the language structure
                if ($firstGame['id'] === $game->id) {
                    $supportedLanguages = $firstGame['supported_languages'];

                    // Should have 2 languages
                    expect(count($supportedLanguages))->toBeGreaterThanOrEqual(2);

                    // Verify each language has the required structure
                    foreach ($supportedLanguages as $language) {
                        expect($language)->toHaveKeys([
                            'iso_code',
                            'ref_name',
                            'flag_code',
                        ]);
                    }

                    // Verify specific language data
                    $isoCodes = array_column($supportedLanguages, 'iso_code');
                    expect($isoCodes)->toContain('eng');
                    expect($isoCodes)->toContain('jpn');
                }
            }
        }
    });

    test('game cards include english word count', function () {
        // Create a game with English word count
        $game = Game::factory()->create([
            'name' => 'Test Game with Stats',
            'is_visible' => true,
        ]);

        $version = GameVersion::factory()->create([
            'game_id' => $game->id,
        ]);

        $version->is_latest = true;
        $version->save();

        // Add English word count
        VersionLanguageStats::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'words' => 75000,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        // Get the teasers data from Inertia
        $teasers = $response->viewData('page')['props']['teasers'];

        // Check all teaser sections
        foreach (['recentlyAdded', 'recentlyUpdated', 'mostPopular'] as $section) {
            if (isset($teasers[$section]) && count($teasers[$section]) > 0) {
                $firstGame = $teasers[$section][0];

                // Verify english_word_count exists
                expect($firstGame)->toHaveKey('english_word_count');

                // If this is our test game, verify the word count
                if ($firstGame['id'] === $game->id) {
                    expect($firstGame['english_word_count'])->toBe(75000);
                }
            }
        }
    });

    test('game cards include required GameCard component properties', function () {
        // Create a complete game with all required data
        $game = Game::factory()->create([
            'name' => 'Complete Test Game',
            'is_visible' => true,
            'is_nsfw' => true,
            'is_paid' => true,
            'has_demo' => true,
            'platform' => 'itch_io',
        ]);

        // Create a tag directly
        $tag = Tag::create([
            'name' => 'Romance',
            'slug' => 'romance',
        ]);
        $game->tags()->attach($tag);

        $version = GameVersion::factory()->latest()->create([
            'game_id' => $game->id,
            'is_windows' => true,
            'is_linux' => true,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        // Get the teasers data from Inertia
        $teasers = $response->viewData('page')['props']['teasers'];

        // Check all teaser sections
        foreach (['recentlyAdded', 'recentlyUpdated', 'mostPopular'] as $section) {
            if (isset($teasers[$section]) && count($teasers[$section]) > 0) {
                $firstGame = $teasers[$section][0];

                // Verify all required properties for GameCard component
                expect($firstGame)->toHaveKeys([
                    'id',
                    'name',
                    'effective_name',
                    'slug',
                    'platform',  // Store platform (itch_io, steam, other)
                    'is_windows',  // Game platform flags
                    'is_linux',
                    'is_mac',
                    'is_android',
                    'is_web',
                    'supported_languages',
                    'tags',
                ]);

                // If this is our test game, verify the data
                if ($firstGame['id'] === $game->id) {
                    expect($firstGame['platform'])->toBe('itch_io');
                    expect($firstGame['is_windows'])->toBe(true);
                    expect($firstGame['is_linux'])->toBe(true);
                    expect($firstGame['is_nsfw'])->toBe(true);
                    expect($firstGame['is_paid'])->toBe(true);
                    expect($firstGame['has_demo'])->toBe(true);
                    expect($firstGame['effective_name'])->not->toBeNull();
                }
            }
        }
    });

    test('multiple games in each teaser section have consistent data structure', function () {
        // Create multiple games to ensure all sections are populated
        for ($i = 1; $i <= 5; $i++) {
            $game = Game::factory()->create([
                'name' => "Test Game {$i}",
                'is_visible' => true,
            ]);

            $version = GameVersion::factory()->latest()->create([
                'game_id' => $game->id,
                'is_windows' => true,
                'is_linux' => fake()->boolean(),
                'is_mac' => fake()->boolean(),
                'published_at' => now()->subDays($i),
            ]);

            // Create English language if it doesn't exist
            Language::firstOrCreate(
                ['id' => 'eng'],
                [
                    'scope' => 'I',
                    'type' => 'L',
                    'ref_name' => 'English',
                    'part1' => 'en',
                    'flag_code' => 'gb',
                ]
            );

            VersionSupportedLanguage::create([
                'game_version_id' => $version->id,
                'iso_code' => 'eng',
                'is_available' => true,
            ]);
        }

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        // Get the teasers data from Inertia
        $teasers = $response->viewData('page')['props']['teasers'];

        // Verify all sections exist
        expect($teasers)->toHaveKeys(['recentlyAdded', 'recentlyUpdated', 'mostPopular']);

        // Check each section has games with consistent structure
        foreach (['recentlyAdded', 'recentlyUpdated', 'mostPopular'] as $section) {
            expect($teasers[$section])->toBeArray();

            if (count($teasers[$section]) > 0) {
                foreach ($teasers[$section] as $game) {
                    // Every game must have these critical properties
                    expect($game)->toHaveKeys([
                        'id',
                        'name',
                        'effective_name',
                        'is_windows',
                        'is_linux',
                        'is_mac',
                        'is_android',
                        'is_web',
                        'supported_languages',
                    ]);

                    // Platform flags must be boolean
                    expect($game['is_windows'])->toBeIn([true, false]);
                    expect($game['is_linux'])->toBeIn([true, false]);
                    expect($game['is_mac'])->toBeIn([true, false]);
                    expect($game['is_android'])->toBeIn([true, false]);
                    expect($game['is_web'])->toBeIn([true, false]);

                    // supported_languages must be an array
                    expect($game['supported_languages'])->toBeArray();
                }
            }
        }
    });
});
