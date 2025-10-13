<?php

use App\Models\Game;
use App\Models\User;

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
