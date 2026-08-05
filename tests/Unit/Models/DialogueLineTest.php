<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->game = Game::factory()->create();
    $this->version = GameVersion::factory()->for($this->game)->create();
    $this->character = Character::factory()->for($this->game)->create();
    $this->uniqueText = UniqueDialogueText::factory()->create([
        'text_content' => 'Hello, this is a test dialogue line.',
        'text_hash' => md5('Hello, this is a test dialogue line.'),
    ]);

    $this->dialogue = DialogueLine::factory()
        ->for($this->version, 'gameVersion')
        ->for($this->character)
        ->create([
            'text_id' => $this->uniqueText->id,
            'iso_code' => 'eng',
            'file_path' => 'chapter1/intro.rpy',
            'line_number' => 42,
            'context' => 'main_story',
        ]);
});

test('text content accessor returns content from related text', function () {
    expect($this->dialogue->text_content)->toBe('Hello, this is a test dialogue line.');
});

test('text content accessor returns null when no text relation', function () {
    $dialogue = new DialogueLine;

    expect($dialogue->text_content)->toBeNull();
});

test('text content mutator creates unique dialogue text', function () {
    $dialogue = new DialogueLine([
        'game_version_id' => $this->version->id,
        'character_id' => $this->character->id,
        'iso_code' => 'eng',
    ]);

    $textContent = 'This is a new dialogue line for testing.';
    $dialogue->text_content = $textContent;

    expect($dialogue->text_id)->not()->toBeNull();

    // Verify the UniqueDialogueText was created
    $uniqueText = UniqueDialogueText::find($dialogue->text_id);
    expect($uniqueText->text_content)->toBe($textContent)
        ->and($uniqueText->text_hash)->toBe(md5($textContent));
});

test('text content mutator reuses existing unique dialogue text', function () {
    $existingText = UniqueDialogueText::factory()->create([
        'text_content' => 'Reused dialogue text',
        'text_hash' => md5('Reused dialogue text'),
    ]);

    $dialogue = new DialogueLine([
        'game_version_id' => $this->version->id,
        'character_id' => $this->character->id,
        'iso_code' => 'eng',
    ]);

    $dialogue->text_content = 'Reused dialogue text';

    expect($dialogue->text_id)->toBe($existingText->id);
});

test('withTextContent scope includes text content in query', function () {
    $results = DialogueLine::withTextContent()
        ->where('version_dialogue_lines.id', $this->dialogue->id)
        ->first();

    expect($results->text_content)->toBe('Hello, this is a test dialogue line.');
});

test('getLanguageConfig returns correct language configurations', function () {
    $ref = new ReflectionClass(DialogueLine::class);
    $method = $ref->getMethod('getLanguageConfig');

    $instance = $ref->newInstanceWithoutConstructor();

    expect($method->invoke($instance, 'jpn'))->toBe('japanese')
        ->and($method->invoke($instance, 'spa'))->toBe('spanish')
        ->and($method->invoke($instance, 'fra'))->toBe('french')
        ->and($method->invoke($instance, 'deu'))->toBe('german')
        ->and($method->invoke($instance, 'eng'))->toBe('english')
        ->and($method->invoke($instance, null))->toBe('english')
        ->and($method->invoke($instance, 'unknown'))->toBe('english');
});

test('getTsvectorColumnForLanguage returns correct column names', function () {
    $ref = new ReflectionClass(DialogueLine::class);
    $method = $ref->getMethod('getTsvectorColumnForLanguage');

    $instance = $ref->newInstanceWithoutConstructor();

    expect($method->invoke($instance, null))->toBe('search_vector')
        ->and($method->invoke($instance, 'eng'))->toBe('search_vector');

    // These would return specific columns if they exist in the database
    // For now, they fall back to the default
    expect($method->invoke($instance, 'jpn'))->toBe('search_vector');
});

test('search scope constructs proper query', function () {
    // This test verifies the search scope builds the query correctly
    // We can't easily test the actual PostgreSQL functionality in unit tests
    $query = DialogueLine::search('test search', 'eng');

    // Verify the query has the proper joins and where clauses
    $sql = $query->toSql();
    expect($sql)->toContain('join')
        ->and($sql)->toContain('unique_dialogue_texts')
        ->and($sql)->toContain('plainto_tsquery');
});
