<?php

use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function myGamesInertiaHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
    ];
}

test('my games index lists games from linked itch io account game ids', function () {
    $user = User::factory()->create();
    $ownedGame = Game::factory()->create([
        'itch_id' => 12345,
        'name' => 'Owned VN',
        'slug' => 'owned-vn',
        'is_visible' => true,
        'url' => ['itch_io' => 'https://linkeddev.itch.io/owned-vn'],
    ]);
    Game::factory()->create([
        'itch_id' => 67890,
        'name' => 'Other VN',
        'slug' => 'other-vn',
        'is_visible' => true,
        'url' => ['itch_io' => 'https://otherdev.itch.io/other-vn'],
    ]);

    SocialAccount::factory()->create([
        'user_id' => $user->id,
        'provider_name' => 'itchio',
        'provider_data' => [
            'username' => 'linkeddev',
            'url' => 'https://linkeddev.itch.io',
        ],
        'itchio_game_ids' => [12345],
    ]);

    $response = $this
        ->actingAs($user)
        ->withHeaders(myGamesInertiaHeaders())
        ->get(route('my-games.index'));

    $response->assertOk()
        ->assertJsonPath('component', 'my-games/index')
        ->assertJsonPath('props.itchio.username', 'linkeddev')
        ->assertJsonCount(1, 'props.games')
        ->assertJsonPath('props.games.0.id', $ownedGame->id)
        ->assertJsonPath('props.games.0.name', 'Owned VN')
        ->assertJsonPath('props.games.0.slug', 'owned-vn');
});

test('my games index lists games by itch io url fallback when game ids are unavailable', function () {
    $user = User::factory()->create();
    $ownedGame = Game::factory()->create([
        'name' => 'Fallback Owned VN',
        'slug' => 'fallback-owned-vn',
        'is_visible' => true,
        'url' => ['itch_io' => 'https://fallbackdev.itch.io/fallback-owned-vn'],
    ]);
    Game::factory()->create([
        'name' => 'Hidden Owned VN',
        'slug' => 'hidden-owned-vn',
        'is_visible' => false,
        'url' => ['itch_io' => 'https://fallbackdev.itch.io/hidden-owned-vn'],
    ]);

    SocialAccount::factory()->create([
        'user_id' => $user->id,
        'provider_name' => 'itchio',
        'provider_data' => [
            'username' => 'fallbackdev',
            'url' => 'https://fallbackdev.itch.io',
        ],
        'itchio_game_ids' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->withHeaders(myGamesInertiaHeaders())
        ->get(route('my-games.index'));

    $response->assertOk()
        ->assertJsonPath('props.itchio.username', 'fallbackdev')
        ->assertJsonCount(1, 'props.games')
        ->assertJsonPath('props.games.0.id', $ownedGame->id)
        ->assertJsonPath('props.games.0.name', 'Fallback Owned VN');
});

test('my games index prompts unlinked users to connect itch io account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withHeaders(myGamesInertiaHeaders())
        ->get(route('my-games.index'));

    $response->assertOk()
        ->assertJsonPath('component', 'my-games/index')
        ->assertJsonPath('props.itchio.username', null)
        ->assertJsonCount(0, 'props.games');
});

test('my games edit renders editable game data and stats for authorized users', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'name' => 'Editable VN',
        'slug' => 'editable-vn',
        'additional_links' => [
            [
                'id' => 'link-1',
                'name' => 'Windows build',
                'url' => 'https://downloads.example/windows.zip',
                'platform' => 'windows',
            ],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->withHeaders(myGamesInertiaHeaders())
        ->get(route('my-games.edit', $game));

    $response->assertOk()
        ->assertJsonPath('component', 'my-games/edit')
        ->assertJsonPath('props.game.id', $game->id)
        ->assertJsonPath('props.game.name', 'Editable VN')
        ->assertJsonPath('props.game.additional_links.0.id', 'link-1')
        ->assertJsonPath('props.platforms.0', 'windows');
});

test('my games edit blocks users who do not own the game', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $game = Game::factory()->create();

    $this
        ->actingAs($user)
        ->withHeaders(myGamesInertiaHeaders())
        ->get(route('my-games.edit', $game))
        ->assertForbidden();
});

test('can update additional links and normalize release dates', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'additional_links' => [
            [
                'id' => 'existing-link',
                'name' => 'Old Name',
                'url' => 'https://downloads.example/old.zip',
                'platform' => 'windows',
                'release_at' => '2026-05-01T10:00:00.000000Z',
                'last_edited_at' => '2026-05-01T10:00:00.000000Z',
            ],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('browser-api.my-games.update', $game), [
            'timezone_offset' => 2,
            'links' => [
                [
                    'id' => 'existing-link',
                    'name' => ' New Name ',
                    'url' => 'https://downloads.example/new.zip ',
                    'platform' => 'linux',
                    'release_at' => '2026-06-02T14:30',
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('links.0.id', 'existing-link')
        ->assertJsonPath('links.0.name', 'New Name')
        ->assertJsonPath('links.0.url', 'https://downloads.example/new.zip')
        ->assertJsonPath('links.0.platform', 'linux')
        ->assertJsonCount(1, 'links');

    $game->refresh();
    $links = $game->getAllAdditionalLinks();
    expect($links)->toHaveCount(1)
        ->and($links[0]['release_at'])->toBe('2026-06-02T12:30:00.000000Z')
        ->and($links[0]['last_edited_at'])->not->toBe('2026-05-01T10:00:00.000000Z');
});

test('keeps additional link edit timestamps when submitted links are unchanged', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'additional_links' => [
            [
                'id' => 'existing-link',
                'name' => 'Same Name',
                'url' => 'https://downloads.example/same.zip',
                'platform' => 'windows',
                'release_at' => null,
                'last_edited_at' => '2026-05-01T10:00:00.000000Z',
            ],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('browser-api.my-games.update', $game), [
            'links' => [
                [
                    'id' => 'existing-link',
                    'name' => 'Same Name',
                    'url' => 'https://downloads.example/same.zip',
                    'platform' => 'windows',
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('links.0.last_edited_at', '2026-05-01T10:00:00.000000Z');

    $game->refresh();
    expect($game->additional_links[0]['last_edited_at'])->toBe('2026-05-01T10:00:00.000000Z');
});

test('rejects unsafe additional link urls and impossible release dates', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create();

    $this
        ->actingAs($user)
        ->putJson(route('browser-api.my-games.update', $game), [
            'links' => [
                [
                    'name' => 'Localhost',
                    'url' => 'javascript://example.com/%0Aalert(1)',
                    'release_at' => now()->addYears(11)->format('Y-m-d\TH:i'),
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['links.0.url', 'links.0.release_at']);
});

test('rejects localhost additional link urls', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create();

    $this
        ->actingAs($user)
        ->putJson(route('browser-api.my-games.update', $game), [
            'links' => [
                [
                    'name' => 'Localhost',
                    'url' => 'http://localhost/build.zip',
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['links.0.url']);
});

test('blocks link updates for users without edit rights', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $game = Game::factory()->create();

    $this
        ->actingAs($user)
        ->putJson(route('browser-api.my-games.update', $game), [
            'links' => [
                [
                    'name' => 'Build',
                    'url' => 'https://downloads.example/build.zip',
                ],
            ],
        ])
        ->assertForbidden()
        ->assertJson(['success' => false, 'message' => 'Forbidden']);
});

test('can delete thumbnail when authenticated and authorized', function () {
    Storage::fake('public');

    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'thumb_url' => 'https://example.com/thumb.jpg',
        'optimized_thumbnails' => [
            'default' => ['path' => "games/{$user->id}/thumbnails/default_test.webp"],
            'large' => ['path' => "games/{$user->id}/thumbnails/large_test.webp"],
        ],
    ]);
    Storage::disk('public')->put("games/{$user->id}/thumbnails/default_test.webp", 'default');
    Storage::disk('public')->put("games/{$user->id}/thumbnails/large_test.webp", 'large');

    $response = $this->actingAs($user)->deleteJson("/browser-api/my-games/{$game->slug}/thumbnail");

    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Thumbnail deleted successfully.']);

    $game->refresh();
    expect($game->thumb_url)->toBeNull();
    expect($game->optimized_thumbnails)->toBeNull();
    Storage::disk('public')->assertMissing("games/{$user->id}/thumbnails/default_test.webp");
    Storage::disk('public')->assertMissing("games/{$user->id}/thumbnails/large_test.webp");
});

test('cannot delete thumbnail when unauthenticated', function () {
    $game = Game::factory()->create();

    $response = $this->deleteJson("/browser-api/my-games/{$game->slug}/thumbnail");

    $response->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('cannot delete thumbnail when unauthorized', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $game = Game::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/browser-api/my-games/{$game->slug}/thumbnail");

    $response->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'Forbidden']);
});

test('can upload thumbnail when authenticated and authorized', function () {
    Storage::fake('public');

    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'thumb_url' => null,
        'optimized_thumbnails' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson("/browser-api/my-games/{$game->slug}/thumbnail", [
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg', 630, 500),
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Thumbnail updated successfully.',
        ])
        ->assertJsonStructure([
            'thumbnail_url',
            'optimized_thumbnails' => [
                'small' => ['path', 'width', 'height', 'size'],
                'default' => ['path', 'width', 'height', 'size'],
                'large' => ['path', 'width', 'height', 'size'],
            ],
        ]);

    $game->refresh();

    expect($game->thumb_url)->toContain("/storage/games/{$game->id}/thumbnails/")
        ->and($game->optimized_thumbnails)->toHaveKeys(['small', 'default', 'large'])
        ->and($game->custom_page_updated_by)->toBe($user->id);
});

test('thumbnail uploads return json validation and authorization errors', function () {
    $game = Game::factory()->create();

    $this
        ->postJson("/browser-api/my-games/{$game->slug}/thumbnail", [
            'thumbnail' => UploadedFile::fake()->create('not-an-image.txt', 1, 'text/plain'),
        ])
        ->assertUnauthorized();

    $this
        ->actingAs(User::factory()->create(['is_admin' => false]))
        ->postJson("/browser-api/my-games/{$game->slug}/thumbnail", [
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg'),
        ])
        ->assertForbidden()
        ->assertJson(['success' => false, 'message' => 'Forbidden']);

    $this
        ->actingAs(User::factory()->create(['is_admin' => true]))
        ->postJson("/browser-api/my-games/{$game->slug}/thumbnail", [
            'thumbnail' => UploadedFile::fake()->create('not-an-image.txt', 1, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.thumbnail.0', 'The thumbnail field must be an image.');
});

test('can delete gallery screenshot with json payload', function () {
    Storage::fake('public');

    $user = User::factory()->create(['is_admin' => true]);
    Storage::disk('public')->put('games/1/screenshots/first.jpg', 'original');
    Storage::disk('public')->put('games/1/screenshots/first-thumb.jpg', 'thumb');
    Storage::disk('public')->put('games/1/screenshots/first-default.webp', 'default');
    $game = Game::factory()->create([
        'custom_screenshots' => [
            [
                'url' => asset('storage/games/1/screenshots/first.jpg'),
                'thumbnail_url' => asset('storage/games/1/screenshots/first-thumb.jpg'),
                'optimized' => [
                    'default' => ['path' => 'games/1/screenshots/first-default.webp'],
                ],
                'uploaded_at' => now()->toISOString(),
            ],
            [
                'url' => 'https://example.com/second.jpg',
                'thumbnail_url' => 'https://example.com/second-thumb.jpg',
            ],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson("/browser-api/my-games/{$game->slug}/screenshots", ['index' => 0]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Screenshot deleted successfully.',
        ])
        ->assertJsonCount(1, 'screenshots');

    $game->refresh();
    expect($game->custom_screenshots)->toHaveCount(1);
    expect($game->custom_screenshots[0]['url'])->toBe('https://example.com/second.jpg');
    expect(array_is_list($response->json('screenshots')))->toBeTrue();
    expect(ltrim($response->getContent()))->toStartWith('{');
    expect($response->getContent())->not->toContain('[Observer]');
    Storage::disk('public')->assertMissing('games/1/screenshots/first.jpg');
    Storage::disk('public')->assertMissing('games/1/screenshots/first-thumb.jpg');
    Storage::disk('public')->assertMissing('games/1/screenshots/first-default.webp');
});

test('can upload gallery screenshot and returns list payloads', function () {
    Storage::fake('public');

    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'custom_screenshots' => [
            [
                'url' => 'https://example.com/existing.jpg',
                'thumbnail_url' => 'https://example.com/existing-thumb.jpg',
            ],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson("/browser-api/my-games/{$game->slug}/screenshots", [
            'screenshots' => [
                UploadedFile::fake()->image('new-screenshot.jpg', 640, 360),
            ],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Screenshots uploaded successfully.',
        ])
        ->assertJsonCount(2, 'screenshots')
        ->assertJsonCount(1, 'new_screenshots');

    expect(array_is_list($response->json('screenshots')))->toBeTrue();
    expect(array_is_list($response->json('new_screenshots')))->toBeTrue();
    expect(ltrim($response->getContent()))->toStartWith('{');
    expect($response->getContent())->not->toContain('[Observer]');
});

test('gallery screenshot upload and delete enforce auth and validation errors', function () {
    $game = Game::factory()->create([
        'custom_screenshots' => [
            ['url' => 'https://example.com/first.jpg'],
        ],
    ]);

    $this
        ->postJson(route('browser-api.my-games.screenshots.upload', $game), [
            'screenshots' => [UploadedFile::fake()->image('screenshot.jpg')],
        ])
        ->assertUnauthorized();

    $this
        ->deleteJson(route('browser-api.my-games.screenshots.delete', $game), ['index' => 0])
        ->assertUnauthorized();

    $this
        ->actingAs(User::factory()->create(['is_admin' => false]))
        ->postJson(route('browser-api.my-games.screenshots.upload', $game), [
            'screenshots' => [UploadedFile::fake()->image('screenshot.jpg')],
        ])
        ->assertForbidden()
        ->assertJson(['success' => false, 'message' => 'Forbidden']);

    $this
        ->actingAs(User::factory()->create(['is_admin' => true]))
        ->postJson(route('browser-api.my-games.screenshots.upload', $game), [
            'screenshots' => [UploadedFile::fake()->create('bad.txt', 1, 'text/plain')],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['screenshots.0']);
});

test('returns not found when deleting missing gallery screenshot', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'custom_screenshots' => [
            ['url' => 'https://example.com/first.jpg'],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson("/browser-api/my-games/{$game->slug}/screenshots", ['index' => 3]);

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Screenshot not found.',
        ]);
});

test('can reorder gallery screenshots and discards invalid indices', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'custom_screenshots' => [
            ['url' => 'https://example.com/first.jpg'],
            ['url' => 'https://example.com/second.jpg'],
            ['url' => 'https://example.com/third.jpg'],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('browser-api.my-games.screenshots.reorder', $game), [
            'ordered_indices' => [2, 0, 99],
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Screenshots reordered successfully.')
        ->assertJsonCount(2, 'screenshots')
        ->assertJsonPath('screenshots.0.url', 'https://example.com/third.jpg')
        ->assertJsonPath('screenshots.1.url', 'https://example.com/first.jpg');

    $game->refresh();
    expect($game->custom_screenshots)->toHaveCount(2)
        ->and($game->custom_screenshots[0]['url'])->toBe('https://example.com/third.jpg')
        ->and($game->custom_page_updated_by)->toBe($user->id);
});

test('reorder screenshots validates payloads and enforces edit rights', function () {
    $game = Game::factory()->create([
        'custom_screenshots' => [
            ['url' => 'https://example.com/first.jpg'],
        ],
    ]);

    $this
        ->postJson(route('browser-api.my-games.screenshots.reorder', $game), [
            'ordered_indices' => [0],
        ])
        ->assertUnauthorized();

    $this
        ->actingAs(User::factory()->create(['is_admin' => true]))
        ->postJson(route('browser-api.my-games.screenshots.reorder', $game), [
            'ordered_indices' => ['bad'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ordered_indices.0']);

    $this
        ->actingAs(User::factory()->create(['is_admin' => false]))
        ->postJson(route('browser-api.my-games.screenshots.reorder', $game), [
            'ordered_indices' => [0],
        ])
        ->assertForbidden()
        ->assertJson(['success' => false, 'message' => 'Forbidden']);
});
