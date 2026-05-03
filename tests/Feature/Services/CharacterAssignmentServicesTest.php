<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use App\Services\CharacterNullAssignmentService;
use App\Services\CharacterSpecialAssignmentService;
use App\Services\EssentialCharacterService;

function assignmentText(string $text = 'Line'): UniqueDialogueText
{
    return UniqueDialogueText::firstOrCreate([
        'text_hash' => md5($text),
    ], [
        'text_content' => $text,
    ]);
}

function assignmentLine(GameVersion $version, ?Character $character, int $line, string $file = 'script.rpy'): DialogueLine
{
    return DialogueLine::create([
        'game_version_id' => $version->id,
        'character_id' => $character?->id,
        'iso_code' => 'eng',
        'file_path' => $file,
        'line_number' => $line,
        'text_id' => assignmentText("Line {$line} {$file}")->id,
    ]);
}

it('reports no null character assignments when dialogue is already assigned', function () {
    $service = new CharacterNullAssignmentService(new EssentialCharacterService());

    expect($service->fixNullCharacterAssignments())->toBe([
        'lines_updated' => 0,
        'narrator_characters_created' => 0,
        'games_processed' => 0,
    ]);
});

it('dry-runs and fixes null character assignments per game', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $line = assignmentLine($version, null, 1);
    $otherGame = Game::factory()->create();
    $otherVersion = GameVersion::factory()->for($otherGame)->create();
    assignmentLine($otherVersion, null, 1);
    $service = new CharacterNullAssignmentService(new EssentialCharacterService());

    expect($service->fixNullCharacterAssignments($game->id, true))->toBe([
        'lines_updated' => 1,
        'narrator_characters_created' => 1,
        'games_processed' => 1,
    ]);
    expect($line->refresh()->character_id)->toBeNull();

    $result = $service->fixNullCharacterAssignments($game->id);

    expect($result['lines_updated'])->toBe(1)
        ->and($result['narrator_characters_created'])->toBe(1)
        ->and($result['games_processed'])->toBe(1)
        ->and($line->refresh()->character->character_id)->toBe('narrator');
});

it('reassigns extend lines to the previous character and falls back to narrator', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $speaker = Character::create([
        'game_id' => $game->id,
        'character_id' => 'alice',
        'display_names' => ['eng' => 'Alice'],
    ]);
    $extend = Character::create([
        'game_id' => $game->id,
        'character_id' => 'extend',
        'display_names' => ['eng' => 'extend'],
    ]);
    assignmentLine($version, $speaker, 10);
    $extendLine = assignmentLine($version, $extend, 11);
    $fallbackLine = assignmentLine($version, $extend, 1, 'opening.rpy');

    $result = (new CharacterSpecialAssignmentService(new EssentialCharacterService()))
        ->fixSpecialCharacterAssignments($game->id, 'extend');

    expect($result['lines_reassigned'])->toBe(2)
        ->and($result['versions_processed'])->toBe(1)
        ->and($extendLine->refresh()->character_id)->toBe($speaker->id)
        ->and($fallbackLine->refresh()->character->character_id)->toBe('narrator');
});

it('dry-runs and applies narrator special character reassignments', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $centered = Character::create([
        'game_id' => $game->id,
        'character_id' => 'centered',
        'display_names' => ['eng' => 'centered'],
    ]);
    $line = assignmentLine($version, $centered, 5);
    $service = new CharacterSpecialAssignmentService(new EssentialCharacterService());

    $dryRun = $service->fixSpecialCharacterAssignments($game->id, 'centered', true);

    expect($dryRun['lines_reassigned'])->toBe(1)
        ->and($line->refresh()->character_id)->toBe($centered->id);

    $result = $service->fixSpecialCharacterAssignments($game->id, 'centered');

    expect($result['lines_reassigned'])->toBe(1)
        ->and($line->refresh()->character->character_id)->toBe('narrator');
});
