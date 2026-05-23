<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameJam;
use App\Models\GameVersion;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\MeilisearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

function makeSearchGame(array $gameAttributes = []): Game
{
    DB::table('iso_639_3_languages')->insertOrIgnore([
        'id' => 'eng',
        'scope' => 'I',
        'type' => 'L',
        'ref_name' => 'English',
        'part1' => 'en',
        'flag_code' => 'gb',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $game = Game::factory()->create(array_merge([
        'name' => 'Searchable Game',
        'authors' => 'Search Author',
        'custom_tags' => 'romance mystery',
        'is_visible' => true,
        'is_delisted' => false,
        'platform' => 'itch_io',
        'first_visible_at' => now(),
        'url' => ['itch_io' => 'https://example.itch.io/searchable-game'],
    ], $gameAttributes));

    $version = GameVersion::factory()->for($game)->latest()->create([
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => false,
        'is_android' => true,
        'is_web' => false,
    ]);

    VersionSupportedLanguage::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'is_available' => true,
    ]);

    VersionLanguageStats::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'words' => 25000,
    ]);

    return $game->fresh(['latestVersion.supportedLanguages.language', 'latestVersion.languageStats']);
}

function paginatorForGames(array $games, ?int $total = null, int $perPage = 8, int $page = 1): LengthAwarePaginator
{
    return new LengthAwarePaginator(
        $games,
        $total ?? count($games),
        $perPage,
        $page,
        ['path' => route('games.index')]
    );
}

it('renders the game index with meilisearch filters, defaults, ignored games, and enriched game data', function () {
    $user = User::factory()->create();
    $includedGame = makeSearchGame(['name' => 'Included Search Result']);
    $ignoredGame = makeSearchGame(['name' => 'Ignored Search Result']);
    $tag = Tag::create(['name' => 'Romance']);
    $excludedTag = Tag::create(['name' => 'Horror']);
    $jam = GameJam::create([
        'name' => 'Search Jam',
        'url' => 'https://itch.io/jam/search-jam',
    ]);

    $user->preferences()->create([
        'preferred_languages' => ['eng'],
        'excluded_tags' => [$excludedTag->id],
    ]);
    $user->ignoredGames()->attach($ignoredGame->id);
    $list = VnList::factory()->for($user)->create([
        'name' => 'Search List',
        'type' => 'reading',
        'is_default' => true,
    ]);
    VnListEntry::factory()->create([
        'vn_list_id' => $list->id,
        'game_id' => $includedGame->id,
    ]);
    UserGameProgress::factory()->for($user)->for($includedGame)->create([
        'receive_updates' => true,
    ]);

    $this->mock(MeilisearchService::class, function (MockInterface $mock) use ($includedGame, $ignoredGame) {
        $mock->shouldReceive('searchGames')
            ->once()
            ->with(
                'wolves',
                Mockery::on(fn ($filters) => $filters['is_visible'] === true
                    && $filters['status'] === ['Released']
                    && $filters['game_engine'] === ["Ren'Py"]
                    && $filters['is_windows'] === true
                    && $filters['platform'] === ['itch_io']
                    && $filters['supported_languages'] === ['eng']
                    && $filters['tags'] === ['Romance']
                    && $filters['excluded_tags'] === ['Horror']
                    && $filters['game_jams'] === ['Search Jam']
                    && $filters['reading_time'] === 'medium'
                    && $filters['is_nsfw'] === false
                    && $filters['is_paid'] === false
                    && $filters['has_demo'] === true
                    && $filters['is_on_sale'] === true),
                12,
                1,
                'rating_score',
                'asc',
                [$ignoredGame->id]
            )
            ->andReturn(paginatorForGames([$includedGame], 1, 12, 1));
    });

    $response = $this->actingAs($user)->get(route('games.index', [
        'search' => 'wolves',
        'selectedStatuses' => ['Released'],
        'selectedEngines' => ["Ren'Py"],
        'selectedPlatforms' => ['windows'],
        'selectedStorePlatforms' => ['itch_io'],
        'selectedTags' => [$tag->id],
        'selectedGameJams' => [$jam->id],
        'readingTime' => 'medium',
        'sfw' => '1',
        'showFree' => '1',
        'showDemo' => '1',
        'showSale' => '1',
        'sort' => 'rating_score',
        'direction' => 'asc',
        'perPage' => 12,
    ]));

    $response->assertOk();
    $page = $response->viewData('page');
    $props = $page['props'];
    $game = $props['games']['data'][0];

    expect($page['component'])->toBe('games/index')
        ->and($props['currentFilters']['search'])->toBe('wolves')
        ->and($props['currentFilters']['selectedLanguages'])->toBe(['eng'])
        ->and($props['currentFilters']['excludedTags'])->toBe([(string) $excludedTag->id])
        ->and($props['currentFilters']['usingDefaultLanguages'])->toBeTrue()
        ->and($props['currentFilters']['usingDefaultExcludedTags'])->toBeTrue()
        ->and($props['ignoredCount'])->toBe(1)
        ->and($props['ignoredGameIds'])->toBe([$ignoredGame->id])
        ->and($game['id'])->toBe($includedGame->id)
        ->and($game['is_windows'])->toBeTrue()
        ->and($game['is_linux'])->toBeTrue()
        ->and($game['is_android'])->toBeTrue()
        ->and($game['english_word_count'])->toBe(25000)
        ->and($game['supported_languages'][0]['iso_code'])->toBe('eng')
        ->and($game['user_progress'][0]->receive_updates)->toBeTrue()
        ->and($game['user_list_memberships'][0]->list_id)->toBe($list->id)
        ->and($props['metaTags']['socialTitle'])->toContain('Search: wolves');
});

it('resets an out of range meilisearch page back to page one', function () {
    $game = makeSearchGame(['name' => 'Only Result']);

    $this->mock(MeilisearchService::class, function (MockInterface $mock) use ($game) {
        $mock->shouldReceive('searchGames')
            ->once()
            ->with('*', Mockery::type('array'), 8, 5, 'first_visible_at', 'desc', [])
            ->andReturn(paginatorForGames([], 1, 8, 5));
        $mock->shouldReceive('searchGames')
            ->once()
            ->with('*', Mockery::type('array'), 8, 1, 'first_visible_at', 'desc', [])
            ->andReturn(paginatorForGames([$game], 1, 8, 1));
    });

    $response = $this->get(route('games.index', ['page' => 5]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['currentFilters']['page'])->toBe(1)
        ->and($props['games']['data'][0]['id'])->toBe($game->id);
});

it('falls back to database search when meilisearch fails', function () {
    $matchingGame = makeSearchGame([
        'name' => 'Fallback Match',
        'authors' => 'Fallback Author',
        'first_visible_at' => now(),
    ]);
    makeSearchGame([
        'name' => 'Fallback Ignored',
        'authors' => 'Fallback Author',
        'first_visible_at' => now()->subDay(),
    ]);
    makeSearchGame([
        'name' => 'Hidden Fallback Match',
        'authors' => 'Fallback Author',
        'is_visible' => false,
    ]);

    $this->mock(MeilisearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('searchGames')
            ->andThrow(new RuntimeException('search unavailable'));
    });

    $response = $this->get(route('games.index', [
        'search' => 'Fallback Match',
        'perPage' => 8,
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['games']['total'])->toBe(1)
        ->and($props['games']['data'][0]['id'])->toBe($matchingGame->id)
        ->and($props['games']['data'][0]['is_windows'])->toBeTrue()
        ->and($props['games']['data'][0]['english_word_count'])->toBe(25000);
});

it('returns simple autocomplete results without selecting a missing cover image column', function () {
    $game = makeSearchGame([
        'name' => 'Autocomplete Match',
        'thumb_url' => 'https://example.com/thumb.png',
    ]);
    makeSearchGame(['name' => 'Different Game']);

    $this->getJson(route('api.games.search', ['q' => 'Autocomplete']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $game->id)
        ->assertJsonPath('0.name', 'Autocomplete Match')
        ->assertJsonPath('0.thumb_url', 'https://example.com/thumb.png');
});

it('returns enhanced search results with converted tag and jam names', function () {
    $game = makeSearchGame(['name' => 'Enhanced Result']);
    $tag = Tag::create(['name' => 'Mystery']);
    $jam = GameJam::create([
        'name' => 'Enhanced Jam',
        'url' => 'https://itch.io/jam/enhanced-jam',
    ]);

    $this->mock(MeilisearchService::class, function (MockInterface $mock) use ($game) {
        $mock->shouldReceive('searchGames')
            ->once()
            ->with(
                'enhanced',
                Mockery::on(fn ($filters) => $filters['status'] === ['released']
                    && $filters['is_nsfw'] === false
                    && $filters['is_paid'] === true
                    && $filters['has_demo'] === true
                    && $filters['game_engine'] === ["Ren'Py"]
                    && $filters['tags'] === ['Mystery']
                    && $filters['game_jams'] === ['Enhanced Jam']
                    && $filters['supported_languages'] === ['eng']
                    && $filters['is_visible'] === true),
                5,
                2
            )
            ->andReturn(paginatorForGames([$game], 1, 5, 2));
    });

    $this->getJson(route('api.games.search-enhanced', [
        'q' => 'enhanced',
        'status' => ['released'],
        'is_nsfw' => '0',
        'is_paid' => '1',
        'has_demo' => '1',
        'game_engine' => ["Ren'Py"],
        'tags' => [$tag->id],
        'game_jams' => [$jam->id],
        'supported_languages' => ['eng'],
        'perPage' => 5,
        'page' => 2,
    ]))->assertOk()
        ->assertJsonPath('data.0.id', $game->id)
        ->assertJsonPath('current_page', 2);
});

it('returns service unavailable when enhanced or global search backends fail', function () {
    $this->mock(MeilisearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('searchGames')->andThrow(new RuntimeException('offline'));
        $mock->shouldReceive('globalSearch')->andThrow(new RuntimeException('offline'));
    });

    $this->getJson(route('api.games.search-enhanced', ['q' => 'offline']))
        ->assertStatus(503)
        ->assertJsonPath('error', 'Search unavailable');

    $this->getJson(route('api.search.global', ['q' => 'offline']))
        ->assertStatus(503)
        ->assertJsonPath('error', 'Search unavailable');
});

it('returns global search results and random visible game slugs', function () {
    $game = makeSearchGame(['name' => 'Random Candidate']);
    makeSearchGame(['name' => 'Delisted Candidate', 'is_delisted' => true]);

    $this->mock(MeilisearchService::class, function (MockInterface $mock) use ($game) {
        $mock->shouldReceive('globalSearch')
            ->once()
            ->with('candidate')
            ->andReturn([
                'games' => [['id' => $game->id, 'name' => $game->name]],
                'dialogue' => [],
                'tags' => [],
                'total_games' => 1,
                'total_dialogue' => 0,
                'total_tags' => 0,
            ]);
    });

    $this->getJson(route('api.search.global', ['q' => 'candidate']))
        ->assertOk()
        ->assertJsonPath('games.0.id', $game->id)
        ->assertJsonPath('total_games', 1);

    $this->getJson(route('games.random'))
        ->assertOk()
        ->assertJsonStructure(['slug']);
});

it('returns 404 for random game when no visible games exist', function () {
    Game::query()->delete();

    $this->getJson(route('games.random'))
        ->assertNotFound()
        ->assertJsonPath('error', 'No games found');
});
