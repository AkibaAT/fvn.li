<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionSupportedLanguage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('scout.driver', 'null');
    $this->originalEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    DB::table('iso_639_3_languages')->updateOrInsert(
        ['id' => 'eng'],
        [
            'part2b' => 'eng',
            'part2t' => 'eng',
            'part1' => 'en',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $this->game = Game::withoutEvents(fn () => Game::factory()->create());
    $this->version = GameVersion::withoutEvents(fn () => GameVersion::create([
        'game_id' => $this->game->id,
        'version' => '1.0.0',
        'published_at' => now(),
        'is_latest' => true,
    ]));

    VersionSupportedLanguage::updateOrCreate(
        ['game_version_id' => $this->version->id, 'iso_code' => 'eng'],
        ['is_available' => true]
    );
});

afterEach(function () {
    if ($this->originalEventDispatcher) {
        Model::setEventDispatcher($this->originalEventDispatcher);
    }
});

it('deduplicates dialogue browser character options by display name and sorts them alphabetically', function () {
    $fred = createDialogueCharacter($this->game->id, $this->version->id, 'f', 'Fred');
    $fredAlt = createDialogueCharacter($this->game->id, $this->version->id, 'f2', 'Fred');
    createDialogueCharacter($this->game->id, $this->version->id, 'z', 'Zed');
    createDialogueCharacter($this->game->id, $this->version->id, 'a', 'Not Alice', ['eng' => 'Alice']);
    createDialogueCharacter($this->game->id, $this->version->id, 'narrator', 'Narrator');
    createDialogueCharacter($this->game->id, $this->version->id, 'menu_choice', 'Menu Choice');
    createDialogueCharacter($this->game->id, $this->version->id, 'alt', 'Alt Text');

    $response = $this->getJson(route('browser-api.dialogue.options', [
        'gameId' => $this->game->id,
        'versionId' => $this->version->id,
        'language' => 'eng',
    ]));

    $response->assertOk();

    $characters = $response->json('characters');

    expect(array_column($characters, 'name'))->toBe(['Alice', 'Fred', 'Zed'])
        ->and($characters[1]['character_id'])->toBe(collect([$fred->character_id, $fredAlt->character_id])->sort()->implode(','));
});

function createDialogueCharacter(
    int $gameId,
    int $versionId,
    string $characterId,
    string $displayName,
    array $displayNameCorrections = []
): Character {
    $character = Character::create([
        'game_id' => $gameId,
        'character_id' => $characterId,
        'display_names' => ['eng' => $displayName],
    ]);
    $character->forceFill(['display_name_corrections' => $displayNameCorrections])->save();

    $textId = DB::table('unique_dialogue_texts')->insertGetId([
        'text_hash' => md5($characterId),
        'text_content' => "Dialogue for {$characterId}",
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('version_dialogue_lines')->insert([
        'game_version_id' => $versionId,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'file_path' => 'script.rpy',
        'line_number' => $character->id,
        'text_id' => $textId,
        'context' => 'start',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $character;
}
