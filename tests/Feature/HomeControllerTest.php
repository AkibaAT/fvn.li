<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\Tag;
use App\Models\User;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\MeilisearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        $version->is_latest = true;
        $version->save();

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        $teasers = $response->viewData('page')['props']['teasers'];

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
        DB::table('iso_639_3_languages')->insertOrIgnore([
            'id' => 'eng',
            'scope' => 'I',  // Individual language
            'type' => 'L',   // Living language
            'ref_name' => 'English',
            'part1' => 'en',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('iso_639_3_languages')->insertOrIgnore([
            'id' => 'jpn',
            'scope' => 'I',  // Individual language
            'type' => 'L',   // Living language
            'ref_name' => 'Japanese',
            'part1' => 'ja',
            'flag_code' => 'jp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $game = Game::factory()->create([
            'name' => 'Multilingual Game',
            'is_visible' => true,
        ]);

        $version = GameVersion::factory()->create([
            'game_id' => $game->id,
        ]);

        $version->is_latest = true;
        $version->save();

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

        VersionLanguageStats::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'words' => 50000,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        $teasers = $response->viewData('page')['props']['teasers'];

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
        $game = Game::factory()->create([
            'name' => 'Test Game with Stats',
            'is_visible' => true,
        ]);

        $version = GameVersion::factory()->create([
            'game_id' => $game->id,
        ]);

        $version->is_latest = true;
        $version->save();

        VersionLanguageStats::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'words' => 75000,
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        $teasers = $response->viewData('page')['props']['teasers'];

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
        $game = Game::factory()->create([
            'name' => 'Complete Test Game',
            'is_visible' => true,
            'is_nsfw' => true,
            'is_paid' => true,
            'has_demo' => true,
            'platform' => 'itch_io',
        ]);

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

        $teasers = $response->viewData('page')['props']['teasers'];

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

            DB::table('iso_639_3_languages')->insertOrIgnore([
                'id' => 'eng',
                'scope' => 'I',
                'type' => 'L',
                'ref_name' => 'English',
                'part1' => 'en',
                'flag_code' => 'gb',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            VersionSupportedLanguage::create([
                'game_version_id' => $version->id,
                'iso_code' => 'eng',
                'is_available' => true,
            ]);
        }

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        $teasers = $response->viewData('page')['props']['teasers'];

        // Verify all sections exist
        expect($teasers)->toHaveKeys(['recentlyAdded', 'recentlyUpdated', 'mostPopular']);

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

    test('home teasers enrich meilisearch results with relationships and authenticated user data', function () {
        Cache::flush();

        DB::table('iso_639_3_languages')->insertOrIgnore([
            'id' => 'eng',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'part1' => 'en',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        $game = Game::factory()->create([
            'name' => 'Mocked Search Home VN',
            'slug' => 'mocked-search-home-vn',
            'is_visible' => true,
            'source_language_id' => 'eng',
        ]);
        $ignoredGame = Game::factory()->create();
        $user->ignoredGames()->attach($ignoredGame->id);

        $version = GameVersion::factory()->latest()->create([
            'game_id' => $game->id,
            'is_windows' => true,
            'is_linux' => false,
            'is_mac' => true,
            'is_android' => false,
            'is_web' => true,
            'published_at' => now(),
        ]);

        VersionSupportedLanguage::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'is_available' => true,
        ]);
        VersionLanguageStats::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'words' => 12345,
        ]);

        DB::table('user_game_progress')->insert([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'status' => 'reading',
            'receive_updates' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $list = VnList::factory()->create([
            'user_id' => $user->id,
            'name' => 'Home List',
            'type' => 'custom',
            'is_default' => false,
        ]);
        VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
        ]);

        $search = Mockery::mock(MeilisearchService::class);
        $search
            ->shouldReceive('searchGames')
            ->times(3)
            ->withArgs(function (
                string $query,
                array $filters,
                int $perPage,
                int $page,
                string $sortField,
                string $sortDirection,
                array $ignoredGameIds
            ) use ($ignoredGame) {
                expect($query)->toBe('')
                    ->and($filters)->toBe([])
                    ->and($perPage)->toBe(4)
                    ->and($page)->toBe(1)
                    ->and($sortDirection)->toBe('desc')
                    ->and($sortField)->toBeIn(['first_visible_at', 'latest_version_published_at', 'trending_score'])
                    ->and($ignoredGameIds)->toBe([$ignoredGame->id]);

                return true;
            })
            ->andReturnUsing(fn () => new LengthAwarePaginator($game->newCollection([$game->fresh()]), 1, 4, 1));

        app()->instance(MeilisearchService::class, $search);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $firstGame = $props['teasers']['recentlyAdded'][0];

        expect($props['ignoredGameIds'])->toBe([$ignoredGame->id])
            ->and($firstGame['id'])->toBe($game->id)
            ->and($firstGame['is_windows'])->toBeTrue()
            ->and($firstGame['is_mac'])->toBeTrue()
            ->and($firstGame['is_web'])->toBeTrue()
            ->and($firstGame['latest_version_id'])->toBe($version->id)
            ->and($firstGame['english_word_count'])->toBe(12345)
            ->and($firstGame['primary_word_count'])->toBe(12345)
            ->and($firstGame['primary_language_label'])->toBe('EN')
            ->and($firstGame['supported_languages'][0]['iso_code'])->toBe('eng')
            ->and($firstGame['supported_languages'][0]['ref_name'])->toBe('English')
            ->and($firstGame['user_progress'][0]->receive_updates)->toBeTrue()
            ->and($firstGame['user_list_memberships'][0]->name)->toBe('Home List');
    });

    test('home teaser cache does not leak authenticated user data to later guests', function () {
        Cache::flush();

        $victim = User::factory()->create();
        $game = Game::factory()->create([
            'name' => 'Cached Teaser Privacy VN',
            'slug' => 'cached-teaser-privacy-vn',
            'is_visible' => true,
        ]);

        DB::table('user_game_progress')->insert([
            'user_id' => $victim->id,
            'game_id' => $game->id,
            'status' => 'reading',
            'receive_updates' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $list = VnList::factory()->create([
            'user_id' => $victim->id,
            'name' => 'Victim Private Wishlist',
            'type' => 'custom',
            'is_default' => false,
        ]);
        VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
        ]);

        $search = Mockery::mock(MeilisearchService::class);
        $search
            ->shouldReceive('searchGames')
            ->times(3)
            ->andReturnUsing(fn () => new LengthAwarePaginator($game->newCollection([$game->fresh()]), 1, 4, 1));

        app()->instance(MeilisearchService::class, $search);

        $victimResponse = $this->actingAs($victim)->get(route('home'));
        $victimResponse->assertOk();
        $victimGame = $victimResponse->viewData('page')['props']['teasers']['recentlyAdded'][0];

        Auth::logout();

        $guestResponse = $this->get(route('home'));
        $guestResponse->assertOk();
        $guestGame = $guestResponse->viewData('page')['props']['teasers']['recentlyAdded'][0];

        expect($victimGame['user_progress'][0]->receive_updates)->toBeTrue()
            ->and($victimGame['user_list_memberships'][0]->name)->toBe('Victim Private Wishlist')
            ->and($guestGame['id'])->toBe($game->id)
            ->and($guestGame['user_progress'])->toBe([])
            ->and($guestGame['user_list_memberships'])->toBe([]);
    });
});
