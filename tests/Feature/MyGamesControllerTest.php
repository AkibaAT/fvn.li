<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('can delete thumbnail when authenticated and authorized', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'thumb_url' => 'https://example.com/thumb.jpg',
        'optimized_thumbnails' => [
            'default' => ['path' => 'games/1/thumbnails/default_test.webp'],
        ],
    ]);

    $response = $this->actingAs($user)->deleteJson("/react-api/my-games/{$game->slug}/thumbnail");

    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Thumbnail deleted successfully.']);

    $game->refresh();
    expect($game->thumb_url)->toBeNull();
    expect($game->optimized_thumbnails)->toBeNull();
});

test('cannot delete thumbnail when unauthenticated', function () {
    $game = Game::factory()->create();

    $response = $this->deleteJson("/react-api/my-games/{$game->slug}/thumbnail");

    $response->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('cannot delete thumbnail when unauthorized', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $game = Game::factory()->create();

    $response = $this->actingAs($user)->deleteJson("/react-api/my-games/{$game->slug}/thumbnail");

    $response->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'Forbidden']);
});

test('can delete gallery screenshot with json payload', function () {
    Storage::fake('public');

    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'custom_screenshots' => [
            [
                'url' => asset('storage/games/1/screenshots/first.jpg'),
                'thumbnail_url' => asset('storage/games/1/screenshots/first.jpg'),
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
        ->deleteJson("/react-api/my-games/{$game->slug}/screenshots", ['index' => 0]);

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
        ->postJson("/react-api/my-games/{$game->slug}/screenshots", [
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

test('returns not found when deleting missing gallery screenshot', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'custom_screenshots' => [
            ['url' => 'https://example.com/first.jpg'],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson("/react-api/my-games/{$game->slug}/screenshots", ['index' => 3]);

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Screenshot not found.',
        ]);
});
