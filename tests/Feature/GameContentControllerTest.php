<?php

use App\Models\Game;
use App\Models\User;
use App\Services\GameDataSyncService;
use Illuminate\Support\Facades\Storage;

test('saving a name in original visitor mode keeps editing the custom name', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'name' => 'Original itch.io Name',
        'has_custom_page' => true,
        'view_mode' => 'original',
        'custom_name' => 'Existing Custom Name',
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('browser-api.games.name.update', $game), [
            'name' => 'Edited Custom Name',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Edited Custom Name')
        ->assertJsonPath('data.effective_name', 'Original itch.io Name');

    $game->refresh();

    expect($game->custom_name)->toBe('Edited Custom Name')
        ->and($game->view_mode)->toBe('original')
        ->and($game->getEffectiveName())->toBe('Original itch.io Name');
});

test('saving content in original visitor mode keeps editing the custom description', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'full_description' => '<p>Original itch.io text</p>',
        'has_custom_page' => true,
        'view_mode' => 'original',
        'custom_description' => '<p>Existing custom text</p>',
    ]);
    $content = '<p>Edited custom text <img src="x" onerror="alert(1)"></p>';

    $response = $this
        ->actingAs($user)
        ->putJson(route('browser-api.games.content.update', $game), [
            'content' => $content,
        ]);

    $response->assertOk();

    expect($response->json('data.content'))
        ->toContain('<img')
        ->not->toContain('onerror');

    $game->refresh();

    expect($game->custom_description)->toBe($content)
        ->and($game->view_mode)->toBe('original')
        ->and($game->getEffectiveDescription())->toBe('<p>Original itch.io text</p>');
});

test('saving content enables custom page and removes unused editor images', function () {
    Storage::fake('public');
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'full_description' => '<p>Original text</p>',
        'has_custom_page' => false,
        'view_mode' => 'original',
    ]);

    Storage::disk('public')->put("editor/{$game->id}/used.png", 'used');
    Storage::disk('public')->put("editor/{$game->id}/nested/background.webp", 'background');
    Storage::disk('public')->put("editor/{$game->id}/unused.jpg", 'unused');
    Storage::disk('public')->put("editor/{$game->id}/notes.txt", 'not an image');

    $content = sprintf(
        '<p><img src="/storage/editor/%d/used.png"></p><div style="background-image: url(/storage/editor/%d/nested/background.webp)"></div>',
        $game->id,
        $game->id,
    );

    $response = $this
        ->actingAs($user)
        ->putJson(route('browser-api.games.content.update', $game), [
            'content' => $content,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.has_custom_page', true);

    expect($response->json('data.content'))
        ->toContain('<img')
        ->not->toContain('background-image');

    Storage::disk('public')->assertExists("editor/{$game->id}/used.png");
    Storage::disk('public')->assertExists("editor/{$game->id}/nested/background.webp");
    Storage::disk('public')->assertMissing("editor/{$game->id}/unused.jpg");
    Storage::disk('public')->assertExists("editor/{$game->id}/notes.txt");

    $game->refresh();
    expect($game->has_custom_page)->toBeTrue()
        ->and($game->custom_description)->toBe($content)
        ->and($game->custom_page_updated_by)->toBe($user->id);
});

test('content and name update endpoints validate required payloads', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create();

    $this
        ->actingAs($user)
        ->putJson(route('browser-api.games.content.update', $game), [
            'content' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    $this
        ->actingAs($user)
        ->putJson(route('browser-api.games.name.update', $game), [
            'name' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('developer can fetch original custom and effective content for preview switching', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'name' => 'Original itch.io Name',
        'full_description' => '<p>Original itch.io text</p>',
        'has_custom_page' => true,
        'view_mode' => 'custom',
        'custom_name' => 'Custom Name',
        'custom_description' => '<p>Custom text <img src="x" onerror="alert(1)"></p>',
        'screenshots' => [
            ['url' => 'https://itch.example/original.jpg'],
        ],
        'custom_screenshots' => [
            ['url' => 'https://custom.example/custom.jpg'],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('browser-api.games.content.view', $game));

    $response->assertOk()
        ->assertJsonPath('data.current_view_mode', 'custom')
        ->assertJsonPath('data.original_content.name', 'Original itch.io Name')
        ->assertJsonPath('data.original_content.description', '<p>Original itch.io text</p>')
        ->assertJsonPath('data.custom_content.name', 'Custom Name')
        ->assertJsonPath('data.effective_content.name', 'Custom Name');

    expect($response->json('data.custom_content.description'))
        ->toContain('<img')
        ->not->toContain('onerror')
        ->and($response->json('data.effective_content.description'))
        ->toContain('<img')
        ->not->toContain('onerror');
});

test('developer can switch visitor mode between itch io and custom content', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'name' => 'Original itch.io Name',
        'full_description' => '<p>Original itch.io text</p>',
        'has_custom_page' => true,
        'view_mode' => 'original',
        'custom_name' => 'Custom Name',
        'custom_description' => '<p>Custom text <img src="x" onerror="alert(1)"></p>',
        'screenshots' => [
            ['url' => 'https://itch.example/original.jpg'],
        ],
        'custom_screenshots' => [
            ['url' => 'https://custom.example/custom.jpg'],
        ],
    ]);

    $customResponse = $this
        ->actingAs($user)
        ->putJson(route('browser-api.games.content.view-mode', $game), [
            'view_mode' => 'custom',
        ]);

    $customResponse->assertOk()
        ->assertJsonPath('data.view_mode', 'custom')
        ->assertJsonPath('data.effective_name', 'Custom Name')
        ->assertJsonPath('data.effective_screenshots.0.url', 'https://custom.example/custom.jpg');

    expect($customResponse->json('data.effective_description'))
        ->toContain('<img')
        ->not->toContain('onerror');

    $originalResponse = $this
        ->actingAs($user)
        ->putJson(route('browser-api.games.content.view-mode', $game), [
            'view_mode' => 'original',
        ]);

    $originalResponse->assertOk()
        ->assertJsonPath('data.view_mode', 'original')
        ->assertJsonPath('data.effective_name', 'Original itch.io Name')
        ->assertJsonPath('data.effective_description', '<p>Original itch.io text</p>')
        ->assertJsonPath('data.effective_screenshots.0.url', 'https://itch.example/original.jpg');

    $game->refresh();
    expect($game->view_mode)->toBe('original')
        ->and($game->custom_name)->toBe('Custom Name')
        ->and($game->custom_description)->toBe('<p>Custom text <img src="x" onerror="alert(1)"></p>');
});

test('developer cannot switch visitor mode before custom page exists', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'has_custom_page' => false,
        'view_mode' => 'original',
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(route('browser-api.games.content.view-mode', $game), [
            'view_mode' => 'custom',
        ]);

    $response->assertBadRequest()
        ->assertJsonPath('message', 'Custom page must be enabled before changing view mode.');
});

test('developer partially reverts content name screenshots and cleans custom screenshot files', function () {
    Storage::fake('public');
    $user = User::factory()->create(['is_admin' => true]);
    $customScreenshotDirectory = uniqid();
    $game = Game::factory()->create([
        'name' => 'Original itch.io Name',
        'full_description' => '<p>Original itch.io text</p>',
        'has_custom_page' => true,
        'view_mode' => 'custom',
        'custom_name' => 'Custom Name',
        'custom_description' => '<p>Custom text <img src="/storage/editor/unused.png"></p>',
        'screenshots' => [
            ['url' => 'https://itch.example/original.jpg'],
        ],
        'custom_screenshots' => [
            [
                'url' => "/storage/games/{$customScreenshotDirectory}/screenshots/custom.jpg",
                'optimized' => [
                    'default' => ['path' => 'games/custom/screenshots/default.webp'],
                ],
            ],
        ],
    ]);
    $customPath = str_replace('/storage/', '', $game->custom_screenshots[0]['url']);
    Storage::disk('public')->put($customPath, 'custom image');
    Storage::disk('public')->put('games/custom/screenshots/default.webp', 'optimized');

    $response = $this
        ->actingAs($user)
        ->postJson(route('browser-api.games.content.revert', $game), [
            'revert_name' => true,
            'revert_screenshots' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Content and screenshots reverted to itch.io version successfully.')
        ->assertJsonPath('data.name', 'Original itch.io Name')
        ->assertJsonPath('data.content', '<p>Original itch.io text</p>')
        ->assertJsonPath('data.has_custom_page', true)
        ->assertJsonPath('data.screenshots.0.url', 'https://itch.example/original.jpg');

    Storage::disk('public')->assertMissing($customPath);
    Storage::disk('public')->assertMissing('games/custom/screenshots/default.webp');

    $game->refresh();
    expect($game->custom_name)->toBeNull()
        ->and($game->custom_description)->toBe('<p>Original itch.io text</p>')
        ->and($game->custom_screenshots[0]['url'])->toBe('https://itch.example/original.jpg');
});

test('developer fully reverts custom content and thumbnail through sync service adapter', function () {
    Storage::fake('public');
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'name' => 'Original itch.io Name',
        'full_description' => '<p>Original itch.io text</p>',
        'has_custom_page' => true,
        'view_mode' => 'custom',
        'custom_name' => 'Custom Name',
        'custom_description' => '<p>Custom text</p>',
        'thumb_url' => 'https://custom.example/thumb.jpg',
        'optimized_thumbnails' => [
            'default' => ['path' => 'games/thumbs/default.webp'],
        ],
        'screenshots' => [
            ['url' => 'https://itch.example/original.jpg'],
        ],
        'custom_screenshots' => [
            ['url' => '/storage/games/custom/screenshots/custom.jpg'],
        ],
    ]);

    $syncService = Mockery::mock(GameDataSyncService::class);
    $syncService
        ->shouldReceive('refreshBaseInfo')
        ->once()
        ->with(Mockery::on(function (Game $refreshedGame) use ($game) {
            $refreshedGame->update(['thumb_url' => 'https://itch.example/original-thumb.jpg']);

            return $refreshedGame->is($game);
        }));
    app()->instance(GameDataSyncService::class, $syncService);

    $response = $this
        ->actingAs($user)
        ->postJson(route('browser-api.games.content.revert', $game), [
            'revert_name' => true,
            'revert_screenshots' => true,
            'revert_thumbnail' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'All custom content has been removed. The game now shows original itch.io content.')
        ->assertJsonPath('data.has_custom_page', false)
        ->assertJsonPath('data.thumbnail_url', 'https://itch.example/original-thumb.jpg')
        ->assertJsonPath('data.name', 'Original itch.io Name');

    $game->refresh();
    expect($game->has_custom_page)->toBeFalse()
        ->and($game->custom_name)->toBeNull()
        ->and($game->custom_description)->toBeNull()
        ->and($game->custom_screenshots)->toBeNull()
        ->and($game->thumb_url)->toBe('https://itch.example/original-thumb.jpg');
});
