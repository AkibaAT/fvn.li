<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;

it('manages private tags through the browser session', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['name' => 'Moonlit Tale']);
    $hidden = Game::factory()->create(['is_visible' => false]);
    $this->actingAs($user);

    $id = $this->postJson(route('browser-api.private-tags.store'), ['name' => 'Favorite'])->assertCreated()
        ->assertJsonPath('data.games_count', 0)->json('data.id');
    $this->postJson(route('browser-api.private-tags.store'), ['name' => 'FAVORITE'])->assertUnprocessable()
        ->assertJsonValidationErrors('name');
    $this->putJson(route('browser-api.games.private-tags.attach', ['game' => $game->id, 'tag' => $id]))->assertOk()
        ->assertJsonPath('data.games_count', 1);
    $this->putJson(route('browser-api.games.private-tags.attach', ['game' => $hidden->id, 'tag' => $id]))->assertNotFound();
    $this->getJson(route('browser-api.private-tags.games', $id))->assertOk()
        ->assertExactJson(['data' => [['id' => $game->id, 'name' => 'Moonlit Tale', 'slug' => $game->slug]], 'links' => ['next' => null]]);
    $this->patchJson(route('browser-api.private-tags.update', $id), ['name' => 'Loved'])->assertOk()->assertJsonPath('data.name', 'Loved');
    $this->getJson(route('browser-api.private-tags.index'))->assertOk()
        ->assertExactJson(['data' => [['id' => $id, 'name' => 'Loved', 'games_count' => 1]]]);
    $this->actingAs(User::factory()->create())->getJson(route('browser-api.private-tags.games', $id))->assertNotFound();
    $this->actingAs($user)->deleteJson(route('browser-api.games.private-tags.detach', ['game' => $game->id, 'tag' => $id]))->assertNoContent();
    $this->deleteJson(route('browser-api.private-tags.destroy', $id))->assertNoContent();
});
