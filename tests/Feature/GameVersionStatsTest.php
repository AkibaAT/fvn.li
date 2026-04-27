<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionCharacterStats;
use App\Models\VersionFileCategory;
use App\Models\VersionFileType;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use App\Services\GameStatsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

uses()->group('version-stats');

beforeEach(function () {
    config()->set('scout.driver', 'null');
    $this->originalEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    if (! DB::table('iso_639_3_languages')->where('id', 'eng')->exists()) {
        DB::table('iso_639_3_languages')->insert([
            'id' => 'eng',
            'part2b' => 'eng',
            'part2t' => 'eng',
            'part1' => 'en',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    if (! DB::table('iso_639_3_languages')->where('id', 'fra')->exists()) {
        DB::table('iso_639_3_languages')->insert([
            'id' => 'fra',
            'part2b' => 'fre',
            'part2t' => 'fra',
            'part1' => 'fr',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'French',
            'flag_code' => 'fr',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Create a test game
    $this->game = Game::withoutEvents(fn () => Game::factory()->create([
        'name' => 'Test Visual Novel',
        'slug' => 'test-visual-novel',
    ]));

    // Create a test version
    $this->version = GameVersion::withoutEvents(fn () => GameVersion::create([
        'game_id' => $this->game->id,
        'version' => '1.0.0',
        'published_at' => now(),
        'is_latest' => true,
    ]));

    VersionSupportedLanguage::updateOrCreate(
        [
            'game_version_id' => $this->version->id,
            'iso_code' => 'eng',
        ],
        ['is_available' => true]
    );

    VersionSupportedLanguage::updateOrCreate(
        [
            'game_version_id' => $this->version->id,
            'iso_code' => 'fra',
        ],
        ['is_available' => true]
    );
});

afterEach(function () {
    if ($this->originalEventDispatcher) {
        Model::setEventDispatcher($this->originalEventDispatcher);
    }
});

describe('character stats endpoint', function () {
    test('returns 404 when game not found', function () {
        $response = $this->getJson("/react-api/games/non-existent-game/versions/{$this->version->id}/character-stats");

        $response->assertStatus(404);
    });

    test('returns 404 when version not found', function () {
        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/99999/character-stats");

        $response->assertStatus(404);
    });

    test('returns empty data when no character stats exist', function () {
        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/character-stats");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'characters' => [],
                    'languages' => [],
                    'wordCounts' => [],
                    'languageTotals' => [],
                ],
            ]);
    });

    test('returns character stats with correct structure', function () {
        // Create test characters
        $character1 = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'protagonist',
            'display_names' => ['eng' => 'Alex', 'fra' => 'Alexandre'],
        ]);

        $character2 = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'narrator',
            'display_names' => ['eng' => 'Narrator', 'fra' => 'Narrateur'],
        ]);

        // Create character stats
        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $character1->id,
            'iso_code' => 'eng',
            'blocks' => 50,
            'words' => 1000,
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $character1->id,
            'iso_code' => 'fra',
            'blocks' => 52,
            'words' => 1100,
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $character2->id,
            'iso_code' => 'eng',
            'blocks' => 100,
            'words' => 5000,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/character-stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'characters',
                    'languages' => [
                        '*' => ['id', 'flag', 'name'],
                    ],
                    'wordCounts',
                    'languageTotals',
                ],
            ]);

        $data = $response->json('data');

        // Verify characters are sorted alphabetically (case-insensitive)
        expect($data['characters'])->toBeArray();
        expect($data['characters'])->toContain('Alex');
        expect($data['characters'])->toContain('Narrator');

        // Verify English appears first in languages
        expect($data['languages'][0]['id'])->toBe('eng');
        expect($data['languages'][0]['name'])->toBe('English');
        expect($data['languages'][0]['flag'])->toBe('gb');
    });

    test('sorts characters alphabetically case-insensitive', function () {
        // Create characters with mixed case names
        $charA = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'char_a',
            'display_names' => ['eng' => 'alice'],
        ]);

        $charB = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'char_b',
            'display_names' => ['eng' => 'Bob'],
        ]);

        $charC = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'char_c',
            'display_names' => ['eng' => 'Charlie'],
        ]);

        // Create stats
        foreach ([$charA, $charB, $charC] as $char) {
            VersionCharacterStats::create([
                'game_version_id' => $this->version->id,
                'character_id' => $char->id,
                'iso_code' => 'eng',
                'blocks' => 10,
                'words' => 100,
            ]);
        }

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/character-stats");

        $data = $response->json('data');
        $characters = $data['characters'];

        // Verify case-insensitive alphabetical order
        expect($characters[0])->toBe('alice');
        expect($characters[1])->toBe('Bob');
        expect($characters[2])->toBe('Charlie');
    });

    test('ensures english language appears first', function () {
        // Create a character with multiple languages
        $character = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'test_char',
            'display_names' => ['eng' => 'Test', 'fra' => 'Test'],
        ]);

        // Create stats for French first (alphabetically before English)
        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $character->id,
            'iso_code' => 'fra',
            'blocks' => 10,
            'words' => 100,
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $character->id,
            'iso_code' => 'eng',
            'blocks' => 10,
            'words' => 100,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/character-stats");

        $data = $response->json('data');

        // English should always be first
        expect($data['languages'][0]['id'])->toBe('eng');
        expect($data['languages'][1]['id'])->toBe('fra');
    });

    test('returns correct word counts per character and language', function () {
        $character = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'test_char',
            'display_names' => ['eng' => 'TestChar'],
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $character->id,
            'iso_code' => 'eng',
            'blocks' => 50,
            'words' => 1234,
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $character->id,
            'iso_code' => 'fra',
            'blocks' => 52,
            'words' => 5678,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/character-stats");

        $data = $response->json('data');
        $wordCounts = $data['wordCounts'];

        // Verify word counts for our character
        expect($wordCounts)->toHaveKey('TestChar');
        expect($wordCounts['TestChar']['eng'])->toBe(1234);
        expect($wordCounts['TestChar']['fra'])->toBe(5678);
    });
});

describe('file stats endpoint', function () {
    test('returns 404 when game not found', function () {
        $response = $this->getJson("/react-api/games/non-existent-game/versions/{$this->version->id}/file-stats");

        $response->assertStatus(404);
    });

    test('returns 404 when version not found', function () {
        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/99999/file-stats");

        $response->assertStatus(404);
    });

    test('returns empty data when no file stats exist', function () {
        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/file-stats");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'file_categories' => [],
                ],
            ]);
    });

    test('returns file stats with correct structure', function () {
        // Create file categories
        $imageCategory = VersionFileCategory::create([
            'game_version_id' => $this->version->id,
            'category' => 'image',
            'total_count' => 150,
            'total_size' => 50000000, // 50 MB
        ]);

        $audioCategory = VersionFileCategory::create([
            'game_version_id' => $this->version->id,
            'category' => 'audio',
            'total_count' => 25,
            'total_size' => 75000000, // 75 MB
        ]);

        // Create file types for image category
        VersionFileType::create([
            'version_file_category_id' => $imageCategory->id,
            'extension' => 'webp',
            'count' => 100,
            'size' => 30000000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $imageCategory->id,
            'extension' => 'png',
            'count' => 50,
            'size' => 20000000,
        ]);

        // Create file types for audio category
        VersionFileType::create([
            'version_file_category_id' => $audioCategory->id,
            'extension' => 'ogg',
            'count' => 25,
            'size' => 75000000,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/file-stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'version',
                    'file_categories' => [
                        '*' => [
                            'category',
                            'total_count',
                            'total_size',
                            'file_types' => [
                                '*' => ['extension', 'count', 'size'],
                            ],
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');

        // Verify we have both categories
        expect($data['file_categories'])->toHaveCount(2);

        // Find image category
        $imageData = collect($data['file_categories'])->firstWhere('category', 'image');
        expect($imageData)->not->toBeNull();
        expect($imageData['total_count'])->toBe(150);
        expect($imageData['total_size'])->toBe(50000000);
        expect($imageData['file_types'])->toHaveCount(2);

        // Find audio category
        $audioData = collect($data['file_categories'])->firstWhere('category', 'audio');
        expect($audioData)->not->toBeNull();
        expect($audioData['total_count'])->toBe(25);
        expect($audioData['total_size'])->toBe(75000000);
        expect($audioData['file_types'])->toHaveCount(1);
    });

    test('returns correct file type details', function () {
        $category = VersionFileCategory::create([
            'game_version_id' => $this->version->id,
            'category' => 'image',
            'total_count' => 100,
            'total_size' => 10000000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $category->id,
            'extension' => 'webp',
            'count' => 75,
            'size' => 7500000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $category->id,
            'extension' => 'png',
            'count' => 25,
            'size' => 2500000,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/file-stats");

        $data = $response->json('data');
        $imageCategory = collect($data['file_categories'])->firstWhere('category', 'image');
        $fileTypes = $imageCategory['file_types'];

        // Verify file type details
        $webpType = collect($fileTypes)->firstWhere('extension', 'webp');
        expect($webpType['count'])->toBe(75);
        expect($webpType['size'])->toBe(7500000);

        $pngType = collect($fileTypes)->firstWhere('extension', 'png');
        expect($pngType['count'])->toBe(25);
        expect($pngType['size'])->toBe(2500000);
    });

    test('handles all file categories correctly', function () {
        // Create all possible categories
        $categories = ['image', 'audio', 'video', 'other'];

        foreach ($categories as $index => $categoryName) {
            $category = VersionFileCategory::create([
                'game_version_id' => $this->version->id,
                'category' => $categoryName,
                'total_count' => ($index + 1) * 10,
                'total_size' => ($index + 1) * 1000000,
            ]);

            VersionFileType::create([
                'version_file_category_id' => $category->id,
                'extension' => 'test',
                'count' => ($index + 1) * 10,
                'size' => ($index + 1) * 1000000,
            ]);
        }

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/file-stats");

        $data = $response->json('data');

        // Verify all categories are present
        expect($data['file_categories'])->toHaveCount(4);

        $categoryNames = collect($data['file_categories'])->pluck('category')->toArray();
        expect($categoryNames)->toContain('image');
        expect($categoryNames)->toContain('audio');
        expect($categoryNames)->toContain('video');
        expect($categoryNames)->toContain('other');
    });

    test('returns correct totals across multiple file types', function () {
        $category = VersionFileCategory::create([
            'game_version_id' => $this->version->id,
            'category' => 'image',
            'total_count' => 200,
            'total_size' => 100000000,
        ]);

        // Create multiple file types
        VersionFileType::create([
            'version_file_category_id' => $category->id,
            'extension' => 'webp',
            'count' => 100,
            'size' => 50000000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $category->id,
            'extension' => 'png',
            'count' => 75,
            'size' => 37500000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $category->id,
            'extension' => 'jpg',
            'count' => 25,
            'size' => 12500000,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/file-stats");

        $data = $response->json('data');
        $imageCategory = collect($data['file_categories'])->firstWhere('category', 'image');

        // Verify totals match
        expect($imageCategory['total_count'])->toBe(200);
        expect($imageCategory['total_size'])->toBe(100000000);
        expect($imageCategory['file_types'])->toHaveCount(3);
    });
});

describe('version comparison endpoint', function () {
    test('returns aggregated diffs with chronological versions and compact version summaries', function () {
        cache()->flush();

        $this->game->update(['source_language_id' => 'fra']);
        $this->version->update([
            'version' => '1.0.0',
            'published_at' => now()->subDays(2),
            'is_latest' => false,
        ]);

        $newVersion = GameVersion::withoutEvents(fn () => GameVersion::create([
            'game_id' => $this->game->id,
            'version' => '1.1.0',
            'published_at' => now()->subDay(),
            'is_latest' => true,
        ]));

        foreach ([$this->version->id, $newVersion->id] as $versionId) {
            VersionSupportedLanguage::updateOrCreate(
                [
                    'game_version_id' => $versionId,
                    'iso_code' => 'eng',
                ],
                ['is_available' => true]
            );

            VersionSupportedLanguage::updateOrCreate(
                [
                    'game_version_id' => $versionId,
                    'iso_code' => 'fra',
                ],
                ['is_available' => true]
            );
        }

        $alexBefore = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'alex_before',
            'display_names' => ['eng' => 'Alex', 'fra' => 'Alexandre'],
        ]);

        $alexAfter = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'alex_after',
            'display_names' => ['eng' => 'Alex', 'fra' => 'Alexandre'],
        ]);

        $bea = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'bea',
            'display_names' => ['eng' => 'Bea', 'fra' => 'Beatrice'],
        ]);

        collect([
            [
                'game_version_id' => $this->version->id,
                'character_id' => $alexBefore->id,
                'iso_code' => 'eng',
                'blocks' => 10,
                'words' => 100,
            ],
            [
                'game_version_id' => $this->version->id,
                'character_id' => $alexBefore->id,
                'iso_code' => 'fra',
                'blocks' => 12,
                'words' => 120,
            ],
            [
                'game_version_id' => $this->version->id,
                'character_id' => $bea->id,
                'iso_code' => 'eng',
                'blocks' => 5,
                'words' => 50,
            ],
            [
                'game_version_id' => $newVersion->id,
                'character_id' => $alexAfter->id,
                'iso_code' => 'eng',
                'blocks' => 18,
                'words' => 180,
            ],
            [
                'game_version_id' => $newVersion->id,
                'character_id' => $alexAfter->id,
                'iso_code' => 'fra',
                'blocks' => 20,
                'words' => 200,
            ],
            [
                'game_version_id' => $newVersion->id,
                'character_id' => $bea->id,
                'iso_code' => 'eng',
                'blocks' => 6,
                'words' => 60,
            ],
        ])->each(fn (array $attributes) => VersionCharacterStats::create($attributes));

        $imageBefore = VersionFileCategory::create([
            'game_version_id' => $this->version->id,
            'category' => 'image',
            'total_count' => 10,
            'total_size' => 1000,
        ]);

        $imageAfter = VersionFileCategory::create([
            'game_version_id' => $newVersion->id,
            'category' => 'image',
            'total_count' => 12,
            'total_size' => 1500,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $imageBefore->id,
            'extension' => 'png',
            'count' => 10,
            'size' => 1000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $imageAfter->id,
            'extension' => 'png',
            'count' => 12,
            'size' => 1500,
        ]);

        $response = $this->getJson(
            "/react-api/games/{$this->game->id}/compare-versions?fromVersionId={$newVersion->id}&toVersionId={$this->version->id}"
        );

        $response->assertStatus(200);

        $data = $response->json();

        expect($data['fromVersion']['id'])->toBe($this->version->id);
        expect($data['toVersion']['id'])->toBe($newVersion->id);
        expect($data['fromVersion'])->toHaveKeys(['id', 'version', 'published_at']);
        expect($data['fromVersion'])->not->toHaveKey('game_id');

        expect($data['characters'])->toContain('Alexandre');
        expect($data['characters'])->toContain('Beatrice');
        expect($data['languages'][0]['id'])->toBe('eng');
        expect($data['languages'][1]['id'])->toBe('fra');

        expect($data['characterDiffs']['Alexandre']['eng'])->toMatchArray([
            'from' => 100,
            'to' => 180,
            'diff' => 80,
        ]);
        expect($data['characterDiffs']['Alexandre']['fra'])->toMatchArray([
            'from' => 120,
            'to' => 200,
            'diff' => 80,
        ]);
        expect($data['languageTotals']['from']['eng'])->toBe(150);
        expect($data['languageTotals']['to']['eng'])->toBe(240);
        expect($data['languageTotals']['diff']['eng'])->toBe(90);

        $imageComparison = collect($data['fileCategories'])->firstWhere('category', 'image');
        expect($imageComparison)->not->toBeNull();
        expect($imageComparison['diff'])->toMatchArray([
            'count' => 2,
            'size' => 500,
        ]);
        expect($imageComparison['fileTypes']['png']['diff'])->toMatchArray([
            'count' => 2,
            'size' => 500,
        ]);
    });

    test('omits unavailable languages from comparison output', function () {
        cache()->flush();

        $olderVersion = $this->version;
        $olderVersion->update([
            'published_at' => now()->subDays(2),
            'is_latest' => false,
        ]);

        $newVersion = GameVersion::withoutEvents(fn () => GameVersion::create([
            'game_id' => $this->game->id,
            'version' => '2.0.0',
            'published_at' => now()->subDay(),
            'is_latest' => true,
        ]));

        foreach ([$olderVersion->id, $newVersion->id] as $versionId) {
            VersionSupportedLanguage::updateOrCreate(
                [
                    'game_version_id' => $versionId,
                    'iso_code' => 'eng',
                ],
                ['is_available' => true]
            );

            VersionSupportedLanguage::updateOrCreate(
                [
                    'game_version_id' => $versionId,
                    'iso_code' => 'fra',
                ],
                ['is_available' => false]
            );
        }

        $character = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'tester',
            'display_names' => ['eng' => 'Tester', 'fra' => 'Testeur'],
        ]);

        collect([
            [
                'game_version_id' => $olderVersion->id,
                'character_id' => $character->id,
                'iso_code' => 'eng',
                'blocks' => 10,
                'words' => 100,
            ],
            [
                'game_version_id' => $olderVersion->id,
                'character_id' => $character->id,
                'iso_code' => 'fra',
                'blocks' => 10,
                'words' => 100,
            ],
            [
                'game_version_id' => $newVersion->id,
                'character_id' => $character->id,
                'iso_code' => 'eng',
                'blocks' => 20,
                'words' => 200,
            ],
            [
                'game_version_id' => $newVersion->id,
                'character_id' => $character->id,
                'iso_code' => 'fra',
                'blocks' => 20,
                'words' => 200,
            ],
        ])->each(fn (array $attributes) => VersionCharacterStats::create($attributes));

        $response = $this->getJson(
            "/react-api/games/{$this->game->id}/compare-versions?fromVersionId={$olderVersion->id}&toVersionId={$newVersion->id}"
        );

        $response->assertStatus(200);

        $data = $response->json();

        expect($data['languages'])->toHaveCount(1);
        expect($data['languages'][0]['id'])->toBe('eng');
        expect($data['characterDiffs']['Tester'])->toHaveKey('eng');
        expect($data['characterDiffs']['Tester'])->not->toHaveKey('fra');
        expect($data['languageTotals']['diff'])->not->toHaveKey('fra');
    });
});

describe('regression prevention', function () {
    test('stats import clears stale language word counts before reindexing game data', function () {
        VersionLanguageStats::create([
            'game_version_id' => $this->version->id,
            'iso_code' => 'eng',
            'blocks' => 10,
            'words' => 75000,
            'menus' => 0,
            'options' => 0,
        ]);

        app(GameStatsService::class)->saveVersionStats(
            $this->version,
            [
                'languages' => [
                    'fra' => [
                        'blocks' => 3,
                        'words' => 12000,
                        'menus' => 0,
                        'options' => 0,
                        'characters' => [],
                    ],
                ],
            ],
            'eng',
            $this->game
        );

        expect($this->version->languageStats()->where('iso_code', 'eng')->exists())->toBeFalse()
            ->and($this->version->languageStats()->where('iso_code', 'fra')->value('words'))->toBe(12000)
            ->and($this->game->fresh()->toSearchableArray()['english_word_count'])->toBeNull();
    });

    test('character stats endpoint does not return 500 error', function () {
        // This test ensures the endpoint doesn't crash even with complex data
        $char1 = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'char1',
            'display_names' => ['eng' => 'Character One', 'fra' => 'Personnage Un'],
        ]);

        $char2 = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'char2',
            'display_names' => ['eng' => 'Character Two'],
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $char1->id,
            'iso_code' => 'eng',
            'blocks' => 10,
            'words' => 100,
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $char1->id,
            'iso_code' => 'fra',
            'blocks' => 12,
            'words' => 120,
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $char2->id,
            'iso_code' => 'eng',
            'blocks' => 5,
            'words' => 50,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/character-stats");

        // Should not return 500
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'characters',
                'languages',
                'wordCounts',
                'languageTotals',
            ],
        ]);
    });

    test('file stats endpoint does not return 500 error', function () {
        // This test ensures the endpoint doesn't crash even with complex data
        $imageCategory = VersionFileCategory::create([
            'game_version_id' => $this->version->id,
            'category' => 'image',
            'total_count' => 100,
            'total_size' => 50000000,
        ]);

        $audioCategory = VersionFileCategory::create([
            'game_version_id' => $this->version->id,
            'category' => 'audio',
            'total_count' => 50,
            'total_size' => 25000000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $imageCategory->id,
            'extension' => 'webp',
            'count' => 100,
            'size' => 50000000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $audioCategory->id,
            'extension' => 'ogg',
            'count' => 50,
            'size' => 25000000,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/file-stats");

        // Should not return 500
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'version',
                'file_categories',
            ],
        ]);
    });

    test('character stats uses correct character display name method', function () {
        // Regression test: ensure we use getDisplayName('eng') not ->name
        $character = Character::create([
            'game_id' => $this->game->id,
            'character_id' => 'test_char',
            'display_names' => ['eng' => 'Proper Name', 'fra' => 'Nom Propre'],
        ]);

        VersionCharacterStats::create([
            'game_version_id' => $this->version->id,
            'character_id' => $character->id,
            'iso_code' => 'eng',
            'blocks' => 10,
            'words' => 100,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/character-stats");

        $data = $response->json('data');

        // Should return the English display name, not "Unknown"
        expect($data['characters'])->toContain('Proper Name');
        expect($data['characters'])->not->toContain('Unknown');
    });

    test('file stats uses correct relationship method', function () {
        // Regression test: ensure we use fileCategories() not fileStats()
        $category = VersionFileCategory::create([
            'game_version_id' => $this->version->id,
            'category' => 'image',
            'total_count' => 10,
            'total_size' => 1000000,
        ]);

        VersionFileType::create([
            'version_file_category_id' => $category->id,
            'extension' => 'webp',
            'count' => 10,
            'size' => 1000000,
        ]);

        $response = $this->getJson("/react-api/games/{$this->game->slug}/versions/{$this->version->id}/file-stats");

        // Should successfully load the relationship
        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data['file_categories'])->toHaveCount(1);
        expect($data['file_categories'][0]['category'])->toBe('image');
    });
});
