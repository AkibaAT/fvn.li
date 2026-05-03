<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserPreference;

it('requires authentication for preference endpoints', function () {
    $this->getJson(route('user.language-preferences.index'))->assertUnauthorized();
    $this->putJson(route('user.language-preferences.update'), ['preferred_languages' => ['eng']])->assertUnauthorized();
    $this->getJson(route('user.excluded-tags.index'))->assertUnauthorized();
    $this->putJson(route('user.excluded-tags.update'), ['excluded_tags' => []])->assertUnauthorized();
    $this->getJson(route('user.ignored-games.index'))->assertUnauthorized();
    $this->postJson(route('user.ignored-games.store'), ['game_id' => 1])->assertUnauthorized();
    $this->deleteJson(route('user.ignored-games.destroy'), ['game_id' => 1])->assertUnauthorized();
    $this->postJson(route('user.ignored-games.toggle'), ['game_id' => 1])->assertUnauthorized();
});

it('stores and clears language preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('user.language-preferences.index'))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'preferred_languages' => [],
        ]);

    $this->actingAs($user)
        ->putJson(route('user.language-preferences.update'), [
            'preferred_languages' => ['eng', 'jpn'],
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'preferred_languages' => ['eng', 'jpn'],
        ]);

    expect(UserPreference::where('user_id', $user->id)->first()?->preferred_languages)
        ->toBe(['eng', 'jpn']);

    $this->actingAs($user)
        ->putJson(route('user.language-preferences.update'), [
            'preferred_languages' => [],
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'preferred_languages' => [],
        ]);

    expect(UserPreference::where('user_id', $user->id)->first()?->preferred_languages)
        ->toBeNull();
});

it('validates language preference payloads', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson(route('user.language-preferences.update'), [
            'preferred_languages' => ['en', 'english'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['preferred_languages.0', 'preferred_languages.1']);
});

it('stores and clears excluded tag preferences', function () {
    $user = User::factory()->create();
    $romance = Tag::create(['name' => 'Romance']);
    $mystery = Tag::create(['name' => 'Mystery']);

    $this->actingAs($user)
        ->getJson(route('user.excluded-tags.index'))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'excluded_tags' => [],
        ]);

    $this->actingAs($user)
        ->putJson(route('user.excluded-tags.update'), [
            'excluded_tags' => [(string) $romance->id, $mystery->id],
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'excluded_tags' => [$romance->id, $mystery->id],
        ]);

    expect(UserPreference::where('user_id', $user->id)->first()?->excluded_tags)
        ->toBe([$romance->id, $mystery->id]);

    $this->actingAs($user)
        ->putJson(route('user.excluded-tags.update'), [
            'excluded_tags' => [],
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'excluded_tags' => [],
        ]);

    expect(UserPreference::where('user_id', $user->id)->first()?->excluded_tags)
        ->toBeNull();
});

it('validates excluded tag preference payloads', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson(route('user.excluded-tags.update'), [
            'excluded_tags' => ['missing'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['excluded_tags.0']);

    $this->actingAs($user)
        ->putJson(route('user.excluded-tags.update'), [
            'excluded_tags' => [999999],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['excluded_tags.0']);
});

it('adds lists and removes ignored games', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['name' => 'Ignored Game']);
    $otherGame = Game::factory()->create(['name' => 'Other Game']);

    $this->actingAs($user)
        ->getJson(route('user.ignored-games.index'))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'ignored_game_ids' => [],
        ]);

    $this->actingAs($user)
        ->postJson(route('user.ignored-games.store'), ['game_id' => $game->id])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Game added to ignore list',
            'ignored_game_ids' => [$game->id],
        ]);

    $this->actingAs($user)
        ->postJson(route('user.ignored-games.store'), ['game_id' => $game->id])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Game already ignored',
            'ignored_game_ids' => [$game->id],
        ]);

    $this->actingAs($user)
        ->postJson(route('user.ignored-games.toggle'), ['game_id' => $otherGame->id])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Game added to ignore list',
            'is_ignored' => true,
        ]);

    expect($user->fresh()->ignoredGames()->pluck('games.id')->sort()->values()->all())
        ->toBe([$game->id, $otherGame->id]);

    $this->actingAs($user)
        ->postJson(route('user.ignored-games.toggle'), ['game_id' => $game->id])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Game removed from ignore list',
            'is_ignored' => false,
            'ignored_game_ids' => [$otherGame->id],
        ]);

    $this->actingAs($user)
        ->deleteJson(route('user.ignored-games.destroy'), ['game_id' => $otherGame->id])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Game removed from ignore list',
            'ignored_game_ids' => [],
        ]);
});

it('validates ignored game payloads', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('user.ignored-games.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['game_id']);

    $this->actingAs($user)
        ->deleteJson(route('user.ignored-games.destroy'), ['game_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['game_id']);

    $this->actingAs($user)
        ->postJson(route('user.ignored-games.toggle'), ['game_id' => 'not-a-game'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['game_id']);
});
