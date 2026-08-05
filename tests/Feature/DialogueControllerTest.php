<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use App\Models\VersionCharacterStats;
use App\Models\VersionSupportedLanguage;
use App\Services\DialogueSearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;

function makeDialogueFixture(): array
{
    DB::table('iso_639_3_languages')->insertOrIgnore([
        [
            'id' => 'eng',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'part1' => 'en',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 'jpn',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'Japanese',
            'part1' => 'ja',
            'flag_code' => 'jp',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $game = Game::factory()->create([
        'name' => 'Dialogue Test Game',
        'is_visible' => true,
    ]);
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now(),
    ]);
    $character = Character::factory()->for($game)->create([
        'character_id' => 'alex',
        'display_names' => [
            'eng' => 'Alex',
            'jpn' => 'アレックス',
        ],
    ]);
    VersionSupportedLanguage::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'is_available' => true,
    ]);
    VersionSupportedLanguage::create([
        'game_version_id' => $version->id,
        'iso_code' => 'jpn',
        'is_available' => false,
    ]);
    VersionCharacterStats::create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'blocks' => 2,
        'words' => 42,
    ]);

    $text = UniqueDialogueText::factory()->create([
        'text_content' => 'Moonlight archive keeps important clues together.',
        'text_hash' => md5('Moonlight archive keeps important clues together.'),
    ]);
    $line = DialogueLine::factory()->create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'text_id' => $text->id,
        'context' => 'chapter-one',
        'file_path' => 'script.rpy',
        'line_number' => 12,
    ]);

    return [$game, $version, $character, $line];
}

it('renders the dialogue browser with selected game and version context', function () {
    [$game, $version] = makeDialogueFixture();

    $response = $this->get(route('dialogue.browser', [
        'game' => $game->slug,
        'versionId' => $version->id,
    ]));

    $response->assertOk();
    $page = $response->viewData('page');

    expect($page['component'])->toBe('dialogue/browser')
        ->and($page['props']['initial']['gameId'])->toBe($game->id)
        ->and($page['props']['initial']['gameName'])->toBe('Dialogue Test Game')
        ->and($page['props']['initial']['gameSlug'])->toBe($game->slug)
        ->and($page['props']['initial']['versionId'])->toBe($version->id);
});

it('does not expose a hidden game slug through a numeric dialogue browser id', function () {
    $hiddenGame = Game::factory()->create([
        'name' => 'Unlisted Dialogue Game',
        'slug' => 'unlisted-dialogue-game',
        'is_visible' => false,
    ]);
    $version = GameVersion::factory()->create([
        'game_id' => $hiddenGame->id,
        'version' => '1.0',
        'published_at' => now(),
    ]);

    $this->get("/dialogue/browser/{$hiddenGame->id}/{$version->id}")
        ->assertNotFound();

    $response = $this->get(route('dialogue.browser', [
        'game' => $hiddenGame->slug,
        'versionId' => $version->id,
    ]));

    $response->assertOk();
    $page = $response->viewData('page');

    expect($page['props']['initial']['gameId'])->toBe($hiddenGame->id)
        ->and($page['props']['initial']['gameSlug'])->toBe($hiddenGame->slug);
});

it('does not open the dialogue browser with a version from another game', function () {
    [$game] = makeDialogueFixture();
    $otherVersion = GameVersion::factory()->create([
        'game_id' => Game::factory()->create()->id,
    ]);

    $this->get(route('dialogue.browser', [
        'game' => $game->slug,
        'versionId' => $otherVersion->id,
    ]))->assertNotFound();
});

it('returns dialogue summary data filtered by available language and character search', function () {
    [$game, $version, $character] = makeDialogueFixture();

    $this->getJson(route('browser-api.dialogue.index', [
        'gameId' => $game->id,
        'versionId' => $version->id,
        'selectedLanguages' => 'eng,jpn',
        'q' => 'Alex',
        'perPage' => 10,
    ]))->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('filters.gameId', $game->id)
        ->assertJsonPath('summary.totalLines', 42)
        ->assertJsonPath('summary.uniqueCharacters', 1)
        ->assertJsonPath('summary.languages.0.id', 'eng')
        ->assertJsonPath('items.0.character.id', $character->id)
        ->assertJsonPath('items.0.character.name', 'Alex')
        ->assertJsonPath('items.0.words', 42)
        ->assertJsonPath('pagination.total', 1);
});

it('returns dialogue options for versions, available languages, characters, and contexts', function () {
    [$game, $version, $character] = makeDialogueFixture();

    $this->getJson(route('browser-api.dialogue.options', [
        'gameId' => $game->id,
        'versionId' => $version->id,
        'language' => 'eng',
    ]))->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('versions.0.id', $version->id)
        ->assertJsonPath('languages.0.id', 'eng')
        ->assertJsonPath('characters.0.id', $character->id)
        ->assertJsonPath('characters.0.name', 'Alex')
        ->assertJsonPath('contexts.0', 'chapter-one');
});

it('transforms dialogue search service results into API response rows', function () {
    [$game, $version, $character, $line] = makeDialogueFixture();
    $line->load(['gameVersion.game', 'gameVersion', 'text', 'character']);
    $line->highlighted_text = '<mark>Moonlight</mark> archive keeps important clues together.';

    $this->mock(DialogueSearchService::class, function (MockInterface $mock) use ($line) {
        $mock->shouldReceive('search')
            ->once()
            ->with('moonlight', Mockery::on(fn ($filters) => $filters['language'] === 'eng' && $filters['exact_match'] === true), 5, 1)
            ->andReturn(new LengthAwarePaginator([$line], 1, 5, 1));
    });

    $this->getJson(route('browser-api.dialogue.search', [
        'q' => 'moonlight',
        'language' => 'eng',
        'gameId' => $game->id,
        'versionId' => $version->id,
        'characterId' => 'alex',
        'context' => 'chapter-one',
        'exactMatch' => true,
        'perPage' => 5,
    ]))->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $line->id)
        ->assertJsonPath('data.0.highlighted_text', '<mark>Moonlight</mark> archive keeps important clues together.')
        ->assertJsonPath('data.0.character_id', 'alex')
        ->assertJsonPath('data.0.character_name', 'Alex')
        ->assertJsonPath('data.0.game.id', $game->id)
        ->assertJsonPath('data.0.version.id', $version->id)
        ->assertJsonPath('pagination.total', 1);
});

it('calculates word frequencies from dialogue lines and validates missing version ids', function () {
    [, $version] = makeDialogueFixture();

    $this->getJson(route('browser-api.dialogue.word-frequency', [
        'versionId' => $version->id,
        'language' => 'eng',
        'limit' => 10,
        'includePhrases' => 'true',
        'minWordLength' => 4,
    ]))->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['text' => 'moonlight']);

    $this->getJson(route('browser-api.dialogue.word-frequency'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['versionId']);
});

it('rejects oversized uncached word frequency corpora before tokenizing them', function () {
    [, $version, $character] = makeDialogueFixture();
    $text = str_repeat('expensive ', 250001);
    $uniqueText = UniqueDialogueText::factory()->create([
        'text_content' => $text,
        'text_hash' => md5($text),
    ]);

    DialogueLine::factory()->create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'text_id' => $uniqueText->id,
        'context' => 'large-corpus',
        'file_path' => 'large.rpy',
        'line_number' => 99,
    ]);

    $this->getJson(route('browser-api.dialogue.word-frequency', [
        'versionId' => $version->id,
        'language' => 'eng',
        'limit' => 10,
        'includePhrases' => 'true',
        'minWordLength' => 1,
    ]))->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Requested dialogue corpus is too large to process on demand.');
});

it('rate limits expensive public dialogue endpoints', function () {
    expect(Route::getRoutes()->getByName('browser-api.dialogue.word-frequency')->gatherMiddleware())
        ->toContain('throttle:20,1')
        ->and(Route::getRoutes()->getByName('browser-api.dialogue.search')->gatherMiddleware())
        ->toContain('throttle:60,1');
});
