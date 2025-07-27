<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('authenticated user can upload image to editor', function () {
    // Create user without specifying ID to avoid conflicts with system user (ID 1)
    $user = User::factory()->create([
        'email' => 'test-upload@example.com', // Use unique email
    ]);

    // Create a test game
    $game = Game::factory()->create();

    // Create a fake image file
    $file = UploadedFile::fake()->image('test-image.jpg', 800, 600);

    $response = $this->actingAs($user)
        ->post('/api/upload-editor-image', [
            'file' => $file,
            'game_id' => $game->id,
        ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['location']);

    // Verify the file was stored
    $responseData = $response->json();
    $location = $responseData['location'];

    // Extract the path from the asset URL
    $path = str_replace(asset('storage/'), '', $location);

    expect(Storage::disk('public')->exists($path))->toBeTrue();
    expect($path)->toStartWith("editor-uploads/{$game->id}/");
});

test('unauthenticated user cannot upload image to editor', function () {
    $file = UploadedFile::fake()->image('test-image.jpg', 800, 600);

    $response = $this->post('/api/upload-editor-image', [
        'file' => $file,
    ]);

    $response->assertRedirect('/login');
});

test('upload validates file is an image', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    // Create a fake non-image file
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($user)
        ->post('/api/upload-editor-image', [
            'file' => $file,
            'game_id' => $game->id,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'error' => 'Validation failed',
        'errors' => [
            'file' => ['The file field must be an image.'],
        ],
    ]);
});

test('upload validates file size limit', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    // Create a fake image file larger than 10MB
    $file = UploadedFile::fake()->image('large-image.jpg')->size(11000); // 11MB

    $response = $this->actingAs($user)
        ->post('/api/upload-editor-image', [
            'file' => $file,
            'game_id' => $game->id,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'error' => 'Validation failed',
        'errors' => [
            'file' => ['The file field must not be greater than 10240 kilobytes.'],
        ],
    ]);
});

test('upload requires file parameter', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api/upload-editor-image', [
            'game_id' => $game->id,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'error' => 'Validation failed',
        'errors' => [
            'file' => ['The file field is required.'],
        ],
    ]);
});

test('upload requires game_id parameter', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test-image.jpg', 800, 600);

    $response = $this->actingAs($user)
        ->post('/api/upload-editor-image', [
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'error' => 'Validation failed',
        'errors' => [
            'game_id' => ['The game id field is required.'],
        ],
    ]);
});

test('can list uploaded images for a game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    // Upload a test image first
    $file = UploadedFile::fake()->image('test-image.jpg', 800, 600);
    $uploadResponse = $this->actingAs($user)
        ->post('/api/upload-editor-image', [
            'file' => $file,
            'game_id' => $game->id,
        ]);

    $uploadResponse->assertStatus(200);

    // Now list the images
    $listResponse = $this->actingAs($user)
        ->get("/api/editor-images?game_id={$game->id}");

    $listResponse->assertStatus(200);
    $listResponse->assertJsonStructure([
        'images' => [
            '*' => [
                'url',
                'path',
                'name',
                'size',
                'modified',
                'type',
            ],
        ],
    ]);

    $images = $listResponse->json('images');
    expect(count($images))->toBe(1);
    expect($images[0]['type'])->toBe('editor');
    expect($images[0]['path'])->toStartWith("editor-uploads/{$game->id}/");
});

test('can delete uploaded image', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    // Upload a test image first
    $file = UploadedFile::fake()->image('test-image.jpg', 800, 600);
    $uploadResponse = $this->actingAs($user)
        ->post('/api/upload-editor-image', [
            'file' => $file,
            'game_id' => $game->id,
        ]);

    $uploadResponse->assertStatus(200);
    $uploadData = $uploadResponse->json();
    $imagePath = str_replace(asset('storage/'), '', $uploadData['location']);

    // Verify file exists
    expect(Storage::disk('public')->exists($imagePath))->toBeTrue();

    // Delete the image
    $encodedPath = urlencode($imagePath);
    $deleteResponse = $this->actingAs($user)
        ->delete("/api/editor-images/{$encodedPath}?game_id={$game->id}");

    $deleteResponse->assertStatus(200);
    $deleteResponse->assertJson(['message' => 'File deleted successfully']);

    // Verify file no longer exists
    expect(Storage::disk('public')->exists($imagePath))->toBeFalse();
});

test('cannot delete image from different game', function () {
    $user = User::factory()->create();
    $game1 = Game::factory()->create();
    $game2 = Game::factory()->create();

    // Upload image to game1
    $file = UploadedFile::fake()->image('test-image.jpg', 800, 600);
    $uploadResponse = $this->actingAs($user)
        ->post('/api/upload-editor-image', [
            'file' => $file,
            'game_id' => $game1->id,
        ]);

    $uploadResponse->assertStatus(200);
    $uploadData = $uploadResponse->json();
    $imagePath = str_replace(asset('storage/'), '', $uploadData['location']);

    // Try to delete using game2's ID (should fail due to authorization)
    $encodedPath = urlencode($imagePath);
    $deleteResponse = $this->actingAs($user)
        ->delete("/api/editor-images/{$encodedPath}?game_id={$game2->id}");

    $deleteResponse->assertStatus(403);
    $deleteResponse->assertJson(['error' => 'You do not have permission to delete images for this game']);

    // Verify file still exists
    expect(Storage::disk('public')->exists($imagePath))->toBeTrue();
});

test('delete returns 404 for non-existent file', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $fakePath = "editor-uploads/{$game->id}/non-existent-file.jpg";
    $encodedPath = urlencode($fakePath);

    $deleteResponse = $this->actingAs($user)
        ->delete("/api/editor-images/{$encodedPath}?game_id={$game->id}");

    $deleteResponse->assertStatus(404);
    $deleteResponse->assertJson(['error' => 'File not found']);
});
