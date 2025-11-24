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

    test('handles multiple progress records for same game', function () {
        $userProgress = UserGameProgress::factory()->create([
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
        ]);

        $otherUserProgress = UserGameProgress::factory()->create([
            'user_id' => $this->otherUser->id,
            'game_id' => $this->game->id,
        ]);

        expect($this->policy->update($this->user, $userProgress))->toBeTrue()
            ->and($this->policy->update($this->user, $otherUserProgress))->toBeFalse()
            ->and($this->policy->update($this->otherUser, $userProgress))->toBeFalse()
            ->and($this->policy->update($this->otherUser, $otherUserProgress))->toBeTrue();
    });

    test('handles progress with different completion states', function () {
        // Use the completed() state method from the factory
        $completedProgress = UserGameProgress::factory()->completed()->create([
            'user_id' => $this->user->id,
        ]);

        // Use the reading() state method from the factory
        $inProgressProgress = UserGameProgress::factory()->reading()->create([
            'user_id' => $this->otherUser->id,
        ]);

        // Ownership is what matters, not completion state
        expect($this->policy->update($this->user, $completedProgress))->toBeTrue()
            ->and($this->policy->update($this->user, $inProgressProgress))->toBeFalse();
    });
});

describe('edge cases', function () {
    test('handles user with multiple game progress records', function () {
        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();

        $progress1 = UserGameProgress::factory()->create([
            'user_id' => $this->user->id,
            'game_id' => $game1->id,
        ]);

        $progress2 = UserGameProgress::factory()->create([
            'user_id' => $this->user->id,
            'game_id' => $game2->id,
        ]);

        expect($this->policy->update($this->user, $progress1))->toBeTrue()
            ->and($this->policy->update($this->user, $progress2))->toBeTrue()
            ->and($this->policy->update($this->otherUser, $progress1))->toBeFalse()
            ->and($this->policy->update($this->otherUser, $progress2))->toBeFalse();
    });

    test('verifies user_id is strictly checked', function () {
        $progress = UserGameProgress::factory()->create([
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
        ]);

        // Create a user with a different ID
        $userWithDifferentId = User::factory()->create();

        expect($this->policy->update($userWithDifferentId, $progress))->toBeFalse();
    });
});
