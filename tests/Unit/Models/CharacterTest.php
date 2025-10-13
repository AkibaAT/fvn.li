<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionCharacterStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->game = Game::factory()->create();
    $this->version = GameVersion::factory()->for($this->game)->create();
    $this->character = Character::factory()->for($this->game)->create([
        'character_id' => 'main_character',
        'display_names' => [
            'eng' => 'Main Character',
            'jpn' => 'メインキャラクター',
        ],
        'display_name_corrections' => [
            'eng' => 'MC',
        ],
    ]);
});

test('character has correct fillable attributes', function () {
    $character = new Character;

    expect($character->getFillable())->toContain(
        'game_id',
        'character_id',
        'display_names',
        'first_seen_in_version_id',
        'last_seen_in_version_id',
        'gender',
        'species',
        'age'
    );
});

test('character has correct casted attributes', function () {
    expect($this->character->display_names)
        ->toBeArray()
        ->and($this->character->display_names['eng'])->toBe('Main Character');
});

test('getDisplayName returns corrected name when available', function () {
    $character = new Character([
        'character_id' => 'hero',
        'display_names' => ['eng' => 'Hero'],
    ]);

    // Simulate correction without touching DB
    $character->display_name_corrections = ['eng' => 'The Hero'];

    expect($character->getDisplayName('eng'))->toBe('The Hero');
});

test('getDisplayName falls back to original when no correction', function () {
    $character = new Character([
        'character_id' => 'hero',
        'display_names' => [
            'eng' => 'Hero',
            'jpn' => 'ヒーロー',
        ],
    ]);

    expect($character->getDisplayName('eng'))->toBe('Hero')
        ->and($character->getDisplayName('jpn'))->toBe('ヒーロー')
        ->and($character->getDisplayName('fra'))->toBeNull();
});

test('getDisplayName uses corrected name from database', function () {
    expect($this->character->getDisplayName('eng'))->toBe('MC');
});

test('getDisplayName returns original display name when no correction', function () {
    expect($this->character->getDisplayName('jpn'))->toBe('メインキャラクター');
});

test('getDisplayName returns null when language not available', function () {
    expect($this->character->getDisplayName('fra'))->toBeNull();
});

test('character has game relationship', function () {
    expect($this->character->game->id)->toBe($this->game->id);
});

test('character has first seen version relationship', function () {
    $character = Character::factory()
        ->for($this->game)
        ->create(['first_seen_in_version_id' => $this->version->id]);

    expect($character->firstSeenVersion->id)->toBe($this->version->id);
});

test('character has last seen version relationship', function () {
    $character = Character::factory()
        ->for($this->game)
        ->create(['last_seen_in_version_id' => $this->version->id]);

    expect($character->lastSeenVersion->id)->toBe($this->version->id);
});

test('character has version stats relationship', function () {
    VersionCharacterStats::factory()
        ->for($this->character)
        ->for($this->version, 'gameVersion')
        ->create();

    expect($this->character->versionStats)->toHaveCount(1);
});

test('character has dialogue lines relationship', function () {
    DialogueLine::factory()
        ->for($this->character)
        ->for($this->version, 'gameVersion')
        ->create();

    expect($this->character->dialogueLines)->toHaveCount(1);
});

test('countUniqueCharactersInLanguage excludes narrator and menu_choice', function () {
    // Create narrator character (should be excluded)
    $narrator = Character::factory()->for($this->game)->create([
        'character_id' => 'narrator',
    ]);

    // Create menu choice character (should be excluded)
    $menuChoice = Character::factory()->for($this->game)->create([
        'character_id' => 'menu_choice',
    ]);

    // Create version stats for all characters
    VersionCharacterStats::factory()
        ->for($this->character)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'eng']);

    VersionCharacterStats::factory()
        ->for($narrator)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'eng']);

    VersionCharacterStats::factory()
        ->for($menuChoice)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'eng']);

    $count = Character::countUniqueCharactersInLanguage($this->game->id, 'eng');

    expect($count)->toBe(1); // Only main_character should be counted
});

test('countUniqueCharactersInLanguage filters by language code', function () {
    $englishStats = VersionCharacterStats::factory()
        ->for($this->character)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'eng']);

    $japaneseStats = VersionCharacterStats::factory()
        ->for($this->character)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'jpn']);

    $englishCount = Character::countUniqueCharactersInLanguage($this->game->id, 'eng');
    $japaneseCount = Character::countUniqueCharactersInLanguage($this->game->id, 'jpn');

    expect($englishCount)->toBe(1)
        ->and($japaneseCount)->toBe(1);
});

test('countUniqueCharactersInLanguage filters by version', function () {
    $version2 = GameVersion::factory()->for($this->game)->create();

    // Character stats only in version 1
    VersionCharacterStats::factory()
        ->for($this->character)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'eng']);

    $count1 = Character::countUniqueCharactersInLanguage($this->game->id, 'eng', $this->version->id);
    $count2 = Character::countUniqueCharactersInLanguage($this->game->id, 'eng', $version2->id);

    expect($count1)->toBe(1)
        ->and($count2)->toBe(0);
});

test('countUniqueCharactersInLanguage counts unique display names', function () {
    // Create two characters with same display name (but first character has correction)
    $character2 = Character::factory()->for($this->game)->create([
        'character_id' => 'character_2',
        'display_names' => [
            'eng' => 'MC', // Same as corrected name of first character
        ],
    ]);

    VersionCharacterStats::factory()
        ->for($this->character)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'eng']);

    VersionCharacterStats::factory()
        ->for($character2)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'eng']);

    $count = Character::countUniqueCharactersInLanguage($this->game->id, 'eng');

    expect($count)->toBe(1); // Should count unique display names (both resolve to 'MC')
});

test('countUniqueCharactersInLanguage uses fallback language', function () {
    // Character with no English display name
    $character = Character::factory()->for($this->game)->create([
        'character_id' => 'no_eng_character',
        'display_names' => [
            'jpn' => 'Japanese Name',
        ],
    ]);

    VersionCharacterStats::factory()
        ->for($character)
        ->for($this->version, 'gameVersion')
        ->create(['iso_code' => 'eng']);

    $count = Character::countUniqueCharactersInLanguage($this->game->id, 'eng');

    // Should use character_id as fallback when display name not available
    expect($count)->toBe(1);
});

test('character can have demographic attributes', function () {
    $character = Character::factory()->create([
        'gender' => 'female',
        'species' => 'human',
        'age' => 'mid-20s',
    ]);

    expect($character->gender)->toBe('female')
        ->and($character->species)->toBe('human')
        ->and($character->age)->toBe('mid-20s');
});

test('character demographic attributes can be null', function () {
    $character = Character::factory()->create([
        'gender' => null,
        'species' => null,
        'age' => null,
    ]);

    expect($character->gender)->toBeNull()
        ->and($character->species)->toBeNull()
        ->and($character->age)->toBeNull();
});

test('character factory demographic methods work correctly', function () {
    $femaleCharacter = Character::factory()->female()->create();
    expect($femaleCharacter->gender)->toBe('female');

    $maleCharacter = Character::factory()->male()->create();
    expect($maleCharacter->gender)->toBe('male');

    $humanCharacter = Character::factory()->human()->create();
    expect($humanCharacter->species)->toBe('human');

    $customCharacter = Character::factory()
        ->withGender('non-binary')
        ->withSpecies('elf')
        ->withAge('centuries old')
        ->create();

    expect($customCharacter->gender)->toBe('non-binary')
        ->and($customCharacter->species)->toBe('elf')
        ->and($customCharacter->age)->toBe('centuries old');
});
