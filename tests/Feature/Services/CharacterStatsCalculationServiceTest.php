<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\CharacterStatsCalculationService;
use Illuminate\Support\Facades\DB;

uses()->group('character-stats');

beforeEach(function () {
    $this->service = app(CharacterStatsCalculationService::class);
});

it('recalculates language stats when character stats are updated', function () {
    $game = Game::factory()->create();

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

    $textId = DB::table('unique_dialogue_texts')->insertGetId([
        'text_hash' => md5('test text with multiple words'),
        'text_content' => 'test text with multiple words',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

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
