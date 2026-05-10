<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use App\Models\VersionCharacterStats;
use App\Services\DialogueSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;

uses(RefreshDatabase::class);

function seedDialogueSearchLanguage(string $isoCode = 'eng', string $name = 'English'): void
{
    DB::table('iso_639_3_languages')->insertOrIgnore([
        'id' => $isoCode,
        'scope' => 'I',
        'type' => 'L',
        'ref_name' => $name,
        'part1' => $isoCode === 'eng' ? 'en' : null,
        'flag_code' => $isoCode === 'eng' ? 'gb' : $isoCode,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeDialogueSearchFixture(): array
{
    seedDialogueSearchLanguage();

    $game = Game::factory()->create([
        'name' => 'Dialogue Search VN',
        'is_visible' => true,
    ]);
    $version = GameVersion::factory()->for($game)->create([
        'version' => '1.0',
    ]);
    $character = Character::factory()->for($game)->create([
        'character_id' => 'alex',
        'display_names' => ['eng' => 'Alex'],
    ]);
    $text = UniqueDialogueText::factory()->create([
        'text_content' => 'Moonlight reveals the hidden archive clue.',
        'text_hash' => md5('Moonlight reveals the hidden archive clue.'),
    ]);
    $line = DialogueLine::factory()->create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'text_id' => $text->id,
        'context' => 'chapter-one',
        'file_path' => 'script.rpy',
        'line_number' => 42,
    ]);

    return [$game, $version, $character, $text, $line];
}

function bindDialogueMeilisearch(array $hits, int $total, callable $assertSearch): void
{
    $index = new class($hits, $total, $assertSearch)
    {
        public function __construct(
            private readonly array $hits,
            private readonly int $total,
            private readonly mixed $assertSearch
        ) {}

        public function search(string $term, array $params): object
        {
            ($this->assertSearch)($term, $params);

            return new class($this->hits, $this->total)
            {
                public function __construct(
                    private readonly array $hits,
                    private readonly int $total
                ) {}

                public function getHits(): array
                {
                    return $this->hits;
                }

                public function getEstimatedTotalHits(): int
                {
                    return $this->total;
                }
            };
        }
    };

    app()->instance(Client::class, new class($index)
    {
        public function __construct(private readonly object $index) {}

        public function index(string $name): object
        {
            expect($name)->toBe('game_dialogue_texts');

            return $this->index;
        }
    });
}

it('searches meilisearch, preserves hit order, and attaches highlighted dialogue text', function () {
    [$game, $version, $character, $text, $line] = makeDialogueSearchFixture();

    bindDialogueMeilisearch([
        [
            'text_id' => $text->id,
            'text_content' => $text->text_content,
            '_formatted' => ['text_content' => '<mark>Moonlight</mark> reveals the hidden archive clue.'],
        ],
    ], 1, function (string $term, array $params) use ($game, $version, $character) {
        expect($term)->toBe('"moonlight"')
            ->and($params['limit'])->toBe(5)
            ->and($params['offset'])->toBe(5)
            ->and($params['filter'])->toBe("language = 'eng' AND game_id = {$game->id} AND version_ids = {$version->id} AND character_ids = {$character->id}");
    });

    $results = app(DialogueSearchService::class)->search('moonlight', [
        'language' => 'eng',
        'game_id' => $game->id,
        'version_id' => $version->id,
        'character_id' => $character->id,
        'context' => 'chapter-one',
        'exact_match' => true,
    ], 5, 2);

    expect($results->total())->toBe(1)
        ->and($results->perPage())->toBe(5)
        ->and($results->currentPage())->toBe(2)
        ->and($results->items()[0]->id)->toBe($line->id)
        ->and($results->items()[0]->highlighted_text)->toBe('<mark>Moonlight</mark> reveals the hidden archive clue.')
        ->and($results->items()[0]->relationLoaded('text'))->toBeTrue()
        ->and($results->items()[0]->relationLoaded('character'))->toBeTrue();
});

it('bounds expanded dialogue rows to the requested page size', function () {
    [$game, $version, $character, $text] = makeDialogueSearchFixture();

    DialogueLine::factory()->count(5)->create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'text_id' => $text->id,
        'context' => 'chapter-one',
    ]);

    bindDialogueMeilisearch([
        [
            'text_id' => $text->id,
            'text_content' => $text->text_content,
            '_formatted' => ['text_content' => '<mark>Moonlight</mark> reveals the hidden archive clue.'],
        ],
    ], 1, function (string $term, array $params) use ($game) {
        expect($term)->toBe('moonlight')
            ->and($params['limit'])->toBe(1)
            ->and($params['filter'])->toBe("language = 'eng' AND game_id = {$game->id}");
    });

    $results = app(DialogueSearchService::class)->search('moonlight', [
        'language' => 'eng',
        'game_id' => $game->id,
        'context' => 'chapter-one',
    ], 1, 1);

    expect($results->items())->toHaveCount(1);
});

it('reapplies character key filters when expanding meilisearch hits', function () {
    [$game, $version, $character, $text] = makeDialogueSearchFixture();
    $otherCharacter = Character::factory()->for($game)->create([
        'character_id' => 'blair',
        'display_names' => ['eng' => 'Blair'],
    ]);
    DialogueLine::factory()->create([
        'game_version_id' => $version->id,
        'character_id' => $otherCharacter->id,
        'iso_code' => 'eng',
        'text_id' => $text->id,
        'context' => 'chapter-one',
    ]);

    bindDialogueMeilisearch([
        [
            'text_id' => $text->id,
            'text_content' => $text->text_content,
            '_formatted' => ['text_content' => '<mark>Moonlight</mark> reveals the hidden archive clue.'],
        ],
    ], 1, function (string $term, array $params) use ($game) {
        expect($term)->toBe('moonlight')
            ->and($params['filter'])->toBe("language = 'eng' AND game_id = {$game->id}");
    });

    $results = app(DialogueSearchService::class)->search('moonlight', [
        'language' => 'eng',
        'game_id' => $game->id,
        'character_id' => 'alex',
        'context' => 'chapter-one',
    ], 10, 1);

    expect($results->items())->toHaveCount(1)
        ->and($results->items()[0]->character_id)->toBe($character->id);
});

it('returns an empty paginator when meilisearch finds no dialogue hits', function () {
    bindDialogueMeilisearch([], 0, function (string $term, array $params) {
        expect($term)->toBe('nothing')
            ->and($params['filter'])->toBe("language = 'eng'");
    });

    $results = app(DialogueSearchService::class)->search('nothing', ['language' => 'eng'], 10, 1);

    expect($results->total())->toBe(0)
        ->and($results->items())->toBe([]);
});

it('returns top duplicate dialogue with example context and display names', function () {
    [$game, $version, $character, $text] = makeDialogueSearchFixture();
    DialogueLine::factory()->count(2)->create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'text_id' => $text->id,
        'context' => 'chapter-two',
    ]);

    $duplicates = app(DialogueSearchService::class)->getTopDuplicates([
        'game_id' => $game->id,
        'version_id' => $version->id,
        'character_id' => 'alex',
        'language' => 'eng',
        'min_length' => 10,
        'min_count' => 3,
    ], 5);

    expect($duplicates)->toHaveCount(1)
        ->and($duplicates[0]->text_id)->toBe($text->id)
        ->and((int) $duplicates[0]->usage_count)->toBe(3)
        ->and($duplicates[0]->examples)->toHaveCount(3)
        ->and($duplicates[0]->examples[0]->game_name)->toBe($game->name)
        ->and($duplicates[0]->examples[0]->character_display_name)->toBe('Alex');
});

it('calculates version and global dialogue statistics', function () {
    Cache::flush();
    [$game, $version, $character, $text] = makeDialogueSearchFixture();

    VersionCharacterStats::factory()->create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'blocks' => 4,
        'words' => 120,
    ]);

    $bulkLines = [];
    for ($i = 0; $i < 101; $i++) {
        $bulkLines[] = [
            'game_version_id' => $version->id,
            'character_id' => $character->id,
            'iso_code' => 'eng',
            'file_path' => 'bulk.rpy',
            'line_number' => 1000 + $i,
            'text_id' => $text->id,
            'context' => 'bulk',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('version_dialogue_lines')->insert($bulkLines);

    $service = app(DialogueSearchService::class);
    $versionStats = $service->getVersionStatistics($version);
    $globalStats = $service->getGlobalStatistics();
    $gameDuplication = $service->getGameDuplicationStats(5);

    expect($versionStats['total_lines'])->toBe(102)
        ->and($versionStats['total_words'])->toBe(120)
        ->and($versionStats['unique_characters'])->toBe(1)
        ->and($versionStats['avg_words_per_line'])->toBe(1.2)
        ->and($versionStats['languages'][0]->iso_code)->toBe('eng')
        ->and($globalStats['total_lines'])->toBe(102)
        ->and($globalStats['unique_texts'])->toBe(1)
        ->and($globalStats['total_games_with_dialogue'])->toBe(1)
        ->and($globalStats['duplication_ratio'])->toEqual(102.0)
        ->and($globalStats['space_efficiency'])->toBeGreaterThan(99)
        ->and($globalStats['most_duplicated_games'])->toHaveCount(1)
        ->and($gameDuplication)->toHaveCount(1)
        ->and($gameDuplication[0]->id)->toBe($game->id);
});

it('uses the default PostgreSQL search language configuration and vector column', function () {
    $service = app(DialogueSearchService::class);
    $config = new ReflectionMethod($service, 'getLanguageConfig');
    $vector = new ReflectionMethod($service, 'getTsvectorColumnForLanguage');
    $config->setAccessible(true);
    $vector->setAccessible(true);

    expect($config->invoke($service, 'jpn'))->toBe('english')
        ->and($vector->invoke($service, 'jpn'))->toBe('search_vector');
});
