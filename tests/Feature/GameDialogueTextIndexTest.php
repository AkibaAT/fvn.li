<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameDialogueText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('indexes only the current dialogue version and keeps first seen metadata', function () {
    Queue::fake();

    $game = Game::factory()->create();
    $now = now();

    $oldVersionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '0.1',
        'published_at' => '2024-01-01 00:00:00',
        'is_latest' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $latestVersionId = DB::table('game_versions')->insertGetId([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => '2024-02-01 00:00:00',
        'is_latest' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $characterId = DB::table('characters')->insertGetId([
        'game_id' => $game->id,
        'character_id' => 'alice',
        'display_names' => json_encode(['eng' => 'Alice']),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $sharedTextId = DB::table('unique_dialogue_texts')->insertGetId([
        'text_hash' => md5('This line survived.'),
        'text_content' => 'This line survived.',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $oldOnlyTextId = DB::table('unique_dialogue_texts')->insertGetId([
        'text_hash' => md5('This old line was removed.'),
        'text_content' => 'This old line was removed.',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $newTextId = DB::table('unique_dialogue_texts')->insertGetId([
        'text_hash' => md5('This line is new.'),
        'text_content' => 'This line is new.',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('version_dialogue_lines')->insert([
        [
            'game_version_id' => $oldVersionId,
            'character_id' => $characterId,
            'iso_code' => 'eng',
            'file_path' => 'script.rpy',
            'line_number' => 10,
            'text_id' => $sharedTextId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'game_version_id' => $oldVersionId,
            'character_id' => $characterId,
            'iso_code' => 'eng',
            'file_path' => 'script.rpy',
            'line_number' => 11,
            'text_id' => $oldOnlyTextId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'game_version_id' => $latestVersionId,
            'character_id' => $characterId,
            'iso_code' => 'eng',
            'file_path' => 'script.rpy',
            'line_number' => 20,
            'text_id' => $sharedTextId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'game_version_id' => $latestVersionId,
            'character_id' => $characterId,
            'iso_code' => 'eng',
            'file_path' => 'script.rpy',
            'line_number' => 21,
            'text_id' => $newTextId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $documents = GameDialogueText::getForGame($game->id)
        ->mapWithKeys(fn (GameDialogueText $text) => [$text->text_content => $text->toSearchableArray()]);

    expect($documents->keys()->all())->toEqualCanonicalizing([
        'This line survived.',
        'This line is new.',
    ]);

    expect($documents['This line survived.']['version_ids'])->toBe([$latestVersionId])
        ->and($documents['This line survived.']['first_seen_version_id'])->toBe($oldVersionId)
        ->and($documents['This line survived.']['first_seen_version'])->toBe('0.1')
        ->and($documents['This line is new.']['first_seen_version_id'])->toBe($latestVersionId)
        ->and($documents)->not->toHaveKey('This old line was removed.');
});
