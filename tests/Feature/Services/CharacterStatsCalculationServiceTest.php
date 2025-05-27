<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\CharacterStatsCalculationService;
use Illuminate\Support\Facades\DB;

uses()->group('character-stats');

beforeEach(function () {
    $this->service = app(CharacterStatsCalculationService::class);
});

it('returns data level none when no data exists', function () {
    $game = Game::factory()->create();
    $versionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0.0',
        'published_at' => '2024-03-15 10:00:00',
        'created_at' => '2024-03-15 10:00:00',
        'updated_at' => '2024-03-15 10:00:00',
    ]);

    $dataLevel = $this->service->getVersionDataLevel($versionId);

    expect($dataLevel)->toBe(CharacterStatsCalculationService::DATA_LEVEL_NONE);
});

it('returns data level language only when only language stats exist', function () {
    $game = Game::factory()->create();
    $versionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0.0',
        'published_at' => '2024-03-15 10:00:00',
        'created_at' => '2024-03-15 10:00:00',
        'updated_at' => '2024-03-15 10:00:00',
    ]);

    // Add language stats only
    DB::table('version_language_stats')->insert([
        'game_version_id' => $versionId,
        'iso_code' => 'eng',
        'blocks' => 100,
        'words' => 500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dataLevel = $this->service->getVersionDataLevel($versionId);

    expect($dataLevel)->toBe(CharacterStatsCalculationService::DATA_LEVEL_LANGUAGE_ONLY);
});

it('returns data level character stats when character stats exist', function () {
    $game = Game::factory()->create();

    // Create character manually
    $characterId = DB::table('characters')->insertGetId([
        'game_id' => $game->id,
        'character_id' => 'test_character',
        'display_names' => json_encode(['eng' => 'Test Character']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $versionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0.0',
        'published_at' => '2024-03-15 10:00:00',
        'created_at' => '2024-03-15 10:00:00',
        'updated_at' => '2024-03-15 10:00:00',
    ]);

    // Add character stats
    DB::table('version_character_stats')->insert([
        'game_version_id' => $versionId,
        'character_id' => $characterId,
        'iso_code' => 'eng',
        'blocks' => 50,
        'words' => 250,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dataLevel = $this->service->getVersionDataLevel($versionId);

    expect($dataLevel)->toBe(CharacterStatsCalculationService::DATA_LEVEL_CHARACTER_STATS);
});

it('returns data level full detail when dialogue lines with text exist', function () {
    $game = Game::factory()->create();

    // Create character manually
    $characterId = DB::table('characters')->insertGetId([
        'game_id' => $game->id,
        'character_id' => 'test_character',
        'display_names' => json_encode(['eng' => 'Test Character']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $versionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0.0',
        'published_at' => '2024-03-15 10:00:00',
        'created_at' => '2024-03-15 10:00:00',
        'updated_at' => '2024-03-15 10:00:00',
    ]);

    // Create unique dialogue text
    $textId = DB::table('unique_dialogue_texts')->insertGetId([
        'text_hash' => md5('test text'),
        'text_content' => 'test text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Add dialogue lines with text references
    DB::table('version_dialogue_lines')->insert([
        'game_version_id' => $versionId,
        'character_id' => $characterId,
        'iso_code' => 'eng',
        'text_id' => $textId,
        'file_path' => 'test.rpy',
        'line_number' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dataLevel = $this->service->getVersionDataLevel($versionId);

    expect($dataLevel)->toBe(CharacterStatsCalculationService::DATA_LEVEL_FULL_DETAIL);
});

it('considers version not safe to update when insufficient data', function () {
    $game = Game::factory()->create();
    $versionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0.0',
        'published_at' => '2024-03-15 10:00:00',
        'created_at' => '2024-03-15 10:00:00',
        'updated_at' => '2024-03-15 10:00:00',
    ]);

    // Only add language stats (insufficient for character stats updates)
    DB::table('version_language_stats')->insert([
        'game_version_id' => $versionId,
        'iso_code' => 'eng',
        'blocks' => 100,
        'words' => 500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $isSafe = $this->service->isVersionSafeToUpdate($versionId);

    expect($isSafe)->toBeFalse();
});

it('considers version safe to update when full detail available', function () {
    $game = Game::factory()->create();

    // Create character manually
    $characterId = DB::table('characters')->insertGetId([
        'game_id' => $game->id,
        'character_id' => 'test_character',
        'display_names' => json_encode(['eng' => 'Test Character']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $versionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0.0',
        'published_at' => '2024-03-15 10:00:00',
        'created_at' => '2024-03-15 10:00:00',
        'updated_at' => '2024-03-15 10:00:00',
    ]);

    // Create unique dialogue text
    $textId = DB::table('unique_dialogue_texts')->insertGetId([
        'text_hash' => md5('test text'),
        'text_content' => 'test text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Add dialogue lines with text references (full detail)
    DB::table('version_dialogue_lines')->insert([
        'game_version_id' => $versionId,
        'character_id' => $characterId,
        'iso_code' => 'eng',
        'text_id' => $textId,
        'file_path' => 'test.rpy',
        'line_number' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $isSafe = $this->service->isVersionSafeToUpdate($versionId);

    expect($isSafe)->toBeTrue();
});

it('skips insufficient data when calculating and saving stats safely', function () {
    $game = Game::factory()->create();
    $versionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0.0',
        'published_at' => '2024-03-15 10:00:00',
        'created_at' => '2024-03-15 10:00:00',
        'updated_at' => '2024-03-15 10:00:00',
    ]);

    // Only add language stats (insufficient for character stats calculation)
    DB::table('version_language_stats')->insert([
        'game_version_id' => $versionId,
        'iso_code' => 'eng',
        'blocks' => 100,
        'words' => 500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = $this->service->calculateAndSaveStatsForVersionSafe($versionId);

    expect($result)->toBe(0);
});

it('recalculates language stats when character stats are updated', function () {
    $game = Game::factory()->create();

    // Create character manually
    $characterId = DB::table('characters')->insertGetId([
        'game_id' => $game->id,
        'character_id' => 'test_character',
        'display_names' => json_encode(['eng' => 'Test Character']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $versionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0.0',
        'published_at' => '2024-03-15 10:00:00',
        'created_at' => '2024-03-15 10:00:00',
        'updated_at' => '2024-03-15 10:00:00',
    ]);

    // Create unique dialogue text
    $textId = DB::table('unique_dialogue_texts')->insertGetId([
        'text_hash' => md5('test text with multiple words'),
        'text_content' => 'test text with multiple words',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Add dialogue lines with text references (full detail)
    DB::table('version_dialogue_lines')->insert([
        'game_version_id' => $versionId,
        'character_id' => $characterId,
        'iso_code' => 'eng',
        'text_id' => $textId,
        'file_path' => 'test.rpy',
        'line_number' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Calculate and save stats
    $result = $this->service->calculateAndSaveStatsForVersion($versionId);

    // Verify character stats were created
    expect($result)->toBeGreaterThan(0);

    $characterStats = DB::table('version_character_stats')
        ->where('game_version_id', $versionId)
        ->where('character_id', $characterId)
        ->where('iso_code', 'eng')
        ->first();

    expect($characterStats)->not->toBeNull()
        ->and($characterStats->blocks)->toBe(1)
        ->and($characterStats->words)->toBe(5);
    // "test text with multiple words"

    // Verify language stats were also created/updated
    $languageStats = DB::table('version_language_stats')
        ->where('game_version_id', $versionId)
        ->where('iso_code', 'eng')
        ->first();

    expect($languageStats)->not->toBeNull()
        ->and($languageStats->blocks)->toBe(1)
        ->and($languageStats->words)->toBe(5);
});
