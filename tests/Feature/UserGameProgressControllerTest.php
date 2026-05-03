<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use App\Models\UserGameProgress;

it('requires authentication before updating game progress', function () {
    $game = Game::factory()->create();

    $this->putJson(route('user-progress.update', $game), [
        'status' => 'reading',
    ])->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

it('creates and updates progress for the authenticated user', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->latest()->create();

    $this->actingAs($user)
        ->putJson(route('user-progress.update', $game), [
            'game_version_id' => $version->id,
            'status' => 'reading',
            'personal_notes' => 'Started the common route.',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Game progress updated successfully')
        ->assertJsonPath('progress.user_id', $user->id)
        ->assertJsonPath('progress.game_id', $game->id)
        ->assertJsonPath('progress.game_version_id', $version->id)
        ->assertJsonPath('progress.status', 'reading')
        ->assertJsonPath('progress.personal_notes', 'Started the common route.');

    $progress = UserGameProgress::where('user_id', $user->id)
        ->where('game_id', $game->id)
        ->firstOrFail();

    expect($progress->game_version_id)->toBe($version->id)
        ->and($progress->status)->toBe('reading')
        ->and($progress->personal_notes)->toBe('Started the common route.');

    $this->actingAs($user)
        ->putJson(route('user-progress.update', $game), [
            'personal_notes' => 'Only the notes changed.',
        ])
        ->assertOk()
        ->assertJsonPath('progress.id', $progress->id)
        ->assertJsonPath('progress.status', 'reading')
        ->assertJsonPath('progress.personal_notes', 'Only the notes changed.');

    expect($progress->fresh()->status)->toBe('reading')
        ->and($progress->fresh()->personal_notes)->toBe('Only the notes changed.');
});

it('rejects a version from another game without writing progress', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $otherGame = Game::factory()->create();
    $otherVersion = GameVersion::factory()->for($otherGame)->latest()->create();

    $this->actingAs($user)
        ->putJson(route('user-progress.update', $game), [
            'game_version_id' => $otherVersion->id,
            'status' => 'completed',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'The specified game version does not belong to this game',
        ]);

    expect(UserGameProgress::where('user_id', $user->id)->where('game_id', $game->id)->exists())
        ->toBeFalse();
});

it('validates progress updates', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $this->actingAs($user)
        ->putJson(route('user-progress.update', $game), [
            'game_version_id' => 999999,
            'status' => 'finished',
            'personal_notes' => str_repeat('x', 1001),
            'progress' => 101,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'game_version_id',
            'status',
            'personal_notes',
            'progress',
        ]);
});
