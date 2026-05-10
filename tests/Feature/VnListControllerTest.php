<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use Illuminate\Support\Facades\Route;

function makeListWithEntry(User $user, array $listAttributes = [], array $gameAttributes = []): array
{
    $game = Game::factory()->create(array_merge([
        'name' => 'List Test Game',
        'is_visible' => true,
        'is_paid' => false,
    ], $gameAttributes));

    $list = VnList::factory()->for($user)->create(array_merge([
        'name' => fake()->unique()->words(3, true),
        'type' => 'custom',
        'is_public' => true,
    ], $listAttributes));

    $entry = VnListEntry::factory()->create([
        'vn_list_id' => $list->id,
        'game_id' => $game->id,
        'sort_order' => 10,
    ]);

    return [$list, $game, $entry];
}

it('renders the authenticated lists index with visibility counts and filtered lists', function () {
    $user = User::factory()->create();
    [$publicList] = makeListWithEntry($user, [
        'name' => 'Public Picks',
        'is_public' => true,
        'type' => 'reading',
    ]);
    VnList::factory()->for($user)->create([
        'name' => 'Private Notes',
        'is_public' => false,
        'type' => 'custom',
    ]);

    $response = $this->actingAs($user)->get(route('lists.index', [
        'visibility' => 'public',
        'per_page' => 4,
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($response->viewData('page')['component'])->toBe('lists/index')
        ->and($props['visibility'])->toBe('public')
        ->and($props['counts']['all'])->toBe(7)
        ->and($props['counts']['public'])->toBe(1)
        ->and($props['counts']['private'])->toBe(6)
        ->and($props['lists']['data'])->toHaveCount(1)
        ->and($props['lists']['data'][0]['id'])->toBe($publicList->id);
});

it('renders public list discovery with search, type, game filter, and newest sorting', function () {
    $owner = User::factory()->create(['name' => 'Curator Example']);
    [$matchingList, $matchingGame] = makeListWithEntry($owner, [
        'name' => 'Public Romance List',
        'type' => 'custom',
        'is_public' => true,
        'created_at' => now()->subDay(),
    ], [
        'name' => 'Filtered Romance Game',
    ]);
    makeListWithEntry($owner, [
        'name' => 'Reading List',
        'type' => 'reading',
        'is_public' => true,
    ], [
        'name' => 'Other Game',
    ]);
    makeListWithEntry($owner, [
        'name' => 'Hidden List',
        'type' => 'custom',
        'is_public' => false,
    ], [
        'name' => 'Filtered Romance Game',
    ]);

    $response = $this->get(route('lists.public', [
        'type' => 'custom',
        'search' => 'Romance',
        'game' => $matchingGame->id,
        'sort' => 'newest',
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($response->viewData('page')['component'])->toBe('lists/public')
        ->and($props['type'])->toBe('custom')
        ->and($props['search'])->toBe('Romance')
        ->and($props['filterGame']['id'])->toBe($matchingGame->id)
        ->and($props['lists']['data'])->toHaveCount(1)
        ->and($props['lists']['data'][0]['id'])->toBe($matchingList->id)
        ->and($props['counts']['all'])->toBe(1)
        ->and($props['counts']['custom'])->toBe(1);
});

it('normalizes public list discovery filters to bounded safe values', function () {
    $owner = User::factory()->create(['name' => 'Wildcard Owner']);
    makeListWithEntry($owner, [
        'name' => 'Bounded Public List',
        'type' => 'reading',
        'is_public' => true,
    ], [
        'name' => 'Bounded Game',
    ]);

    $response = $this->get(route('lists.public', [
        'type' => 'invalid-type',
        'search' => '%',
        'game' => '-20',
        'sort' => 'unknown-sort',
        'page' => '-5',
        'per_page' => '100000',
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['type'])->toBe('all')
        ->and($props['search'])->toBe('')
        ->and($props['sort'])->toBe('default')
        ->and($props['filterGame'])->toBeNull()
        ->and($props['lists']['current_page'])->toBe(1)
        ->and($props['lists']['per_page'])->toBe(24);
});

it('bounds public list search length and keeps meaningful searches', function () {
    $owner = User::factory()->create(['name' => 'Long Search Owner']);
    makeListWithEntry($owner, [
        'name' => 'Long Search Public List',
        'type' => 'custom',
        'is_public' => true,
    ], [
        'name' => 'Alpha '.str_repeat('x', 120),
    ]);

    $response = $this->get(route('lists.public', [
        'search' => 'Alpha '.str_repeat('x', 120),
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['search'])->toHaveLength(80)
        ->and($props['lists']['data'])->toHaveCount(1);
});

it('throttles public list discovery route', function () {
    expect(Route::getRoutes()->getByName('lists.public')->gatherMiddleware())
        ->toContain('throttle:30,1');
});

it('renders public owner lists and hides private lists from other users', function () {
    $owner = User::factory()->create(['name' => 'Visible Owner']);
    [$publicList] = makeListWithEntry($owner, [
        'name' => 'Visible List',
        'type' => 'completed',
        'is_public' => true,
    ]);
    makeListWithEntry($owner, [
        'name' => 'Invisible List',
        'type' => 'completed',
        'is_public' => false,
    ]);

    $response = $this->get(route('lists.user-public', [
        'user' => $owner->id,
        'types' => 'completed',
        'search' => 'Visible',
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($response->viewData('page')['component'])->toBe('lists/user-public')
        ->and($props['user']['id'])->toBe($owner->id)
        ->and($props['lists']['data'])->toHaveCount(1)
        ->and($props['lists']['data'][0]['id'])->toBe($publicList->id);
});

it('shows a list to the owner with entries, available target lists, and ownership state', function () {
    $owner = User::factory()->create();
    [$list, $game] = makeListWithEntry($owner, [
        'name' => 'Owner List',
        'is_public' => false,
    ]);
    $targetList = VnList::factory()->for($owner)->create([
        'name' => 'Move Target',
        'type' => 'custom',
        'is_public' => false,
    ]);

    $response = $this->actingAs($owner)->get(route('lists.show', $list));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($response->viewData('page')['component'])->toBe('lists/show')
        ->and($props['vnList']['id'])->toBe($list->id)
        ->and($props['vnList']['entries'][0]['game']['id'])->toBe($game->id)
        ->and($props['isOwner'])->toBeTrue()
        ->and(collect($props['availableLists'])->pluck('id')->all())->toContain($targetList->id);
});

it('prevents guests from viewing private lists', function () {
    $owner = User::factory()->create();
    [$list] = makeListWithEntry($owner, ['is_public' => false]);

    $this->get(route('lists.show', $list))->assertForbidden();
});

it('creates a custom list without an initial game through JSON endpoints', function () {
    $user = User::withoutEvents(fn () => User::factory()->create());

    $response = $this->actingAs($user)->postJson(route('api.vn-lists.store'), [
        'name' => 'Summer Reading',
        'description' => 'VNs to read this summer.',
        'is_public' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'List created successfully.')
        ->assertJsonPath('list.name', 'Summer Reading')
        ->assertJsonPath('list.description', 'VNs to read this summer.')
        ->assertJsonPath('list.type', 'custom')
        ->assertJsonPath('list.is_public', true);

    $this->assertDatabaseHas('vn_lists', [
        'user_id' => $user->id,
        'name' => 'Summer Reading',
        'type' => 'custom',
        'is_default' => false,
        'is_public' => true,
    ]);
});

it('creates, updates, toggles, and deletes a custom list through JSON endpoints', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['is_visible' => true]);

    $createResponse = $this->actingAs($user)->postJson(route('api.vn-lists.store'), [
        'name' => 'Fresh Custom List',
        'description' => 'First description',
        'is_public' => true,
        'game_id' => $game->id,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'List created and game added successfully.');

    $list = VnList::where('user_id', $user->id)->where('name', 'Fresh Custom List')->firstOrFail();
    expect($list->entries()->where('game_id', $game->id)->exists())->toBeTrue();

    $this->actingAs($user)->putJson(route('api.vn-lists.update', $list), [
        'name' => 'Renamed Custom List',
        'description' => 'Updated description',
        'is_public' => false,
    ])->assertOk()
        ->assertJsonPath('vnList.name', 'Renamed Custom List')
        ->assertJsonPath('vnList.is_public', false);

    $this->actingAs($user)->postJson(route('api.vn-lists.toggle-visibility', $list))
        ->assertOk()
        ->assertJsonPath('is_public', true);

    $this->actingAs($user)->deleteJson(route('api.vn-lists.destroy', $list))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(VnList::find($list->id))->toBeNull();
});

it('rejects duplicate list names and default list deletion', function () {
    $user = User::factory()->create();
    VnList::factory()->for($user)->create(['name' => 'Duplicate']);
    $defaultList = VnList::factory()->for($user)->create([
        'name' => 'Default Reading',
        'type' => 'reading',
        'is_default' => true,
    ]);

    $this->actingAs($user)->postJson(route('api.vn-lists.store'), [
        'name' => 'Duplicate',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name'])
        ->assertJsonPath('errors.name.0', 'You already have a list with this name.');

    $this->actingAs($user)->deleteJson(route('api.vn-lists.destroy', $defaultList))
        ->assertForbidden();
});

it('returns json for unauthenticated create requests', function () {
    $this->postJson(route('api.vn-lists.store'), [
        'name' => 'Guest List',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('adds, toggles, moves, updates, reorders, and removes list entries', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['is_paid' => false]);
    $otherGame = Game::factory()->create(['is_paid' => false]);
    $reading = $user->vnLists()->where('type', 'reading')->where('is_default', true)->first()
        ?? VnList::factory()->for($user)->create([
            'name' => 'Test Reading',
            'type' => 'reading',
            'is_default' => true,
        ]);
    $completed = $user->vnLists()->where('type', 'completed')->where('is_default', true)->first()
        ?? VnList::factory()->for($user)->create([
            'name' => 'Test Completed',
            'type' => 'completed',
            'is_default' => true,
        ]);
    $custom = VnList::factory()->for($user)->create([
        'name' => 'Custom Target',
        'type' => 'custom',
        'is_public' => true,
    ]);

    $this->actingAs($user)->postJson(route('api.games.add-to-list', $game), [
        'list_type' => 'reading',
    ])->assertOk()
        ->assertJsonPath('action', 'added');

    $entry = VnListEntry::where('vn_list_id', $reading->id)->where('game_id', $game->id)->firstOrFail();

    $this->actingAs($user)->postJson(route('api.games.add-to-list', $game), [
        'list_type' => 'completed',
    ])->assertOk()
        ->assertJsonPath('action', 'added');

    expect(VnListEntry::where('vn_list_id', $reading->id)->where('game_id', $game->id)->exists())->toBeFalse()
        ->and(VnListEntry::where('vn_list_id', $completed->id)->where('game_id', $game->id)->exists())->toBeTrue();

    $entry = VnListEntry::where('vn_list_id', $completed->id)->where('game_id', $game->id)->firstOrFail();

    $this->actingAs($user)->putJson(route('api.list-entries.update', $entry), [
        'private_notes' => 'Private note',
        'personal_notes' => 'Progress note',
    ])->assertOk()
        ->assertJsonPath('entry.private_notes', 'Private note')
        ->assertJsonPath('progress.personal_notes', 'Progress note');

    $this->actingAs($user)->postJson(route('api.list-entries.move', $entry), [
        'target_list_id' => $custom->id,
    ])->assertOk()
        ->assertJsonPath('target_list.id', $custom->id);

    $movedEntry = $entry->fresh();
    expect($movedEntry->vn_list_id)->toBe($custom->id);

    $secondEntry = VnListEntry::factory()->create([
        'vn_list_id' => $custom->id,
        'game_id' => $otherGame->id,
        'sort_order' => 20,
    ]);

    $this->actingAs($user)->postJson(route('api.lists.reorder', $custom), [
        'entry_ids' => [$secondEntry->id, $movedEntry->id],
    ])->assertOk();

    expect($secondEntry->fresh()->sort_order)->toBe(10)
        ->and($movedEntry->fresh()->sort_order)->toBe(20);

    $this->actingAs($user)->deleteJson(route('api.list-entries.destroy', $movedEntry))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(VnListEntry::find($movedEntry->id))->toBeNull();
});

it('rejects duplicate target entries and invalid reorder payloads', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $source = VnList::factory()->for($user)->create(['name' => 'Source']);
    $target = VnList::factory()->for($user)->create(['name' => 'Target']);
    $entry = VnListEntry::factory()->create([
        'vn_list_id' => $source->id,
        'game_id' => $game->id,
    ]);
    VnListEntry::factory()->create([
        'vn_list_id' => $target->id,
        'game_id' => $game->id,
    ]);

    $this->actingAs($user)->postJson(route('api.list-entries.move', $entry), [
        'target_list_id' => $target->id,
    ])->assertUnprocessable()
        ->assertJsonPath('success', false);

    $this->actingAs($user)->postJson(route('api.lists.reorder', $source), [
        'entry_ids' => [$entry->id, 999999],
    ])->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('tracks list membership and progress status for the current user', function () {
    $user = User::factory()->create();
    [$list, $game] = makeListWithEntry($user, [
        'name' => 'Progress List',
        'is_public' => true,
    ]);

    $this->actingAs($user)->getJson(route('browser-api.games.lists', $game))
        ->assertOk()
        ->assertJsonPath('list_ids.0', $list->id);

    $this->actingAs($user)->putJson(route('api.user-progress.update', $game), [
        'personal_notes' => 'Started today',
        'started_at' => now()->toDateString(),
    ])->assertOk()
        ->assertJsonPath('progress.personal_notes', 'Started today');

    $this->actingAs($user)->patchJson(route('api.user-progress.toggle-updates', $game), [
        'receive_updates' => true,
    ])->assertOk()
        ->assertJsonPath('receive_updates', true);

    $this->actingAs($user)->getJson(route('browser-api.user-progress.status', $game))
        ->assertOk()
        ->assertJsonPath('receive_updates', true);

    expect(UserGameProgress::where('user_id', $user->id)->where('game_id', $game->id)->first()?->receive_updates)->toBeTrue();
});
