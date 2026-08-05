<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Policies\UserGameProgressPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new UserGameProgressPolicy;
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->game = Game::factory()->create();
});

describe('update policy', function () {
    test('allows user to update their own game progress', function () {
        $progress = UserGameProgress::factory()->create([
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
        ]);

        expect($this->policy->update($this->user, $progress))->toBeTrue();
    });

    test('prevents user from updating other users game progress', function () {
        $progress = UserGameProgress::factory()->create([
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
        ]);

        expect($this->policy->update($this->otherUser, $progress))->toBeFalse();
    });
});
