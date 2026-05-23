<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Tag;
use App\Models\UniqueDialogueText;
use App\Services\SearchIndexService;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client as MeilisearchClient;
use Meilisearch\Endpoints\Indexes;

it('reindexes visible games and tags with progress callbacks', function () {
    $visibleGame = Game::factory()->create([
        'name' => 'Visible Indexed Game',
        'is_visible' => true,
    ]);
    Game::factory()->create([
        'name' => 'Hidden Indexed Game',
        'is_visible' => false,
    ]);

    Tag::create(['name' => 'Romance']);
    Tag::create(['name' => 'Mystery']);

    $service = new SearchIndexService;
    $progress = [];

    $gameStats = $service->reindexGames(function (int $count) use (&$progress) {
        $progress[] = ['games', $count];
    });
    $tagStats = $service->reindexTags(function (int $count) use (&$progress) {
        $progress[] = ['tags', $count];
    });

    expect($gameStats)->toBe(['count' => 1, 'errors' => []])
        ->and($tagStats)->toBe(['count' => 2, 'errors' => []])
        ->and($progress)->toContain(['games', 1])
        ->and($progress)->toContain(['tags', 2]);
});

it('runs a full reindex and returns aggregate stats without dialogue rows', function () {
    $game = Game::factory()->create([
        'name' => 'Full Reindex Game',
        'is_visible' => true,
    ]);
    Tag::create(['name' => 'Drama']);

    $stats = (new SearchIndexService)->fullReindex();

    expect($stats['games'])->toBe(1)
        ->and($stats['dialogue_texts'])->toBe(0)
        ->and($stats['tags'])->toBe(1)
        ->and($stats['errors'])->toBe([]);
});

it('includes dialogue rows in full reindex aggregate stats', function () {
    $game = Game::factory()->create([
        'name' => 'Full Dialogue Reindex Game',
        'is_visible' => true,
    ]);
    $version = GameVersion::factory()->for($game)->create();
    $character = Character::factory()->for($game)->create([
        'display_names' => ['eng' => 'Narrator'],
    ]);
    $text = UniqueDialogueText::factory()->create([
        'text_content' => 'Full reindex dialogue.',
        'text_hash' => md5('Full reindex dialogue.'),
    ]);

    DialogueLine::factory()->for($version, 'gameVersion')->for($character)->create([
        'text_id' => $text->id,
        'iso_code' => 'eng',
    ]);

    $stats = (new SearchIndexService)->fullReindex();

    expect($stats['games'])->toBe(1)
        ->and($stats['dialogue_texts'])->toBe(1)
        ->and($stats['tags'])->toBe(0)
        ->and($stats['errors'])->toBe([]);
});

it('reindexes dialogue safely when no games have dialogue', function () {
    $stats = (new SearchIndexService)->reindexDialogue();

    expect($stats)->toBe(['count' => 0, 'errors' => []]);
});

it('reindexes dialogue texts for games with dialogue rows', function () {
    $game = Game::factory()->create(['name' => 'Dialogue Indexed Game']);
    $version = GameVersion::factory()->for($game)->create();
    $character = Character::factory()->for($game)->create([
        'display_names' => ['eng' => 'Guide'],
    ]);
    $text = UniqueDialogueText::factory()->create([
        'text_content' => 'Index this line.',
        'text_hash' => md5('Index this line.'),
    ]);

    DialogueLine::factory()->for($version, 'gameVersion')->for($character)->create([
        'text_id' => $text->id,
        'iso_code' => 'eng',
    ]);
    DialogueLine::factory()->for($version, 'gameVersion')->for($character)->create([
        'text_id' => $text->id,
        'iso_code' => 'jpn',
    ]);

    $progress = [];
    $stats = (new SearchIndexService)->reindexDialogue(function (int $count) use (&$progress) {
        $progress[] = $count;
    });

    expect($stats)->toBe(['count' => 2, 'errors' => []])
        ->and($progress)->toBe([2]);
});

it('removes indexed models and ignores empty removal requests', function () {
    Log::spy();

    $service = new SearchIndexService;
    $service->removeFromIndex(Game::class, []);
    $service->removeFromIndex(Game::class, [123, 456]);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Removed items from search index', [
            'model' => Game::class,
            'ids_count' => 2,
        ]);
});

it('returns search index stats and health while tolerating per-index failures', function () {
    $gamesIndex = Mockery::mock(Indexes::class);
    $gamesIndex->shouldReceive('stats')->twice()->andReturn([
        'numberOfDocuments' => 12,
        'isIndexing' => false,
        'fieldDistribution' => ['name' => 12],
    ]);

    $failingIndex = Mockery::mock(Indexes::class);
    $failingIndex->shouldReceive('stats')->times(4)->andThrow(new RuntimeException('index unavailable'));

    $client = Mockery::mock(MeilisearchClient::class);
    $client->shouldReceive('index')->twice()->with('games')->andReturn($gamesIndex);
    $client->shouldReceive('index')->twice()->with('game_dialogue_texts')->andReturn($failingIndex);
    $client->shouldReceive('index')->twice()->with('tags')->andReturn($failingIndex);
    $client->shouldReceive('health')->once()->andReturn(['status' => 'available']);

    app()->instance(MeilisearchClient::class, $client);

    $service = new SearchIndexService;
    $stats = $service->getIndexStats();
    $health = $service->healthCheck();

    expect($stats['games'])->toBe([
        'numberOfDocuments' => 12,
        'isIndexing' => false,
        'fieldDistribution' => ['name' => 12],
    ])
        ->and($stats['game_dialogue_texts']['error'])->toBe('index unavailable')
        ->and($health['meilisearch_status'])->toBe('available')
        ->and($health['healthy'])->toBeTrue()
        ->and($health['indexes']['games']['numberOfDocuments'])->toBe(12);
});

it('reports unhealthy search when the Meilisearch client cannot respond', function () {
    $client = Mockery::mock(MeilisearchClient::class);
    $client->shouldReceive('health')->once()->andThrow(new RuntimeException('connection refused'));

    app()->instance(MeilisearchClient::class, $client);

    expect((new SearchIndexService)->healthCheck())->toBe([
        'meilisearch_status' => 'error',
        'error' => 'connection refused',
        'healthy' => false,
    ]);
});

it('marks search unhealthy when Meilisearch responds with a non-available status', function () {
    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('stats')->times(3)->andReturn([]);

    $client = Mockery::mock(MeilisearchClient::class);
    $client->shouldReceive('health')->once()->andReturn(['status' => 'degraded']);
    $client->shouldReceive('index')->once()->with('games')->andReturn($index);
    $client->shouldReceive('index')->once()->with('game_dialogue_texts')->andReturn($index);
    $client->shouldReceive('index')->once()->with('tags')->andReturn($index);

    app()->instance(MeilisearchClient::class, $client);

    $health = (new SearchIndexService)->healthCheck();

    expect($health['meilisearch_status'])->toBe('degraded')
        ->and($health['healthy'])->toBeFalse()
        ->and($health['indexes']['games']['numberOfDocuments'])->toBe(0);
});

it('reports an index stats error when the Meilisearch client cannot be resolved', function () {
    Log::spy();

    app()->bind(MeilisearchClient::class, fn () => throw new RuntimeException('client missing'));

    expect((new SearchIndexService)->getIndexStats())->toBe(['error' => 'client missing']);

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Failed to get search index statistics', ['error' => 'client missing']);
});
