<?php

use App\Http\Controllers\EditorUploadController;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

function controllerCoverageInertiaHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
    ];
}

test('guest login renders the Svelte login page and stores the intended URL', function () {
    $previousUrl = route('games.index');

    $response = $this
        ->withHeaders([
            ...controllerCoverageInertiaHeaders(),
            'Referer' => $previousUrl,
        ])
        ->get(route('login'));

    $response->assertOk()
        ->assertJsonPath('component', 'auth/login')
        ->assertSessionHas('url.intended', $previousUrl);
});

test('authenticated login route returns the home page instead of login', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withHeaders(controllerCoverageInertiaHeaders())
        ->get(route('login'));

    $response->assertOk()
        ->assertJsonPath('component', 'home');
});

test('logout invalidates the session and redirects back to the previous page', function () {
    $user = User::factory()->create();
    $previousUrl = route('games.index');

    $response = $this
        ->actingAs($user)
        ->withHeader('Referer', $previousUrl)
        ->post(route('logout'));

    $response->assertRedirect($previousUrl);
    $this->assertGuest();
});

test('editor image upload returns controller level authentication error without a user', function () {
    Auth::logout();

    $request = Request::create(route('browser-api.upload-editor-image'), 'POST');
    $response = app(EditorUploadController::class)->uploadEditorImage($request);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'message' => 'Authentication required.',
        ]);
});

test('editor image upload validates files and enforces game edit permission', function () {
    Storage::fake('public');

    $editor = User::factory()->create(['is_admin' => true]);
    $otherUser = User::factory()->create(['is_admin' => false]);
    $game = Game::factory()->create(['slug' => 'editable-upload-game']);

    $this
        ->actingAs($editor)
        ->postJson(route('browser-api.upload-editor-image'), [
            'game_id' => $game->id,
            'file' => UploadedFile::fake()->create('not-an-image.txt', 1, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this
        ->actingAs($otherUser)
        ->postJson(route('browser-api.upload-editor-image'), [
            'game_id' => $game->id,
            'file' => UploadedFile::fake()->image('blocked.png', 320, 180),
        ])
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

test('editor image upload stores images for users who can edit the game', function () {
    Storage::fake('public');

    $editor = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create(['slug' => 'image-upload-game']);

    $response = $this
        ->actingAs($editor)
        ->postJson(route('browser-api.upload-editor-image'), [
            'game_id' => $game->id,
            'file' => UploadedFile::fake()->image('screenshot.png', 320, 180),
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJson(fn ($json) => $json->whereType('location', 'string')->etc());

    $location = $response->json('location');
    expect($location)->toContain("/storage/editor/{$game->id}/");

    $storedPath = str_replace('/storage/', '', parse_url($location, PHP_URL_PATH));
    Storage::disk('public')->assertExists($storedPath);
});

test('new games feed includes only visible public games ordered by first visibility', function () {
    $newest = Game::factory()->create([
        'name' => 'Newest Feed VN',
        'slug' => 'newest-feed-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
        'first_visible_at' => now(),
    ]);
    $older = Game::factory()->create([
        'name' => 'Older Feed VN',
        'slug' => 'older-feed-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
        'first_visible_at' => now()->subDay(),
    ]);
    Game::factory()->create([
        'name' => 'Hidden Feed VN',
        'slug' => 'hidden-feed-vn',
        'is_visible' => false,
        'content_type' => 'visual_novel',
        'first_visible_at' => now()->addDay(),
    ]);
    Game::factory()->create([
        'name' => 'Adjacent Feed Game',
        'slug' => 'adjacent-feed-game',
        'is_visible' => true,
        'content_type' => 'adjacent',
        'first_visible_at' => now()->addDay(),
    ]);

    $response = $this->get(route('feed.new'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
        ->assertSee('FVN.li - New Visual Novels', false)
        ->assertSee($newest->name, false)
        ->assertSee($older->name, false)
        ->assertDontSee('Hidden Feed VN', false)
        ->assertDontSee('Adjacent Feed Game', false);

    expect($response->getContent())->toContain($newest->slug)
        ->and(strpos($response->getContent(), $newest->name))->toBeLessThan(strpos($response->getContent(), $older->name));
});

test('updated games feed includes games with latest versions ordered by published date', function () {
    $freshGame = Game::factory()->create([
        'name' => 'Fresh Updated VN',
        'slug' => 'fresh-updated-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
    ]);
    $oldGame = Game::factory()->create([
        'name' => 'Old Updated VN',
        'slug' => 'old-updated-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
    ]);
    $noVersion = Game::factory()->create([
        'name' => 'No Version VN',
        'slug' => 'no-version-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
    ]);

    GameVersion::factory()->latest()->create([
        'game_id' => $freshGame->id,
        'published_at' => now(),
    ]);
    GameVersion::factory()->latest()->create([
        'game_id' => $oldGame->id,
        'published_at' => now()->subWeek(),
    ]);

    $response = $this->get(route('feed.updates'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
        ->assertSee('FVN.li - Updated Visual Novels', false)
        ->assertSee($freshGame->name, false)
        ->assertSee($oldGame->name, false)
        ->assertDontSee($noVersion->name, false);

    expect(strpos($response->getContent(), $freshGame->name))->toBeLessThan(strpos($response->getContent(), $oldGame->name));
});

test('game reviews endpoint filters visible reviews, sanitizes text, and exposes available ratings', function () {
    $game = Game::factory()->create();
    $rater = Rater::factory()->create(['name' => 'External Reviewer']);
    $user = User::factory()->create(['name' => 'Site Reviewer']);

    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => '<p>Great VN.</p><script>alert("x")</script>',
        'is_visible' => true,
        'is_reviewed' => true,
        'published_at' => now(),
        'event_id' => 1,
        'source_platform' => 'itch_io',
    ]);
    Rating::create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'rating' => 3,
        'review' => '',
        'is_visible' => true,
        'is_reviewed' => false,
        'published_at' => now()->subDay(),
        'event_id' => 2,
        'source_platform' => 'fvn_li',
    ]);
    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 1,
        'review' => 'Hidden text',
        'is_visible' => false,
        'is_reviewed' => true,
        'published_at' => now()->subDays(2),
        'event_id' => 3,
        'source_platform' => 'itch_io',
    ]);

    $this
        ->getJson(route('browser-api.games.reviews', [
            'game' => $game->id,
            'perPage' => 10,
        ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'reviews.data')
        ->assertJsonPath('reviews.data.0.rating', 5)
        ->assertJsonPath('availableRatings', [5])
        ->assertJsonMissing(['review' => '<script>alert("x")</script>']);

    $this
        ->getJson(route('browser-api.games.reviews', [
            'game' => $game->id,
            'showAllRatings' => 'true',
            'selectedRating' => 3,
            'perPage' => 10,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'reviews.data')
        ->assertJsonPath('reviews.data.0.rating', 3)
        ->assertJsonPath('availableRatings', [3, 5]);
});

test('game reviews endpoint validates filter parameters', function () {
    $game = Game::factory()->create();

    $this
        ->getJson(route('browser-api.games.reviews', [
            'game' => $game->id,
            'selectedRating' => 6,
            'perPage' => 500,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['selectedRating', 'perPage']);
});
