<?php

declare(strict_types=1);

use App\Models\Character;
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

test('countUniqueCharactersInLanguage excludes narrator and menu_choice', function () {
    $narrator = Character::factory()->for($this->game)->create([
        'character_id' => 'narrator',
    ]);

    $menuChoice = Character::factory()->for($this->game)->create([
        'character_id' => 'menu_choice',
    ]);

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
