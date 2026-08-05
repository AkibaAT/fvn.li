<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameDialogueText;
use App\Models\GameVersion;
use App\Models\Tag;
use App\Models\UniqueDialogueText;
use App\Services\MeilisearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

uses(RefreshDatabase::class);

function meilisearchTestClient(): Client
{
    return new Client(
        config('scout.meilisearch.host'),
        config('scout.meilisearch.key')
    );
}

function waitForMeilisearchTask(array $task): void
{
    $taskUid = $task['taskUid'] ?? $task['uid'] ?? null;

    if ($taskUid === null) {
        return;
    }

    $finished = meilisearchTestClient()->waitForTask($taskUid, 60000, 100);

    // A rejected task leaves the index on its previous state, which surfaces
    // much later as an unrelated failure, so it is raised where it happens.
    if (($finished['status'] ?? null) !== 'succeeded') {
        throw new RuntimeException(sprintf(
            'Meilisearch task %d (%s) %s: %s',
            $taskUid,
            $finished['type'] ?? 'unknown type',
            $finished['status'] ?? 'never finished',
            $finished['error']['message'] ?? 'no error message reported'
        ));
    }
}

function waitForMeilisearchAssertion(callable $assertion): mixed
{
    $deadline = microtime(true) + 8;
    $lastException = null;

    do {
        try {
            return $assertion();
        } catch (Throwable $exception) {
            $lastException = $exception;
            usleep(100000);
        }
    } while (microtime(true) < $deadline);

    throw $lastException;
}

/**
 * Configures an index from the settings the application ships, so a payload
 * Meilisearch rejects fails the suite instead of reaching a deployment.
 */
function configureMeilisearchIndex(string $indexName): void
{
    $client = meilisearchTestClient();

    try {
        $client->getRawIndex($indexName);
    } catch (ApiException) {
        waitForMeilisearchTask($client->createIndex($indexName, ['primaryKey' => 'id']));
    }

    $index = $client->index($indexName);

    /** @var array<string, mixed> $settings */
    $settings = config("scout.meilisearch.index-settings.{$indexName}", []);

    waitForMeilisearchTask($index->updateSettings($settings));

    // Settings carry no embedder, and vector search is out of scope here.
    // Configuring one would have every run download model weights.
    waitForMeilisearchTask($index->resetEmbedders());
}

function addMeilisearchDocument(string $indexName, array $document): void
{
    waitForMeilisearchTask(
        meilisearchTestClient()
            ->index($indexName)
            ->addDocuments([$document], 'id')
    );
}

function seedMeilisearchLanguage(string $isoCode = 'eng', string $name = 'English'): void
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

function makeMeilisearchGame(array $attributes = [], array $versionAttributes = []): Game
{
    $game = Game::factory()->create(array_merge([
        'name' => 'Meili Test '.str()->uuid(),
        'is_visible' => true,
        'first_visible_at' => now(),
    ], $attributes));

    GameVersion::factory()->for($game)->create(array_merge([
        'version' => '1.0',
        'is_latest' => true,
        'is_windows' => true,
        'published_at' => now(),
    ], $versionAttributes));

    return $game->fresh(['tags', 'gameJams', 'gameVersions']);
}

function configureMeilisearchIndexesOnce(): void
{
    static $configured = false;

    if ($configured) {
        return;
    }

    configureMeilisearchIndex('games');
    configureMeilisearchIndex('game_dialogue_texts');

    $configured = true;
}

function clearMeilisearchTestIndexes(): void
{
    $client = meilisearchTestClient();

    foreach (['games', 'game_dialogue_texts'] as $indexName) {
        waitForMeilisearchTask($client->index($indexName)->deleteAllDocuments());
    }
}

beforeEach(function () {
    config([
        'scout.driver' => 'meilisearch',
        'scout.meilisearch.host' => getenv('MEILISEARCH_HOST') ?: 'http://meilisearch:7700',
        'scout.meilisearch.key' => getenv('MEILISEARCH_KEY') ?: 'dev-master-key-change-in-production',
        'scout.queue' => false,
    ]);

    app()->forgetInstance(Client::class);
    app()->forgetInstance(EngineManager::class);

    try {
        expect(meilisearchTestClient()->health()['status'] ?? null)->toBe('available');
    } catch (Throwable $exception) {
        $this->markTestSkipped('Meilisearch is not reachable: '.$exception->getMessage());
    }

    configureMeilisearchIndexesOnce();
    clearMeilisearchTestIndexes();

    $this->service = new MeilisearchService;
});

test('searches games by name through meilisearch', function () {
    $token = 'doki-'.str()->lower(str()->random(8));
    $game = makeMeilisearchGame(['name' => "Doki {$token} Literature Club"]);
    addMeilisearchDocument('games', $game->fresh()->toSearchableArray());

    $results = waitForMeilisearchAssertion(fn () => $this->service->searchGames($token, ['show_hidden' => true]));

    expect($results->pluck('id')->all())->toContain($game->id);
});

test('filters games by tag platform and language attributes', function () {
    seedMeilisearchLanguage();

    $token = 'romance-'.str()->lower(str()->random(8));
    $tag = Tag::create(['name' => "Romance {$token}"]);
    $matching = makeMeilisearchGame(['name' => "Tagged {$token} VN"]);
    $matching->tags()->attach($tag);
    $matching->latestVersion->addSupportedLanguage('eng');

    $other = makeMeilisearchGame(['name' => "Other {$token} VN"], ['is_windows' => false]);

    addMeilisearchDocument('games', $matching->fresh(['tags', 'gameJams', 'gameVersions'])->toSearchableArray());
    addMeilisearchDocument('games', $other->fresh(['tags', 'gameJams', 'gameVersions'])->toSearchableArray());

    $results = waitForMeilisearchAssertion(fn () => $this->service->searchGames($token, [
        'show_hidden' => true,
        'tags' => [$tag->name],
        'supported_languages' => ['eng'],
        'is_windows' => true,
    ]));

    expect($results->pluck('id')->all())->toBe([$matching->id]);
});

test('sorts and paginates game search results through meilisearch', function () {
    $token = 'sorted-'.str()->lower(str()->random(8));
    $old = makeMeilisearchGame([
        'name' => "Old {$token}",
        'first_visible_at' => now()->subDays(10),
    ]);
    $new = makeMeilisearchGame([
        'name' => "New {$token}",
        'first_visible_at' => now()->subDay(),
    ]);

    addMeilisearchDocument('games', $old->fresh()->toSearchableArray());
    addMeilisearchDocument('games', $new->fresh()->toSearchableArray());

    $results = waitForMeilisearchAssertion(fn () => $this->service->searchGames(
        $token,
        ['show_hidden' => true],
        1,
        1,
        'first_visible_at',
        'desc'
    ));

    expect($results->count())->toBe(1)
        ->and($results->first()->id)->toBe($new->id)
        ->and($results->total())->toBe(2);
});

test('searches dialogue using the current game dialogue text index', function () {
    seedMeilisearchLanguage();

    $token = 'moonlight '.str()->lower(str()->random(8));
    $game = makeMeilisearchGame(['name' => "Dialogue {$token} VN"]);
    $version = $game->latestVersion;
    $character = Character::factory()->for($game)->create([
        'character_id' => 'alex',
        'display_names' => ['eng' => 'Alex'],
    ]);
    $text = UniqueDialogueText::factory()->create([
        'text_content' => "Moonlight reveals {$token} archive clue.",
        'text_hash' => md5("Moonlight reveals {$token} archive clue."),
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

    $dialogueDocument = GameDialogueText::getForGame($game->id)->first();
    addMeilisearchDocument('game_dialogue_texts', $dialogueDocument->toSearchableArray());

    $results = waitForMeilisearchAssertion(fn () => $this->service->searchDialogue($token, [
        'language' => 'eng',
        'game_id' => $game->id,
        'character_id' => $character->id,
        'context' => 'chapter-one',
    ], 5, 1));

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->id)->toBe($line->id)
        ->and($results->items()[0]->highlighted_text)->toContain('<mark>');
});
