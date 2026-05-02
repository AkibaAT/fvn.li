<?php

use App\Models\Game;
use App\Models\User;

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
        ->putJson(route('react-api.games.name.update', $game), [
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

    $response = $this
        ->actingAs($user)
        ->putJson(route('react-api.games.content.update', $game), [
            'content' => '<p>Edited custom text</p>',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.content', '<p>Edited custom text</p>');

    $game->refresh();

    expect($game->custom_description)->toBe('<p>Edited custom text</p>')
        ->and($game->view_mode)->toBe('original')
        ->and($game->getEffectiveDescription())->toBe('<p>Original itch.io text</p>');
});
