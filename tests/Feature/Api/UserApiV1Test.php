<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\PrivateTag;
use App\Models\User;
use App\Models\VnListEntry;

function userApiToken(User $user, array $abilities): string
{
    return $user->createToken('test-user-api', ['user-api', ...$abilities], now()->addDays(90))->plainTextToken;
}

it('requires a scoped bearer token and keeps account and admin routes closed to user tokens', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $token = userApiToken($admin, ['catalog:read']);

    $this->getJson('/api/v1/games')->assertUnauthorized();
    $this->withToken($token)->getJson('/api/v1/games')->assertOk()
        ->assertHeader('X-Token-Expires-At')->assertHeader('Cache-Control', 'no-store, private');
    $this->withToken($token)->getJson('/api/v1/private-tags')->assertForbidden()
        ->assertExactJson(['message' => 'This token lacks the tags:read permission.']);
    $this->withToken($token)->getJson('/api/v1/games/999999')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    $this->withToken($token)->getJson('/api/user')->assertForbidden();
    $this->withToken($token)->getJson('/api/discord-servers')->assertForbidden();
});

it('rejects expired user tokens', function () {
    $user = User::factory()->create();
    $expired = $user->createToken('expired', ['user-api', 'catalog:read'], now()->subMinute())->plainTextToken;

    $this->withToken($expired)->getJson('/api/v1/games')->assertUnauthorized();
});

it('does not accept a browser session for v1', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/v1/games')->assertUnauthorized();
});

it('resolves visible games by IDs or stored platform URLs without guessing', function () {
    $user = User::factory()->create();
    $this->withToken(userApiToken($user, ['catalog:read']));
    $itch = Game::factory()->create(['itch_id' => 41235, 'platform' => 'itch_io', 'url' => ['itch_io' => 'https://maker.itch.io/story']]);
    $steam = Game::factory()->create(['itch_id' => null, 'steam_app_id' => 222, 'platform' => 'steam', 'url' => ['steam' => 'https://store.steampowered.com/app/222/story']]);
    $other = Game::factory()->create(['itch_id' => null, 'platform' => 'other', 'url' => ['other' => 'https://example.org/story']]);
    $caseSensitive = Game::factory()->create(['itch_id' => null, 'platform' => 'other', 'url' => ['other' => 'https://example.org/Story']]);
    Game::factory()->create(['itch_id' => 99999, 'is_visible' => false, 'url' => ['itch_io' => 'https://hidden.itch.io/story']]);
    Game::factory()->create(['itch_id' => null, 'steam_app_id' => 222, 'platform' => 'steam', 'url' => ['steam' => 'https://store.steampowered.com/app/222/other']]);

    $this->getJson('/api/v1/games/resolve?itch_id=41235')->assertOk()->assertJsonPath('data.id', $itch->id);
    $this->getJson('/api/v1/games/resolve?url=http%3A%2F%2FEXAMPLE.ORG%2Fstory%2F')->assertOk()->assertJsonPath('data.id', $other->id);
    $this->getJson('/api/v1/games/resolve?url=https%3A%2F%2Fexample.org%2FStory')->assertOk()->assertJsonPath('data.id', $caseSensitive->id);
    $this->getJson('/api/v1/games/resolve?url=https%3A%2F%2Fexample.org%3A8443%2Fstory')->assertNotFound();
    $this->getJson('/api/v1/games/resolve?steam_app_id=222')->assertStatus(409);
    $this->getJson('/api/v1/games/resolve?itch_id=99999')->assertNotFound();
    $this->getJson('/api/v1/games/resolve?itch_id=41235&_=1700000000')->assertOk()->assertJsonPath('data.id', $itch->id);
    $this->getJson('/api/v1/games/resolve?itch_id=41235&steam_app_id=222')->assertUnprocessable();
    $this->getJson('/api/v1/games?platform=steam')->assertOk()->assertJsonPath('data.0.platform', 'steam');
    $this->getJson("/api/v1/games/{$steam->id}")->assertOk()->assertJsonMissing(['is_visible' => true]);
    $this->postJson('/api/v1/games/resolve', ['items' => [
        ['itch_id' => 41235], ['url' => 'https://example.org/story'], ['itch_id' => 99999], ['steam_app_id' => 222],
    ]])->assertOk()->assertJsonPath('data.0.status', 'matched')->assertJsonPath('data.1.status', 'matched')
        ->assertJsonPath('data.2.status', 'unmatched')->assertJsonPath('data.3.status', 'ambiguous');
    $this->postJson('/api/v1/games/resolve', ['items' => [['url' => 'ftp://example.org/story']]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.url');
});

it('matches search wildcards literally', function () {
    $this->withToken(userApiToken(User::factory()->create(), ['catalog:read']));
    $percent = Game::factory()->create(['name' => '100% Romance', 'authors' => 'Someone']);
    Game::factory()->create(['name' => 'Plain Romance', 'authors' => 'Someone_Else']);

    $this->getJson('/api/v1/games?q=' . urlencode('%'))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $percent->id);
    $this->getJson('/api/v1/games?q=' . urlencode('e_E'))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/games?q=' . urlencode('e_x'))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/games?q=Romance&per_page=1')->assertOk()
        ->assertJsonPath('links.next', fn (string $next) => str_contains($next, 'q=Romance') && str_contains($next, 'per_page=1'));
});

it('keeps private tags per user and permits tagging watched non-itch games', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $game = Game::factory()->create(['itch_id' => null, 'platform' => 'other', 'url' => ['other' => 'https://example.org/game']]);
    $itch = Game::factory()->create(['platform' => 'itch_io']);
    $steam = Game::factory()->create(['itch_id' => null, 'platform' => 'steam']);
    $hidden = Game::factory()->create(['is_visible' => false]);
    $owner->gameProgress()->create(['game_id' => $game->id, 'is_receiving_updates' => true]);
    $this->withToken(userApiToken($owner, ['tags:read', 'tags:write']));

    $id = $this->postJson('/api/v1/private-tags', ['name' => 'Favorite'])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/private-tags', ['name' => 'favorite'])->assertUnprocessable();
    $this->putJson("/api/v1/games/{$game->id}/private-tags/{$id}")->assertOk();
    $this->putJson("/api/v1/games/{$itch->id}/private-tags/{$id}")->assertOk();
    $this->putJson("/api/v1/games/{$steam->id}/private-tags/{$id}")->assertOk();
    $this->putJson("/api/v1/games/{$hidden->id}/private-tags/{$id}")->assertNotFound();
    $this->getJson("/api/v1/private-tags/{$id}/games")->assertOk()->assertJsonCount(3, 'data');
    $steam->update(['is_visible' => false]);
    $this->getJson('/api/v1/private-tags')->assertOk()->assertJsonPath('data.0.games_count', 2);
    $this->getJson("/api/v1/private-tags/{$id}/games")->assertOk()->assertJsonCount(2, 'data');

    app('auth')->forgetGuards();
    $this->withToken(userApiToken($other, ['tags:read', 'tags:write']))
        ->getJson("/api/v1/private-tags/{$id}/games")->assertNotFound();
    $this->getJson("/api/v1/games/{$game->id}/private-tags")->assertOk()->assertJsonPath('data', []);
    expect(PrivateTag::count())->toBe(1);
});

it('manages lists with owner checks, default exclusivity, notes and exact reorder', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $game = Game::factory()->create(['is_paid' => false]);
    $second = Game::factory()->create(['is_paid' => false]);
    $this->withToken(userApiToken($user, ['lists:read', 'lists:write']));

    $list = $this->postJson('/api/v1/lists', ['name' => 'My picks'])->assertCreated()->json('data.id');
    $entry = $this->putJson("/api/v1/lists/{$list}/games/{$game->id}")->assertCreated()->json('data.id');
    $this->putJson("/api/v1/lists/{$list}/games/{$game->id}")->assertOk();
    $secondEntry = $this->putJson("/api/v1/lists/{$list}/games/{$second->id}")->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/lists/{$list}/entries/{$entry}", ['private_notes' => 'Keep private'])->assertOk()
        ->assertJsonPath('data.private_notes', 'Keep private');
    $this->postJson("/api/v1/lists/{$list}/entries/reorder", ['entry_ids' => [$entry]])->assertUnprocessable();
    $this->postJson("/api/v1/lists/{$list}/entries/reorder", ['entry_ids' => [$secondEntry, $entry]])->assertOk()
        ->assertJsonPath('data.0.id', $secondEntry)->assertJsonPath('data.1.id', $entry);
    expect(VnListEntry::findOrFail($secondEntry)->sort_order)->toBe(10);
    $target = $this->postJson('/api/v1/lists', ['name' => 'Another list'])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/lists/{$list}/entries/{$entry}/move", ['target_list_id' => $target])->assertOk()
        ->assertJsonPath('data.private_notes', 'Keep private');
    $this->getJson("/api/v1/lists/{$other->vnLists()->first()->id}")->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    expect($this->getJson('/api/v1/lists')->assertOk()->json('data.*.type'))
        ->toBe(['reading', 'plan_to_read', 'completed', 'on_hold', 'dropped', 'custom', 'custom']);

    $reading = $user->vnLists()->where('type', 'reading')->firstOrFail();
    $completed = $user->vnLists()->where('type', 'completed')->firstOrFail();
    $this->patchJson("/api/v1/lists/{$reading->id}", ['name' => 'Currently reading'])->assertOk();
    $this->deleteJson("/api/v1/lists/{$reading->id}")->assertForbidden()
        ->assertJsonPath('message', 'Default lists cannot be deleted.');
    $this->putJson("/api/v1/lists/{$reading->id}/games/{$game->id}")->assertCreated();
    $this->putJson("/api/v1/lists/{$completed->id}/games/{$game->id}")->assertCreated();
    expect(VnListEntry::where('vn_list_id', $reading->id)->where('game_id', $game->id)->exists())->toBeFalse();
});

it('validates tracking writes and throttles writes per account', function () {
    $user = User::factory()->create();
    $paid = Game::factory()->create(['is_paid' => true]);
    $game = Game::factory()->create(['is_paid' => false]);
    $wrongVersion = GameVersion::factory()->for($paid)->create();
    $version = GameVersion::factory()->for($game)->create();
    $this->withToken(userApiToken($user, ['tracking:read', 'tracking:write', 'preferences:read', 'preferences:write']));

    $this->patchJson("/api/v1/me/tracked-games/{$paid->id}", ['is_receiving_updates' => true])->assertUnprocessable();
    $this->patchJson("/api/v1/me/tracked-games/{$paid->id}", ['personal_notes' => 'Wishlisted'])->assertCreated()
        ->assertJsonPath('data.status', 'reading')->assertJsonPath('data.is_receiving_updates', false);
    $this->patchJson("/api/v1/me/tracked-games/{$game->id}", ['game_version_id' => $wrongVersion->id])->assertUnprocessable();
    $this->patchJson("/api/v1/me/tracked-games/{$game->id}", [
        'status' => 'reading', 'is_receiving_updates' => true, 'game_version_id' => $version->id,
        'started_at' => '2026-09-01', 'personal_notes' => 'Keep private',
    ])->assertCreated()->assertJsonPath('data.is_receiving_updates', true)
        ->assertJsonPath('data.game_version_id', $version->id)
        ->assertJsonPath('data.personal_notes', 'Keep private');
    expect($this->getJson('/api/v1/me/tracked-games')->assertOk()->json('data.*.game_id'))->toEqualCanonicalizing([$paid->id, $game->id]);
    $this->patchJson('/api/v1/me/search-preferences', ['excluded_tag_ids' => []])->assertOk();
    $this->putJson("/api/v1/me/ignored-games/{$game->id}")->assertNoContent();
    $this->getJson('/api/v1/me/ignored-games')->assertOk()->assertJsonPath('data.0.id', $game->id);

    for ($i = 0; $i < 24; $i++) {
        $this->patchJson('/api/v1/me/search-preferences', ['excluded_tag_ids' => []])->assertOk();
    }
    $this->patchJson('/api/v1/me/search-preferences', ['excluded_tag_ids' => []])->assertStatus(429)
        ->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit');

    app('auth')->forgetGuards();
    $this->withToken(userApiToken($user, ['preferences:write']))
        ->patchJson('/api/v1/me/search-preferences', ['excluded_tag_ids' => []])->assertStatus(429);

    $otherToken = userApiToken(User::factory()->create(), ['preferences:write']);
    app('auth')->forgetGuards();
    $this->withToken($otherToken)
        ->patchJson('/api/v1/me/search-preferences', ['excluded_tag_ids' => []])->assertOk();
});

it('limits batch resolution per account across tokens', function () {
    $user = User::factory()->create();
    $this->withToken(userApiToken($user, ['catalog:read']));
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/games/resolve', ['items' => [['itch_id' => 99999999]]])->assertOk();
    }
    $this->postJson('/api/v1/games/resolve', ['items' => [['itch_id' => 99999999]]])
        ->assertStatus(429)->assertHeader('Retry-After');
    $this->getJson('/api/v1/games')->assertOk();

    app('auth')->forgetGuards();
    $this->withToken(userApiToken($user, ['catalog:read']))
        ->postJson('/api/v1/games/resolve', ['items' => [['itch_id' => 99999999]]])->assertStatus(429);

    $otherToken = userApiToken(User::factory()->create(), ['catalog:read']);
    app('auth')->forgetGuards();
    $this->withToken($otherToken)
        ->postJson('/api/v1/games/resolve', ['items' => [['itch_id' => 99999999]]])->assertOk();
});

it('limits anonymous requests by IP before authentication', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']);
    for ($i = 0; $i < 300; $i++) {
        $this->getJson('/api/v1/games')->assertUnauthorized();
    }
    $this->getJson('/api/v1/games')
        ->assertStatus(429)->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit', '300');
});
