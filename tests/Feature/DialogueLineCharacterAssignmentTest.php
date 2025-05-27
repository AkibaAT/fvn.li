<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameStatsService;

test('it assigns narrator character id for narrator dialogue', function () {
    // Create a game and version
    $game = Game::factory()->create();
    $version = GameVersion::factory()->create(['game_id' => $game->id]);

    // Mock dialogue data with narrator lines
    $dialogueData = [
        'languages' => [
            'eng' => [
                'blocks' => 2,
                'words' => 10,
            ],
        ],
        'dialogue_lines' => [
            'eng' => [
                [
                    'character' => 'narrator',
                    'text' => 'This is narrator text.',
                    'file' => 'script.rpy',
                    'line' => 10,
                    'context' => 'intro',
                ],
            ],
        ],
    ];

    // Process the dialogue lines
    $gameStatsService = app(GameStatsService::class);
    $gameStatsService->saveVersionStats($version, $dialogueData);

    // Verify that narrator character was created
    $narratorCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'narrator')
        ->first();

    expect($narratorCharacter)->not->toBeNull();

    // Verify that narrator lines are assigned to the narrator character
    $narratorLine = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $narratorCharacter->id)
        ->first();

    expect($narratorLine)->not->toBeNull()
        ->and($narratorLine->text_content)->toBe('This is narrator text.');
});

test('it assigns correct character id for menu choice dialogue', function () {
    // Create a game and version
    $game = Game::factory()->create();
    $version = GameVersion::factory()->create(['game_id' => $game->id]);

    // Mock dialogue data with menu choice lines
    $dialogueData = [
        'languages' => [
            'eng' => [
                'blocks' => 1,
                'words' => 4,
            ],
        ],
        'dialogue_lines' => [
            'eng' => [
                [
                    'character' => 'menu_choice',
                    'text' => 'Option 1: Go left',
                    'file' => 'script.rpy',
                    'line' => 20,
                    'context' => 'choice',
                ],
            ],
        ],
    ];

    // Process the dialogue lines
    $gameStatsService = app(GameStatsService::class);
    $gameStatsService->saveVersionStats($version, $dialogueData);

    // Verify that menu_choice character was created
    $menuChoiceCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'menu_choice')
        ->first();

    expect($menuChoiceCharacter)->not->toBeNull();

    // Verify that the dialogue line has the correct character_id
    $menuChoiceLine = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $menuChoiceCharacter->id)
        ->first();

    expect($menuChoiceLine)->not->toBeNull()
        ->and($menuChoiceLine->text_content)->toBe('Option 1: Go left');
});

test('it assigns correct character id for regular character dialogue', function () {
    // Create a game and version
    $game = Game::factory()->create();
    $version = GameVersion::factory()->create(['game_id' => $game->id]);

    // Mock dialogue data with regular character lines
    $dialogueData = [
        'languages' => [
            'eng' => [
                'blocks' => 1,
                'words' => 4,
            ],
        ],
        'dialogue_lines' => [
            'eng' => [
                [
                    'character' => 'alice',
                    'text' => 'Hello, how are you?',
                    'file' => 'script.rpy',
                    'line' => 30,
                    'context' => 'conversation',
                ],
            ],
        ],
    ];

    // Process the dialogue lines
    $gameStatsService = app(GameStatsService::class);
    $gameStatsService->saveVersionStats($version, $dialogueData);

    // Verify that the character was created
    $character = Character::where('game_id', $game->id)
        ->where('character_id', 'alice')
        ->first();

    expect($character)->not->toBeNull();

    // Verify that the dialogue line has the correct character_id
    $characterLine = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $character->id)
        ->first();

    expect($characterLine)->not->toBeNull()
        ->and($characterLine->text_content)->toBe('Hello, how are you?');
});

test('it handles mixed character types correctly', function () {
    // Create a game and version
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

    // Process the dialogue lines
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

test('it assigns extend dialogue to the previous character', function () {
    // Create a game and version
    $game = Game::factory()->create();
    $version = GameVersion::factory()->create(['game_id' => $game->id]);

    // Mock dialogue data with extend statements already processed by Ren'Py script
    // (extend statements have been resolved to the correct characters)
    $dialogueData = [
        'languages' => [
            'eng' => [
                'blocks' => 3,
                'words' => 15,
            ],
        ],
        'dialogue_lines' => [
            'eng' => [
                [
                    'character' => 'alice',
                    'text' => 'Hello there!',
                    'file' => 'script.rpy',
                    'line' => 10,
                    'context' => 'intro',
                ],
                [
                    'character' => 'alice', // extend resolved to alice
                    'text' => ' How are you doing today?',
                    'file' => 'script.rpy',
                    'line' => 11,
                    'context' => 'intro',
                ],
                [
                    'character' => 'bob',
                    'text' => 'I am doing well, thanks!',
                    'file' => 'script.rpy',
                    'line' => 12,
                    'context' => 'intro',
                ],
                [
                    'character' => 'bob', // extend resolved to bob
                    'text' => ' And you?',
                    'file' => 'script.rpy',
                    'line' => 13,
                    'context' => 'intro',
                ],
            ],
        ],
    ];

    // Process the dialogue lines
    $gameStatsService = app(GameStatsService::class);
    $gameStatsService->saveVersionStats($version, $dialogueData);

    // Verify that alice character was created
    $aliceCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'alice')
        ->first();
    expect($aliceCharacter)->not->toBeNull();

    // Verify that bob character was created
    $bobCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'bob')
        ->first();
    expect($bobCharacter)->not->toBeNull();

    // Verify that no extend character was created
    $extendCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'extend')
        ->first();
    expect($extendCharacter)->toBeNull();

    // Verify that alice has 2 dialogue lines (original + extend)
    $aliceLines = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $aliceCharacter->id)
        ->get();
    expect($aliceLines)->toHaveCount(2);
    expect($aliceLines[0]->text_content)->toBe('Hello there!');
    expect($aliceLines[1]->text_content)->toBe(' How are you doing today?');

    // Verify that bob has 2 dialogue lines (original + extend)
    $bobLines = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $bobCharacter->id)
        ->get();
    expect($bobLines)->toHaveCount(2);
    expect($bobLines[0]->text_content)->toBe('I am doing well, thanks!');
    expect($bobLines[1]->text_content)->toBe(' And you?');

    // Verify total count
    $totalLines = DialogueLine::where('game_version_id', $version->id)->count();
    expect($totalLines)->toBe(4);
});

test('it assigns extend dialogue to narrator when no previous character exists', function () {
    // Create a game and version
    $game = Game::factory()->create();
    $version = GameVersion::factory()->create(['game_id' => $game->id]);

    // Mock dialogue data with extend as first statement already processed by Ren'Py script
    // (orphaned extend resolved to narrator)
    $dialogueData = [
        'languages' => [
            'eng' => [
                'blocks' => 1,
                'words' => 5,
            ],
        ],
        'dialogue_lines' => [
            'eng' => [
                [
                    'character' => 'narrator', // orphaned extend resolved to narrator
                    'text' => 'This is an orphaned extend statement.',
                    'file' => 'script.rpy',
                    'line' => 10,
                    'context' => 'intro',
                ],
            ],
        ],
    ];

    // Process the dialogue lines
    $gameStatsService = app(GameStatsService::class);
    $gameStatsService->saveVersionStats($version, $dialogueData);

    // Verify that narrator character was created
    $narratorCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'narrator')
        ->first();
    expect($narratorCharacter)->not->toBeNull();

    // Verify that no extend character was created
    $extendCharacter = Character::where('game_id', $game->id)
        ->where('character_id', 'extend')
        ->first();
    expect($extendCharacter)->toBeNull();

    // Verify that the extend line was assigned to narrator
    $narratorLine = DialogueLine::where('game_version_id', $version->id)
        ->where('character_id', $narratorCharacter->id)
        ->first();
    expect($narratorLine)->not->toBeNull();
    expect($narratorLine->text_content)->toBe('This is an orphaned extend statement.');

    // Verify total count
    $totalLines = DialogueLine::where('game_version_id', $version->id)->count();
    expect($totalLines)->toBe(1);
});
