<?php

use App\Http\Controllers\EditorUploadController;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
