<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use Illuminate\Support\Facades\DB;

function wordFrequencyText(string $text): UniqueDialogueText
{
    return UniqueDialogueText::firstOrCreate([
        'text_hash' => md5($text),
    ], [
        'text_content' => $text,
    ]);
}

function wordFrequencyLine(GameVersion $version, Character $character, string $text, string $isoCode = 'eng', int $line = 1): void
{
    DialogueLine::create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => $isoCode,
        'file_path' => 'script.rpy',
        'line_number' => $line,
        'text_id' => wordFrequencyText($text)->id,
    ]);
}

it('warns when no dialogue combinations match the requested criteria', function () {
    $this->artisan('dialogue:calculate-word-frequencies', ['--version-id' => 999999])
        ->expectsOutput('No dialogue data found for the specified criteria.')
        ->assertExitCode(0);
});

it('calculates word and phrase frequencies and skips cached combinations', function () {
    DB::table('iso_639_3_languages')->updateOrInsert([
        'id' => 'eng',
    ], [
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
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $otherVersion = GameVersion::factory()->for($game)->create();
    $character = Character::factory()->for($game)->create();
    wordFrequencyLine($version, $character, 'Crystal forest crystal melody', 'eng', 1);
    wordFrequencyLine($version, $character, 'Crystal melody returns', 'eng', 2);
    wordFrequencyLine($version, $character, 'Should be ignored', 'qaa', 3);
    wordFrequencyLine($otherVersion, $character, 'Other version crystal', 'eng', 1);

    DB::table('version_word_frequencies')->insert([
        'game_version_id' => $otherVersion->id,
        'iso_code' => 'eng',
        'word_data' => json_encode([['text' => 'old', 'value' => 1]]),
        'calculated_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('dialogue:calculate-word-frequencies')
        ->expectsOutput('Found 2 version+language combinations to process.')
        ->expectsOutput('Processed: 1')
        ->expectsOutput('Skipped (already cached): 1')
        ->expectsOutput('Use --force to recalculate existing entries.')
        ->assertExitCode(0);

    $wordData = json_decode(DB::table('version_word_frequencies')
        ->where('game_version_id', $version->id)
        ->where('iso_code', 'eng')
        ->value('word_data'), true);

    expect($wordData)->toContain(['text' => 'crystal', 'value' => 3])
        ->and($wordData)->toContain(['text' => 'crystal melody', 'value' => 2])
        ->and(DB::table('version_word_frequencies')->where('iso_code', 'qaa')->exists())->toBeFalse();

    $this->artisan('dialogue:calculate-word-frequencies', [
        '--version-id' => $otherVersion->id,
        '--language' => 'eng',
        '--force' => true,
    ])
        ->expectsOutput('Found 1 version+language combinations to process.')
        ->expectsOutput('Processed: 1')
        ->assertExitCode(0);

    $updatedData = json_decode(DB::table('version_word_frequencies')
        ->where('game_version_id', $otherVersion->id)
        ->where('iso_code', 'eng')
        ->value('word_data'), true);

    expect($updatedData)->not->toBe([['text' => 'old', 'value' => 1]]);
});
