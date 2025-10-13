<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\VersionCharacterStats;
use App\Models\VersionFileCategory;
use App\Models\VersionFileType;
use Illuminate\Support\Facades\DB;

uses()->group('version-stats');

beforeEach(function () {
    // Ensure English and French languages exist using raw SQL to avoid transaction issues
    DB::statement("
        INSERT INTO iso_639_3_languages (id, part2b, part2t, part1, scope, type, ref_name, flag_code, created_at, updated_at)
        VALUES ('eng', 'eng', 'eng', 'en', 'I', 'L', 'English', 'gb', NOW(), NOW())
        ON CONFLICT (id) DO NOTHING
    ");

    DB::statement("
        INSERT INTO iso_639_3_languages (id, part2b, part2t, part1, scope, type, ref_name, flag_code, created_at, updated_at)
        VALUES ('fra', 'fre', 'fra', 'fr', 'I', 'L', 'French', 'fr', NOW(), NOW())
        ON CONFLICT (id) DO NOTHING
    ");

    // Create a test game
    $this->game = Game::factory()->create([
        'name' => 'Test Visual Novel',
        'slug' => 'test-visual-novel',
    ]);

    // Create a test version
    $this->version = GameVersion::create([
        'game_id' => $this->game->id,
        'version' => '1.0.0',
        'published_at' => now(),
        'is_latest' => true,
    ]);
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

describe('regression prevention', function () {
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


