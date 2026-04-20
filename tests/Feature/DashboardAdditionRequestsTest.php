<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('addition requests endpoint includes linked approved game for the authenticated user', function () {
    $user = User::withoutEvents(fn () => User::factory()->create());
    $otherUser = User::withoutEvents(fn () => User::factory()->create());

    $game = Game::withoutEvents(fn () => Game::factory()->create([
        'name' => 'Approved Entry',
        'slug' => 'approved-entry',
    ]));

    $request = AdditionRequest::withoutEvents(fn () => AdditionRequest::factory()->approved()->create([
        'game_id' => $game->id,
    ]));
    $request->users()->attach($user->id);

    $otherRequest = AdditionRequest::withoutEvents(fn () => AdditionRequest::factory()->approved()->create());
    $otherRequest->users()->attach($otherUser->id);

    $response = $this->actingAs($user)->getJson(route('react-api.dashboard.addition-requests.index'));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'requests')
        ->assertJsonPath('requests.0.id', $request->id)
        ->assertJsonPath('requests.0.game.id', $game->id)
        ->assertJsonPath('requests.0.game.name', $game->name)
        ->assertJsonPath('requests.0.game.slug', $game->slug);
});
