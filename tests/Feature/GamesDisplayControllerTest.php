<?php

use App\Http\Controllers\Games\GamesDisplayController;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\SimilarGamesService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

function gameShowInertiaHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
    ];
}

test('game social meta tags format numeric string word counts without crashing', function () {
    $game = Game::factory()->create([
        'name' => 'String Word Count Game',
        'slug' => 'string-word-count-game',
        'description' => 'A detail page with imported stats.',
        'authors' => 'Stat Author',
        'status' => 'released',
        'is_visible' => true,
    ]);

    $controller = app(GamesDisplayController::class);
    $method = new ReflectionMethod($controller, 'prepareSocialMetaTags');
    $method->setAccessible(true);

    $method->invoke($controller, $game, new LengthAwarePaginator([], 0, 5), [
        'words' => '12345',
    ]);

    expect($controller->getMetaTags()['description'])
        ->toContain('12,345 words');
});

test('game show exposes itch screenshots as effective screenshots in original view mode', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
        'has_custom_page' => true,
        'view_mode' => 'original',
        'screenshots' => [
            ['url' => 'https://itch.example/original-a.jpg'],
            ['url' => 'https://itch.example/original-b.jpg'],
        ],
        'custom_screenshots' => [
            ['url' => 'https://custom.example/custom-a.jpg'],
        ],
    ]);

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $gameProps = $response->json('props.game');

    expect($response->json('component'))->toBe('games/show')
        ->and($gameProps['screenshots'])->toHaveCount(0)
        ->and($gameProps['custom_screenshots'])->toHaveCount(0)
        ->and($gameProps['effective_screenshots'])->toHaveCount(0);
});

test('game show exposes custom screenshots as effective screenshots in custom view mode', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
        'has_custom_page' => true,
        'view_mode' => 'custom',
        'screenshots' => [
            ['url' => 'https://itch.example/original-a.jpg'],
        ],
        'custom_screenshots' => [
            ['url' => 'https://custom.example/custom-a.jpg'],
            ['url' => 'https://custom.example/custom-b.jpg'],
        ],
    ]);

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $gameProps = $response->json('props.game');

    expect($response->json('component'))->toBe('games/show')
        ->and($gameProps['screenshots'])->toHaveCount(0)
        ->and($gameProps['custom_screenshots'])->toHaveCount(0)
        ->and($gameProps['effective_screenshots'])->toHaveCount(0);
});

test('game show exposes original name and description to guests in original view mode', function () {
    $game = Game::factory()->create([
        'name' => 'Original itch.io Name',
        'full_description' => '<p>Original itch.io text</p>',
        'is_visible' => true,
        'has_custom_page' => true,
        'view_mode' => 'original',
        'custom_name' => 'Custom Name',
        'custom_description' => '<p>Custom text</p>',
    ]);

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $gameProps = $response->json('props.game');

    expect($gameProps['effective_name'])->toBe('Original itch.io Name')
        ->and($gameProps['effective_description'])->toBe('<p>Original itch.io text</p>')
        ->and($gameProps['custom_name'])->toBe('Custom Name')
        ->and($gameProps['custom_description'])->toBe('<p>Custom text</p>');
});

test('game show exposes custom name and description to guests in custom view mode', function () {
    $game = Game::factory()->create([
        'name' => 'Original itch.io Name',
        'full_description' => '<p>Original itch.io text</p>',
        'is_visible' => true,
        'has_custom_page' => true,
        'view_mode' => 'custom',
        'custom_name' => 'Custom Name',
        'custom_description' => '<p>Custom text</p>',
    ]);

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $gameProps = $response->json('props.game');

    expect($gameProps['effective_name'])->toBe('Custom Name')
        ->and($gameProps['effective_description'])->toBe('<p>Custom text</p>')
        ->and($gameProps['name'])->toBe('Original itch.io Name')
        ->and($gameProps['full_description'])->toBe('<p>Original itch.io text</p>');
});

test('game show preserves missing custom screenshot list as null', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
        'has_custom_page' => false,
        'screenshots' => [
            ['url' => 'https://itch.example/original-a.jpg'],
        ],
        'custom_screenshots' => null,
    ]);

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $gameProps = $response->json('props.game');

    expect($gameProps['custom_screenshots'])->toBeNull()
        ->and($gameProps['effective_screenshots'])->toHaveCount(0);
});

test('game show sanitizes stored review html in initial props', function () {
    $viewer = User::factory()->create();
    $game = Game::factory()->create([
        'is_visible' => true,
        'name' => 'Review Sanitized Game',
    ]);
    $rater = Rater::factory()->create(['name' => 'Imported Reviewer']);

    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => '<div onmouseover="alert(1)">Public review</div><a href="javascript:alert(2)">bad link</a>',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);

    Rating::create([
        'game_id' => $game->id,
        'user_id' => $viewer->id,
        'rating' => 4,
        'review' => '<p style="color:red;position:absolute;background-image:url(javascript:alert(3))">Own review</p>',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'fvn_li',
        'published_at' => now()->subMinute(),
    ]);

    $response = $this
        ->actingAs($viewer)
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $reviews = collect($response->json('props.reviews.data'));
    $publicReview = $reviews->firstWhere('rating', 5)['review'];

    expect($publicReview)->toBe('<div>Public review</div><a rel="noopener">bad link</a>')
        ->and($response->json('props.userReview.review'))->toBe('<p style="color:red">Own review</p>')
        ->and($publicReview)->not->toContain('onmouseover')
        ->and($response->json('props.userReview.review'))->not->toContain('javascript:')
        ->and($response->json('props.userReview.review'))->not->toContain('position:absolute');
});

test('game show exposes rich version review progress analytics and recommendation props', function () {
    DB::table('iso_639_3_languages')->updateOrInsert(
        ['id' => 'eng'],
        [
            'part1' => 'en',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $user = User::factory()->create(['is_admin' => false]);
    $game = Game::factory()->create([
        'itch_id' => 123456,
        'name' => 'Rich Game',
        'slug' => 'rich-game',
        'authors' => '<strong>Dev Name</strong>',
        'description' => 'Short description',
        'full_description' => '<p>Long description</p>',
        'source_language_id' => 'eng',
        'is_visible' => true,
        'has_custom_page' => true,
        'view_mode' => 'custom',
        'custom_name' => 'Custom Rich Game',
        'custom_description' => '<p>Custom long description</p>',
        'additional_links' => [
            ['id' => 'discord', 'name' => 'Discord', 'url' => 'https://discord.example'],
        ],
    ]);
    SocialAccount::factory()->create([
        'user_id' => $user->id,
        'provider_name' => 'itchio',
        'provider_id' => 'itchio-user',
        'itchio_game_ids' => [123456],
    ]);

    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '3.0',
        'published_at' => now(),
        'is_windows' => true,
        'is_linux' => true,
        'is_mac' => true,
        'is_android' => false,
        'is_web' => true,
    ]);
    $version->forceFill(['is_latest' => true])->save();

    VersionSupportedLanguage::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'is_available' => true,
    ]);
    VersionLanguageStats::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'blocks' => 10,
        'words' => 420,
        'menus' => 2,
        'options' => 4,
    ]);
    $character = Character::factory()->create([
        'game_id' => $game->id,
        'character_id' => 'hero',
        'display_names' => ['eng' => 'Hero'],
    ]);
    VersionCharacterStats::factory()->create([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'blocks' => 3,
        'words' => 90,
    ]);
    DB::table('version_file_categories')->insert([
        'game_version_id' => $version->id,
        'category' => 'images',
        'total_count' => 2,
        'total_size' => 2048,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('version_dialogue_lines')->insert([
        'game_version_id' => $version->id,
        'character_id' => $character->id,
        'iso_code' => 'eng',
        'file_path' => 'script.rpy',
        'line_number' => 10,
        'text_id' => null,
        'context' => 'start',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('version_route_labels')->insert([
        'game_version_id' => $version->id,
        'name' => 'start',
        'file_path' => 'script.rpy',
        'line_number' => 1,
        'is_ending' => false,
        'returns_to_caller' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rater = Rater::factory()->create(['name' => 'Imported Rater']);
    Rating::create([
        'published_at' => now(),
        'event_id' => 987,
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'user_id' => $user->id,
        'rating' => 5,
        'review' => '<p>User review</p>',
        'is_visible' => true,
        'is_reviewed' => true,
        'has_spoilers' => true,
        'source_platform' => 'fvn_li',
    ]);
    UserGameProgress::factory()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'receive_updates' => true,
    ]);
    $list = VnList::factory()->create([
        'user_id' => $user->id,
        'is_public' => true,
        'name' => 'Public Picks',
    ]);
    VnListEntry::factory()->create([
        'vn_list_id' => $list->id,
        'game_id' => $game->id,
    ]);

    $similar = Game::factory()->create(['name' => 'Similar Game', 'is_visible' => true]);
    $developer = Game::factory()->create(['name' => 'Developer Game', 'is_visible' => true]);
    $recommendations = Mockery::mock(SimilarGamesService::class);
    $recommendations->shouldReceive('findSimilarGames')->once()->andReturn(collect([$similar]));
    $recommendations->shouldReceive('findDeveloperGames')->once()->andReturn(collect([$developer]));
    app()->instance(SimilarGamesService::class, $recommendations);

    $response = $this
        ->actingAs($user)
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    expect($response->json('component'))->toBe('games/show')
        ->and($response->json('props.game.effective_name'))->toBe('Custom Rich Game')
        ->and($response->json('props.editPermissions.canEdit'))->toBeTrue()
        ->and($response->json('props.editPermissions.isOwner'))->toBeTrue()
        ->and($response->json('props.canSeeAnalytics'))->toBeTrue()
        ->and($response->json('props.supportedLanguages.0.iso_code'))->toBe('eng')
        ->and($response->json('props.englishStats.words'))->toBe(420)
        ->and($response->json('props.primaryStats.words'))->toBe(420)
        ->and($response->json('props.estimatedReadingTime.total_minutes'))->toBe(3)
        ->and($response->json("props.versionCharacterCounts.{$version->id}"))->toBe(1)
        ->and($response->json("props.versionHasFileStats.{$version->id}"))->toBeTrue()
        ->and($response->json("props.versionHasDialogueLines.{$version->id}"))->toBeTrue()
        ->and($response->json("props.versionHasRouteData.{$version->id}"))->toBeTrue()
        ->and($response->json('props.userReview.rating'))->toBe(5)
        ->and($response->json('props.publicListsCount'))->toBe(1)
        ->and($response->json('props.similarGames.0.name'))->toBe('Similar Game')
        ->and($response->json('props.developerGames.0.name'))->toBe('Developer Game');
});

test('game show character count matches modal display name fallback semantics', function () {
    DB::table('iso_639_3_languages')->updateOrInsert(
        ['id' => 'eng'],
        [
            'part1' => 'en',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $game = Game::factory()->create([
        'name' => 'Fallback Character Count Game',
        'slug' => 'fallback-character-count-game',
        'is_visible' => true,
        'source_language_id' => 'eng',
    ]);

    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now(),
    ]);
    $version->forceFill(['is_latest' => true])->save();

    $correctedCharacter = Character::factory()->for($game)->create([
        'character_id' => 'corrected_character',
        'display_names' => ['jpn' => 'Corrected JP'],
        'display_name_corrections' => ['eng' => 'Corrected Character'],
    ]);
    $fallbackCharacter = Character::factory()->for($game)->create([
        'character_id' => 'fallback_character',
        'display_names' => ['jpn' => 'Fallback JP'],
        'display_name_corrections' => null,
    ]);

    foreach ([$correctedCharacter, $fallbackCharacter] as $character) {
        VersionCharacterStats::factory()->create([
            'game_version_id' => $version->id,
            'character_id' => $character->id,
            'iso_code' => 'eng',
            'blocks' => 1,
            'words' => 10,
        ]);
    }

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    expect($response->json("props.versionCharacterCounts.{$version->id}"))->toBe(2);
});
