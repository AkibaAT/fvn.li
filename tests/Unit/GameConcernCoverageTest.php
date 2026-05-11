<?php

use App\Models\Character;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameJam;
use App\Models\GameVersion;
use App\Models\Tag;
use App\Models\User;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use App\Services\GameStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('game search array includes cleaned text tags jams languages platforms and visibility flags', function () {
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

    $game = Game::factory()->create([
        'name' => 'Searchable VN',
        'slug' => 'searchable-vn',
        'authors' => '<b>Studio Fox</b>',
        'description' => 'Play at https://example.com now',
        'full_description' => '<p>More at www.example.org and example.net/path</p>',
        'custom_description' => '<p>Custom page text https://custom.example</p>',
        'custom_tags' => null,
        'status' => 'Released',
        'is_visible' => true,
        'is_delisted' => false,
        'is_nsfw' => true,
        'is_paid' => true,
        'has_demo' => false,
        'min_price' => 499,
        'currency' => 'USD',
        'is_on_sale' => true,
        'sale_discount_percent' => 20,
        'game_engine' => "Ren'Py",
        'platform' => 'itch_io',
        'rating_score' => 4.5,
        'rating_count' => 12,
        'source_language_id' => 'eng',
        'has_custom_page' => true,
        'custom_page_updated_at' => now(),
    ]);

    $tag = Tag::create(['name' => 'Romance']);
    $gameJam = GameJam::create([
        'name' => 'Cozy Jam',
        'url' => 'https://itch.io/jam/cozy',
    ]);
    $game->tags()->attach($tag);
    $game->gameJams()->attach($gameJam);

    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => false,
        'is_android' => true,
        'is_web' => false,
        'published_at' => now(),
    ]);
    VersionSupportedLanguage::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'is_available' => true,
    ]);
    VersionLanguageStats::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'words' => 55555,
    ]);
    ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => 'trend-session',
        'ip_address' => '203.0.113.0',
        'clicked_at' => now(),
    ]);

    $searchable = $game->fresh()->toSearchableArray();

    expect($searchable['id'])->toBe($game->id)
        ->and($searchable['authors'])->toBe('Studio Fox')
        ->and($searchable['description'])->toBe('Play at now')
        ->and($searchable['full_description'])->toBe('More at and')
        ->and($searchable['custom_description'])->toBe('Custom page text')
        ->and($searchable['tags'])->toBe(['Romance'])
        ->and($searchable['game_jams'])->toBe(['Cozy Jam'])
        ->and($searchable['supported_languages'])->toBe(['eng'])
        ->and($searchable['english_word_count'])->toBe(55555)
        ->and($searchable['primary_word_count'])->toBe(55555)
        ->and($searchable['latest_version_id'])->toBe($version->id)
        ->and($searchable['is_windows'])->toBeTrue()
        ->and($searchable['is_linux'])->toBeTrue()
        ->and($searchable['is_android'])->toBeTrue()
        ->and($searchable['is_mac'])->toBeFalse()
        ->and($searchable['is_web'])->toBeFalse()
        ->and($searchable['trending_score'])->toBeGreaterThan(0)
        ->and($game->fresh()->searchableAs())->toBe('games')
        ->and($game->fresh()->shouldBeSearchable())->toBeTrue();
});

test('game media helpers resolve optimized and original image URLs and clear stored variants', function () {
    Storage::fake('public');
    Storage::disk('public')->put('thumb/default.webp', 'thumb');
    Storage::disk('public')->put('screens/default.webp', 'screen');
    Storage::disk('public')->put('screens/large.webp', 'screen');

    $game = Game::factory()->create([
        'thumb_url' => null,
        'optimized_thumbnails' => [
            'default' => ['path' => 'thumb/default.webp'],
        ],
        'screenshots' => [
            [
                'url' => 'https://example.com/original.png',
                'optimized' => [
                    'default' => ['path' => 'screens/default.webp'],
                    'large' => ['path' => 'screens/large.webp'],
                ],
            ],
        ],
    ]);

    expect($game->hasThumbnail())->toBeTrue()
        ->and($game->getEffectiveThumbnailUrl())->toBe('https://example.com/original.png')
        ->and($game->getThumbnailUrl())->toContain('/storage/thumb/default.webp')
        ->and($game->getFirstScreenshotUrl())->toContain('/storage/screens/default.webp')
        ->and($game->getScreenshotUrl(0, 'large'))->toContain('/storage/screens/large.webp')
        ->and($game->isScreenshotOptimized())->toBeTrue()
        ->and($game->getScreenshots()[0]['url'])->toContain('/storage/screens/large.webp')
        ->and($game->resolveScreenshots([]))->toBe([]);

    $game->clearOptimizedThumbnails();
    Storage::disk('public')->assertMissing('thumb/default.webp');
    expect($game->refresh()->optimized_thumbnails)->toBeNull();

    $game->clearOptimizedScreenshots();
    Storage::disk('public')->assertMissing('screens/default.webp');
    Storage::disk('public')->assertMissing('screens/large.webp');
    expect($game->refresh()->screenshots)->toBe([
        ['url' => 'https://example.com/original.png'],
    ]);
});

test('game attribute helpers sort links and expose platform metadata', function () {
    $version = GameVersion::factory()->make([
        'devlog' => 'Version notes',
        'is_windows' => true,
        'is_linux' => false,
        'is_mac' => true,
        'is_android' => false,
        'is_web' => true,
    ]);
    $game = Game::factory()->make([
        'rating_score' => 4.2,
        'rating_count' => 9,
        'additional_links' => [
            ['id' => 'b', 'sort_order' => 2],
            ['id' => 'a', 'sort_order' => 1],
            ['id' => 'c', 'sort_order' => 2],
        ],
    ]);
    $game->setRelation('latestVersion', $version);

    expect(Game::getAvailablePlatforms())->toHaveKey('windows', 'Windows')
        ->and($game->additional_links)->sequence(
            fn ($link) => $link->id->toBe('a'),
            fn ($link) => $link->id->toBe('b'),
            fn ($link) => $link->id->toBe('c'),
        )
        ->and($game->hasAdditionalLinks())->toBeTrue()
        ->and($game->devlog)->toBe('Version notes')
        ->and($game->rating)->toBe(4.2)
        ->and($game->rating_count)->toBe(9)
        ->and($game->platforms)->toBe([
            'windows' => true,
            'linux' => false,
            'mac' => true,
            'android' => false,
            'web' => true,
        ]);
});

test('custom game content concern handles effective values and edit state transitions', function () {
    $admin = User::factory()->make(['id' => 123, 'is_admin' => true]);
    $guest = null;
    $game = Game::factory()->make([
        'name' => 'Original Name',
        'custom_name' => 'Custom Name',
        'full_description' => 'Original Description',
        'custom_description' => 'Custom Description',
        'screenshots' => [['url' => 'https://example.com/original.png']],
        'custom_screenshots' => [['url' => 'https://example.com/custom.png']],
        'has_custom_page' => true,
        'view_mode' => 'custom',
    ]);

    expect($game->getEffectiveName())->toBe('Custom Name')
        ->and($game->getEffectiveName(forceOriginal: true))->toBe('Original Name')
        ->and($game->getEffectiveDescription())->toBe('Custom Description')
        ->and($game->getEffectiveDescription(forceOriginal: true))->toBe('Original Description')
        ->and($game->getEffectiveScreenshots()[0]['original_url'])->toBe('https://example.com/custom.png')
        ->and($game->canUserEdit($guest))->toBeFalse()
        ->and($game->canUserEdit($admin))->toBeTrue();

    $game->view_mode = 'original';
    expect($game->getEffectiveName())->toBe('Original Name')
        ->and($game->getEffectiveDescription())->toBe('Original Description')
        ->and($game->getEffectiveScreenshots()[0]['original_url'])->toBe('https://example.com/original.png');
});

test('game stats service saves language character and supported language aggregates', function () {
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

    $game = Game::factory()->create();
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'published_at' => now(),
    ]);
    $version->is_latest = false;
    $version->save();

    app(GameStatsService::class)->saveVersionStats($version, [
        'languages' => [
            'default' => [
                'blocks' => 5,
                'words' => 1234,
                'menus' => 2,
                'options' => 4,
                'characters' => [
                    'hero' => [
                        'display_name' => 'Hero',
                        'species' => 'fox',
                        'blocks' => 3,
                        'words' => 300,
                    ],
                ],
            ],
        ],
        'file_statistics' => [
            'summary' => [
                'total_scripts' => 2,
            ],
            'scripts' => [
                'rpy' => ['count' => 2, 'total_size' => 2048],
            ],
        ],
        'route_labels' => [
            ['name' => 'start', 'file' => 'script.rpy', 'line' => 1],
            ['name' => 'ending', 'file' => 'script.rpy', 'line' => 20, 'is_ending' => true],
        ],
        'route_edges' => [
            ['from_label' => 'start', 'to_label' => 'ending', 'edge_type' => 'jump', 'file' => 'script.rpy', 'line' => 10],
        ],
        'route_menu_choices' => [
            [
                'from_label' => 'start',
                'prompt' => 'Choose',
                'text' => 'Go',
                'target_label' => 'ending',
                'edge_type' => 'choice',
                'file' => 'script.rpy',
                'line' => 8,
                'translations' => ['default' => 'Go'],
                'prompt_translations' => ['default' => 'Choose'],
            ],
        ],
        'route_variables' => [
            ['name' => 'flag', 'default_value' => 'False', 'type' => 'default', 'file' => 'script.rpy', 'line' => 2],
            ['name' => 'flag', 'default_value' => 'False', 'type' => 'default', 'file' => 'script.rpy', 'line' => 2],
        ],
        'route_variable_changes' => [
            ['label' => 'start', 'variable' => 'flag', 'operation' => '=', 'value' => 'True', 'file' => 'script.rpy', 'line' => 9],
        ],
    ], 'eng', $game);

    expect(VersionLanguageStats::where('game_version_id', $version->id)->where('iso_code', 'eng')->first())
        ->blocks->toBe(5)
        ->words->toBe(1234)
        ->menus->toBe(2)
        ->options->toBe(4);

    $character = Character::where('game_id', $game->id)
        ->where('character_id', 'hero')
        ->firstOrFail();

    expect($character->display_names['eng'])->toBe('Hero')
        ->and($character->species)->toBe('fox')
        ->and($character->first_seen_in_version_id)->toBe($version->id)
        ->and($character->last_seen_in_version_id)->toBe($version->id)
        ->and(VersionCharacterStats::where('character_id', $character->id)->where('iso_code', 'eng')->value('words'))->toBe(300)
        ->and(VersionSupportedLanguage::where('game_version_id', $version->id)->where('iso_code', 'eng')->exists())->toBeTrue()
        ->and($version->fileCategories()->where('category', 'scripts')->first()?->total_size)->toBe(2048)
        ->and($version->routeLabels()->count())->toBe(2)
        ->and($version->routeEdges()->count())->toBe(1)
        ->and($version->routeMenuChoices()->count())->toBe(1)
        ->and($version->routeVariables()->count())->toBe(1)
        ->and($version->routeVariableChanges()->count())->toBe(1);
});

test('game stats service keeps real q-prefixed languages in supported languages', function () {
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
            'id' => 'quc',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => "K'iche'",
            'part1' => null,
            'flag_code' => 'gt',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();

    app(GameStatsService::class)->saveVersionStats($version, [
        'languages' => [
            'quc' => [
                'blocks' => 2,
                'words' => 20,
            ],
            'made-up-language' => [
                'blocks' => 1,
                'words' => 10,
            ],
        ],
    ], 'eng', $game);

    expect(VersionLanguageStats::where('game_version_id', $version->id)->where('iso_code', 'quc')->exists())->toBeTrue()
        ->and(VersionLanguageStats::where('game_version_id', $version->id)->where('iso_code', 'qaa')->exists())->toBeTrue()
        ->and(VersionSupportedLanguage::where('game_version_id', $version->id)->where('iso_code', 'quc')->exists())->toBeTrue()
        ->and(VersionSupportedLanguage::where('game_version_id', $version->id)->where('iso_code', 'qaa')->exists())->toBeFalse();
});
