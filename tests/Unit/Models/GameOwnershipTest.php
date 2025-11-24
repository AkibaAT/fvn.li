<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->admin = User::factory()->create(['is_admin' => true]);
});

describe('game ownership via itch.io API data', function () {
    test('user owns game when game_id is in itchio_game_ids', function () {
        $game = Game::factory()->create(['game_id' => 12345]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [12345, 67890],
        ]);

        expect($this->user->ownsGame($game))->toBeTrue();
    });

    test('user does not own game when game_id is not in itchio_game_ids', function () {
        $game = Game::factory()->create(['game_id' => 99999]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [12345, 67890],
        ]);

        expect($this->user->ownsGame($game))->toBeFalse();
    });

    test('user does not own game when no itch.io account exists', function () {
        $game = Game::factory()->create(['game_id' => 12345]);

        expect($this->user->ownsGame($game))->toBeFalse();
    });

    test('handles empty itchio_game_ids array', function () {
        $game = Game::factory()->create(['game_id' => 12345]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [],
        ]);

        expect($this->user->ownsGame($game))->toBeFalse();
    });
});

describe('game ownership via URL fallback', function () {
    test('user owns game when URL matches itch.io profile', function () {
        $game = Game::factory()->create([
            'url' => 'https://testdev.itch.io/my-game',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testdev.itch.io'],
            'itchio_game_ids' => null,
        ]);

        expect($this->user->ownsGame($game))->toBeTrue();
    });

    test('user does not own game when URL does not match', function () {
        $game = Game::factory()->create([
            'url' => 'https://otherdev.itch.io/my-game',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testdev.itch.io'],
            'itchio_game_ids' => null,
        ]);

        expect($this->user->ownsGame($game))->toBeFalse();
    });

    test('handles case insensitive domain comparison', function () {
        $game = Game::factory()->create([
            'url' => 'https://TestDev.itch.io/my-game',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testdev.itch.io'],
            'itchio_game_ids' => null,
        ]);

        expect($this->user->ownsGame($game))->toBeTrue();
    });

    test('handles invalid URLs gracefully', function () {
        $game = Game::factory()->create([
            'url' => 'not-a-valid-url',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testdev.itch.io'],
            'itchio_game_ids' => null,
        ]);

        expect($this->user->ownsGame($game))->toBeFalse();
    });
});

describe('canUserEdit method', function () {
    test('admin can edit any game', function () {
        $game = Game::factory()->create();

        expect($game->canUserEdit($this->admin))->toBeTrue();
    });

    test('owner can edit their game via API data', function () {
        $game = Game::factory()->create(['game_id' => 12345]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [12345],
        ]);

        expect($game->canUserEdit($this->user))->toBeTrue();
    });

    test('owner can edit their game via URL matching', function () {
        $game = Game::factory()->create([
            'url' => 'https://testdev.itch.io/my-game',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testdev.itch.io'],
            'itchio_game_ids' => null,
        ]);

        expect($game->canUserEdit($this->user))->toBeTrue();
    });

    test('non-owner cannot edit game', function () {
        $game = Game::factory()->create(['game_id' => 99999]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [12345],
        ]);

        expect($game->canUserEdit($this->user))->toBeFalse();
    });

    test('guest cannot edit game', function () {
        $game = Game::factory()->create();

        expect($game->canUserEdit(null))->toBeFalse();
    });

    test('user without itch.io account cannot edit game', function () {
        $game = Game::factory()->create();

        expect($game->canUserEdit($this->user))->toBeFalse();
    });
});

describe('getOwnedGames method', function () {
    test('returns games owned via API data', function () {
        $game1 = Game::factory()->create(['game_id' => 100, 'is_visible' => true]);
        $game2 = Game::factory()->create(['game_id' => 200, 'is_visible' => true]);
        $game3 = Game::factory()->create(['game_id' => 300, 'is_visible' => true]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [100, 200],
        ]);

        $ownedGames = $this->user->getOwnedGames();

        expect($ownedGames)->toHaveCount(2)
            ->and($ownedGames->pluck('id')->toArray())->toContain($game1->id, $game2->id)
            ->and($ownedGames->pluck('id')->toArray())->not->toContain($game3->id);
    });

    test('returns games owned via URL matching', function () {
        $game1 = Game::factory()->create([
            'url' => 'https://testdev.itch.io/game1',
            'is_visible' => true,
        ]);
        $game2 = Game::factory()->create([
            'url' => 'https://testdev.itch.io/game2',
            'is_visible' => true,
        ]);
        $game3 = Game::factory()->create([
            'url' => 'https://otherdev.itch.io/game3',
            'is_visible' => true,
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testdev.itch.io'],
            'itchio_game_ids' => null,
        ]);

        $ownedGames = $this->user->getOwnedGames();

        expect($ownedGames)->toHaveCount(2)
            ->and($ownedGames->pluck('id')->toArray())->toContain($game1->id, $game2->id)
            ->and($ownedGames->pluck('id')->toArray())->not->toContain($game3->id);
    });

    test('excludes invisible games', function () {
        $visibleGame = Game::factory()->create(['game_id' => 100, 'is_visible' => true]);
        $invisibleGame = Game::factory()->create(['game_id' => 200, 'is_visible' => false]);

        SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [100, 200],
        ]);

        $ownedGames = $this->user->getOwnedGames();

        expect($ownedGames)->toHaveCount(1)
            ->and($ownedGames->first()->id)->toBe($visibleGame->id);
    });

    test('returns empty collection when no itch.io account', function () {
        Game::factory()->count(3)->create(['is_visible' => true]);

        $ownedGames = $this->user->getOwnedGames();

        expect($ownedGames)->toBeEmpty();
    });
});
