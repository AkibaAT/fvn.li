<?php

declare(strict_types=1);

use App\Filament\Resources\Games\Pages\EditGame;
use App\Models\Game;
use App\Models\User;
use Livewire\Livewire;

test('admin can hide a game by moderation with a note', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create(['is_visible' => true, 'itch_id' => 39096]);

    Livewire::actingAs($admin)
        ->test(EditGame::class, ['record' => $game->getRouteKey()])
        ->set('data.is_moderation_hidden', true)
        ->set('data.moderation_note', 'Underage sexual content')
        ->call('save')
        ->assertHasNoErrors();

    expect($game->refresh())
        ->is_moderation_hidden->toBeTrue()
        ->is_visible->toBeFalse()
        ->moderation_note->toBe('Underage sexual content');
});

test('a moderation-hidden game cannot be made visible', function () {
    $game = Game::factory()->create(['is_visible' => false, 'is_moderation_hidden' => true]);

    $game->update(['is_visible' => true]);

    expect($game->refresh()->is_visible)->toBeFalse();
});
