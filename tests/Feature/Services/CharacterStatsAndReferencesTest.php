<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use App\Services\CharacterStatsCalculationService;
use App\Services\CharacterVersionReferenceService;

function statsText(string $text): UniqueDialogueText
{
    return UniqueDialogueText::firstOrCreate([
        'text_hash' => md5($text),
    ], [
        'text_content' => $text,
    ]);
}

function statsLine(GameVersion $version, ?Character $character, string $text, string $isoCode = 'eng', int $line = 1): DialogueLine
{
    return DialogueLine::create([
        'game_version_id' => $version->id,
        'character_id' => $character?->id,
        'iso_code' => $isoCode,
        'file_path' => 'script.rpy',
        'line_number' => $line,
        'text_id' => statsText($text)->id,
    ]);
}

it('classifies version data levels and safety gates', function () {
    $version = GameVersion::factory()->create();
    $service = new CharacterStatsCalculationService;

    expect($service->getVersionDataLevel($version->id))->toBe(CharacterStatsCalculationService::DATA_LEVEL_NONE)
        ->and($service->isVersionSafeToUpdate($version->id))->toBeFalse()
        ->and($service->canCreateCharacterStats($version->id))->toBeFalse()
        ->and($service->getDataLevelDescription('unexpected'))->toBe('Unknown data level');

    VersionLanguageStats::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'blocks' => 1,
        'words' => 2,
    ]);

    expect($service->getVersionDataLevel($version->id))->toBe(CharacterStatsCalculationService::DATA_LEVEL_LANGUAGE_ONLY);

    $character = Character::factory()->for($version->game)->create();
    VersionCharacterStats::create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'blocks' => 1,
        'words' => 2,
    ]);

    expect($service->getVersionDataLevel($version->id))->toBe(CharacterStatsCalculationService::DATA_LEVEL_CHARACTER_STATS);

    statsLine($version, $character, 'one two three');

    expect($service->getVersionDataLevel($version->id))->toBe(CharacterStatsCalculationService::DATA_LEVEL_FULL_DETAIL)
        ->and($service->isVersionSafeToUpdate($version->id))->toBeTrue()
        ->and($service->canCreateCharacterStats($version->id))->toBeTrue();
});

it('calculates saves filters and updates character stats from dialogue', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $unsafeVersion = GameVersion::factory()->for($game)->create();
    $alice = Character::factory()->for($game)->create(['character_id' => 'alice']);
    $bob = Character::factory()->for($game)->create(['character_id' => 'bob']);
    $alt = Character::factory()->for($game)->create(['character_id' => 'alt']);
    $service = new CharacterStatsCalculationService;

    statsLine($version, $alice, 'hello there', 'eng', 1);
    statsLine($version, $alice, '  extra   spaces  count ', 'eng', 2);
    statsLine($version, $bob, 'bonjour ici', 'fra', 3);
    statsLine($version, $alt, 'image alt text', 'eng', 4);
    statsLine($version, null, 'missing speaker', 'eng', 5);

    expect($service->calculateStatsForCharacter($version->id, $alice->id, 'eng'))->toBe([
        'blocks' => 2,
        'words' => 5,
    ]);

    expect($service->calculateAndSaveStatsForVersionSafe($unsafeVersion->id))->toBe(0);

    $created = $service->calculateAndSaveStatsForVersionSafe($version->id);

    expect($created)->toBe(3)
        ->and(VersionCharacterStats::where('game_version_id', $version->id)->count())->toBe(3)
        ->and(VersionLanguageStats::where('game_version_id', $version->id)->where('iso_code', 'eng')->value('words'))->toBe(5)
        ->and(VersionLanguageStats::where('game_version_id', $version->id)->where('iso_code', 'fra')->value('words'))->toBe(2);

    $aliceStats = VersionCharacterStats::where('game_version_id', $version->id)
        ->where('character_id', $alice->id)
        ->where('iso_code', 'eng')
        ->firstOrFail();
    $unsafeStats = VersionCharacterStats::create([
        'game_version_id' => $unsafeVersion->id,
        'character_id' => $bob->id,
        'iso_code' => 'eng',
        'blocks' => 99,
        'words' => 99,
    ]);
    $calculated = [
        ['stat' => $aliceStats, 'calculated' => ['blocks' => 7, 'words' => 11]],
        ['stat' => $unsafeStats, 'calculated' => ['blocks' => 1, 'words' => 1]],
    ];

    expect($service->filterSafeStatsToUpdate(collect([$aliceStats, $unsafeStats]))->pluck('id')->all())->toBe([$aliceStats->id])
        ->and($service->updateCharacterStatsSafe($calculated, true))->toBe(1)
        ->and($aliceStats->refresh()->blocks)->toBe(2)
        ->and($service->updateCharacterStatsSafe($calculated))->toBe(1)
        ->and($aliceStats->refresh()->blocks)->toBe(7)
        ->and($unsafeStats->refresh()->blocks)->toBe(99)
        ->and($service->updateCharacterStats($calculated, true))->toBe(2)
        ->and($unsafeStats->refresh()->blocks)->toBe(99)
        ->and($service->updateCharacterStats($calculated))->toBe(2)
        ->and($unsafeStats->refresh()->blocks)->toBe(1);
});

it('finds stats with issues by character type and zero counts', function () {
    $game = Game::factory()->create();
    $otherGame = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $otherVersion = GameVersion::factory()->for($otherGame)->create();
    $narrator = Character::factory()->for($game)->create(['character_id' => 'narrator']);
    $zero = Character::factory()->for($game)->create(['character_id' => 'alice']);
    $normal = Character::factory()->for($game)->create(['character_id' => 'bob']);
    $other = Character::factory()->for($otherGame)->create(['character_id' => 'centered']);

    $issueA = VersionCharacterStats::factory()->for($version, 'gameVersion')->for($narrator)->create(['blocks' => 2, 'words' => 2]);
    $issueB = VersionCharacterStats::factory()->for($version, 'gameVersion')->for($zero)->create(['blocks' => 0, 'words' => 2]);
    VersionCharacterStats::factory()->for($version, 'gameVersion')->for($normal)->create(['blocks' => 2, 'words' => 2]);
    VersionCharacterStats::factory()->for($otherVersion, 'gameVersion')->for($other)->create(['blocks' => 2, 'words' => 2]);

    $ids = (new CharacterStatsCalculationService)->getStatsWithIssues($game->id)->pluck('id')->all();

    expect($ids)->toContain($issueA->id, $issueB->id)
        ->and($ids)->toHaveCount(2);
});

it('repairs character version references, creates missing stats, and deletes orphans', function () {
    $game = Game::factory()->create();
    $firstVersion = GameVersion::factory()->for($game)->create(['published_at' => now()->subDays(2)]);
    $lastVersion = GameVersion::factory()->for($game)->create(['published_at' => now()]);
    $character = Character::factory()->for($game)->create([
        'character_id' => 'alice',
        'first_seen_in_version_id' => null,
        'last_seen_in_version_id' => null,
    ]);
    $orphan = Character::factory()->for($game)->create(['character_id' => 'unused']);
    $otherGame = Game::factory()->create();
    $otherOrphan = Character::factory()->for($otherGame)->create(['character_id' => 'other_unused']);

    VersionCharacterStats::factory()->for($firstVersion, 'gameVersion')->for($character)->create();
    statsLine($lastVersion, $character, 'new words here', 'eng', 10);
    $statsService = Mockery::mock(CharacterStatsCalculationService::class);
    $statsService->shouldReceive('isVersionSafeToUpdate')->andReturnTrue();
    $statsService->shouldReceive('calculateAndSaveStatsForVersionSafe')->once()->with($lastVersion->id)->andReturn(1);

    $result = (new CharacterVersionReferenceService($statsService))->fixVersionReferences($game->id);

    expect($result)->toBe([
        'characters_processed' => 2,
        'characters_updated' => 1,
        'stats_entries_created' => 1,
        'characters_deleted' => 1,
    ])
        ->and($character->refresh()->first_seen_in_version_id)->toBe($firstVersion->id)
        ->and($character->last_seen_in_version_id)->toBe($lastVersion->id)
        ->and(Character::find($orphan->id))->toBeNull()
        ->and(Character::find($otherOrphan->id))->not->toBeNull();
});

it('dry runs character version repairs without mutating characters', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $character = Character::factory()->for($game)->create();
    $orphan = Character::factory()->for($game)->create();
    statsLine($version, $character, 'dry run line', 'eng', 1);
    $statsService = Mockery::mock(CharacterStatsCalculationService::class);
    $statsService->shouldReceive('isVersionSafeToUpdate')->once()->with($version->id)->andReturnFalse();

    $result = (new CharacterVersionReferenceService($statsService))->fixVersionReferences($game->id, true);

    expect($result['characters_processed'])->toBe(2)
        ->and($result['characters_updated'])->toBe(1)
        ->and($result['stats_entries_created'])->toBe(0)
        ->and($result['characters_deleted'])->toBe(1)
        ->and($character->refresh()->first_seen_in_version_id)->toBeNull()
        ->and(Character::find($orphan->id))->not->toBeNull();
});
