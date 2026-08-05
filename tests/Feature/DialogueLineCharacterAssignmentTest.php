<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameStatsService;

test('it handles mixed character types correctly', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->create(['game_id' => $game->id]);

    // Mock dialogue data with mixed character types
    $dialogueData = [
        'languages' => [
            'eng' => [
                'blocks' => 3,
                'words' => 8,
            ],
        ],
        'dialogue_lines' => [
            'eng' => [
                [
                    'character' => 'narrator',
                    'text' => 'The story begins...',
                    'file' => 'script.rpy',
                    'line' => 1,
                    'context' => 'intro',
                ],
                [
                    'character' => 'alice',
                    'text' => 'Hi there!',
                    'file' => 'script.rpy',
                    'line' => 5,
                    'context' => 'greeting',
                ],
                [
                    'character' => 'menu_choice',
                    'text' => 'Say hello back',
                    'file' => 'script.rpy',
                    'line' => 10,
                    'context' => 'choice',
                ],
            ],
        ],
    ];

    $gameStatsService = app(GameStatsService::class);
    $gameStatsService->saveVersionStats($version, $dialogueData);

    // Verify narrator line (assigned to narrator character)
    $narratorCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'narrator')
        ->first();
    expect($narratorCharacter)->not->toBeNull();

    $narratorLine = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $narratorCharacter->id)
        ->first();
    expect($narratorLine)->not->toBeNull()
        ->and($narratorLine->text_content)->toBe('The story begins...');

    // Verify regular character line
    $aliceCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'alice')
        ->first();
    expect($aliceCharacter)->not->toBeNull();

    $aliceLine = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $aliceCharacter->id)
        ->first();
    expect($aliceLine)->not->toBeNull()
        ->and($aliceLine->text_content)->toBe('Hi there!');

    // Verify menu choice line
    $menuChoiceCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'menu_choice')
        ->first();
    expect($menuChoiceCharacter)->not->toBeNull();

    $menuChoiceLine = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $menuChoiceCharacter->id)
        ->first();
    expect($menuChoiceLine)->not->toBeNull()
        ->and($menuChoiceLine->text_content)->toBe('Say hello back');

    // Verify total count
    $totalLines = DialogueLine::where('game_version_id', $version->id)->count();
    expect($totalLines)->toBe(3);
});
