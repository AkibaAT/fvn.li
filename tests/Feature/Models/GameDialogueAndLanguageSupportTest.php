<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameDialogueText;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\UniqueDialogueText;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;

function dialogueLanguage(string $id, string $name, ?string $part1 = null, ?string $flag = null): Language
{
    DB::table('iso_639_3_languages')->updateOrInsert([
        'id' => $id,
    ], [
        'part2b' => $id,
        'part2t' => $id,
        'part1' => $part1,
        'scope' => 'I',
        'type' => 'L',
        'ref_name' => $name,
        'flag_code' => $flag ?? strtolower(substr($id, 0, 2)),
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    return Language::findOrFail($id);
}

function dialogueTextRecord(string $text): UniqueDialogueText
{
    return UniqueDialogueText::firstOrCreate([
        'text_hash' => md5($text),
    ], [
        'text_content' => $text,
    ]);
}

function dialogueModelLine(GameVersion $version, ?Character $character, string $text, string $isoCode = 'eng', int $line = 1): DialogueLine
{
    return DialogueLine::create([
        'game_version_id' => $version->id,
        'character_id' => $character?->id,
        'iso_code' => $isoCode,
        'file_path' => 'script.rpy',
        'line_number' => $line,
        'text_id' => dialogueTextRecord($text)->id,
    ]);
}

it('aggregates dialogue texts per game for search indexing', function () {
    $game = Game::factory()->create(['name' => 'Searchable Game']);
    $versionA = GameVersion::factory()->for($game)->create();
    $versionB = GameVersion::factory()->for($game)->create();
    $otherVersion = GameVersion::factory()->create();
    $alice = Character::factory()->for($game)->create([
        'character_id' => 'alice',
        'display_names' => ['eng' => 'Alice'],
    ]);
    $bob = Character::factory()->for($game)->create([
        'character_id' => 'bob',
        'display_names' => [],
    ]);

    dialogueModelLine($versionA, $alice, 'Shared line', 'eng', 1);
    dialogueModelLine($versionB, $bob, 'Shared line', 'eng', 2);
    dialogueModelLine($versionB, null, 'Narration only', 'eng', 3);
    dialogueModelLine($otherVersion, null, 'Other game line', 'eng', 1);

    $records = GameDialogueText::getForGame($game->id)->keyBy('text_content');

    expect($records)->toHaveCount(2)
        ->and($records['Shared line']->game_name)->toBe('Searchable Game')
        ->and($records['Shared line']->version_ids)->toContain($versionA->id, $versionB->id)
        ->and($records['Shared line']->character_ids)->toContain($alice->id, $bob->id)
        ->and($records['Shared line']->character_names)->toContain('Alice', 'bob')
        ->and($records['Narration only']->character_ids)->toBe([])
        ->and($records['Narration only']->character_names)->toBe([]);

    $allTexts = GameDialogueText::getAllGameDialogueTexts();

    expect($allTexts->pluck('text_content')->all())->toContain('Shared line', 'Narration only', 'Other game line');

    $chunkedRecords = collect();
    $chunkedCount = GameDialogueText::chunkForGame($game->id, 1, function ($chunk) use ($chunkedRecords) {
        expect($chunk)->toHaveCount(1);
        $chunkedRecords->push(...$chunk);
    });

    expect($chunkedCount)->toBe(2)
        ->and($chunkedRecords->keyBy('text_content')['Shared line']->character_names)->toContain('Alice', 'bob');
});

it('exposes dialogue search payloads and searchability metadata', function () {
    $model = new GameDialogueText;
    $model->id = 123;
    $model->text_id = 1;
    $model->game_id = 2;
    $model->text_content = 'Index me';
    $model->language = 'eng';
    $model->game_name = 'Game';
    $model->version_ids = [3];
    $model->character_ids = [4];
    $model->character_names = ['Alice'];

    expect($model->toSearchableArray())->toBe([
        'id' => 123,
        'text_id' => 1,
        'game_id' => 2,
        'text_content' => 'Index me',
        'language' => 'eng',
        'game_name' => 'Game',
        'version_ids' => [3],
        'character_ids' => [4],
        'character_names' => ['Alice'],
    ])
        ->and($model->searchableAs())->toBe('game_dialogue_texts')
        ->and($model->shouldBeSearchable())->toBeTrue()
        ->and($model->getScoutKey())->toBe(123)
        ->and($model->getScoutKeyName())->toBe('id');

    $model->text_content = '   ';

    expect($model->shouldBeSearchable())->toBeFalse();
});

it('returns loaded and queried language support for the latest version', function () {
    dialogueLanguage('eng', 'English', 'en', 'gb');
    dialogueLanguage('fra', 'French', 'fr', 'fr');
    dialogueLanguage('qaa', 'Placeholder');
    $game = Game::factory()->create();
    $latest = GameVersion::factory()->for($game)->create();
    $latest->forceFill(['is_latest' => true])->save();
    VersionSupportedLanguage::create(['game_version_id' => $latest->id, 'iso_code' => 'eng', 'is_available' => true]);
    VersionSupportedLanguage::create(['game_version_id' => $latest->id, 'iso_code' => 'fra', 'is_available' => false]);
    VersionSupportedLanguage::create(['game_version_id' => $latest->id, 'iso_code' => 'qaa', 'is_available' => true]);

    $loadedGame = $game->fresh()->load('latestVersion.supportedLanguages.language');

    expect($loadedGame->getSupportedLanguages()->all())->toBe([
        ['iso_code' => 'eng', 'ref_name' => 'English', 'flag_code' => 'gb'],
    ])
        ->and($loadedGame->getAllSupportedLanguages()->all())->toBe([
            ['iso_code' => 'eng', 'ref_name' => 'English', 'flag_code' => 'gb', 'is_available' => true],
            ['iso_code' => 'fra', 'ref_name' => 'French', 'flag_code' => 'fr', 'is_available' => false],
        ])
        ->and($loadedGame->getAvailableLanguages()->pluck('iso_code')->all())->toBe(['eng', 'qaa'])
        ->and($loadedGame->isLanguageAvailable('eng'))->toBeTrue()
        ->and($loadedGame->isLanguageAvailable('fra'))->toBeFalse()
        ->and($loadedGame->isLanguageAvailable('deu'))->toBeFalse()
        ->and(Game::factory()->make()->getSupportedLanguages())->toHaveCount(0)
        ->and(Game::factory()->make()->getAllSupportedLanguages())->toHaveCount(0)
        ->and(Game::factory()->make()->getAvailableLanguages())->toHaveCount(0)
        ->and(Game::factory()->make()->isLanguageAvailable('eng'))->toBeFalse();
});

it('reports primary and english word counts and language labels', function () {
    dialogueLanguage('eng', 'English', 'en');
    dialogueLanguage('jpn', 'Japanese', 'ja');
    $game = Game::factory()->create(['source_language_id' => 'jpn']);
    $latest = GameVersion::factory()->for($game)->create();
    $latest->forceFill(['is_latest' => true])->save();
    VersionLanguageStats::create(['game_version_id' => $latest->id, 'iso_code' => 'eng', 'blocks' => 2, 'words' => 120]);
    VersionLanguageStats::create(['game_version_id' => $latest->id, 'iso_code' => 'jpn', 'blocks' => 3, 'words' => 340]);

    $loaded = $game->fresh()->load('latestVersion.languageStats', 'sourceLanguage');

    expect($loaded->getEnglishWordCount())->toBe(120)
        ->and($loaded->getPrimaryWordCount())->toBe(340)
        ->and($loaded->getPrimaryLanguageLabel())->toBe('JA')
        ->and($loaded->getLatestCharacterStats('eng'))->toHaveCount(0);

    $preloaded = Game::factory()->make(['english_word_count' => 777]);
    $fallback = Game::factory()->make(['source_language_id' => 'zzz']);
    $englishSource = $game->fresh(['latestVersion.languageStats']);
    $englishSource->source_language_id = 'eng';

    expect($preloaded->getEnglishWordCount())->toBe(777)
        ->and($fallback->getPrimaryLanguageLabel())->toBe('ZZ')
        ->and($englishSource->getPrimaryWordCount())->toBe(120)
        ->and(Game::factory()->make()->getEnglishWordCount())->toBeNull()
        ->and(Game::factory()->make(['source_language_id' => 'jpn'])->getPrimaryWordCount())->toBeNull();
});
