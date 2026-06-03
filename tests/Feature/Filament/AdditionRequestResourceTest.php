<?php

declare(strict_types=1);

use App\Filament\Resources\AdditionRequests\Pages\EditAdditionRequest;
use App\Models\AdditionRequest;
use App\Models\Game;
use App\Models\User;
use Livewire\Livewire;

test('admin can assign a game to an addition request from the edit form', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create(['name' => 'Assigned Game']);
    $request = AdditionRequest::factory()->create([
        'status' => AdditionRequest::STATUS_PENDING,
        'game_id' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(EditAdditionRequest::class, ['record' => $request->getRouteKey()])
        ->set('data.game_id', $game->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($request->refresh()->game_id)->toBe($game->id);
});

test('linked game select can return autocomplete results', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $game = Game::factory()->create([
        'name' => 'Autocomplete Target',
        'url' => ['itch_io' => 'https://studio.itch.io/autocomplete-target'],
    ]);
    Game::factory()->create([
        'name' => 'Other Autocomplete Target',
        'url' => ['itch_io' => 'https://studio.itch.io/other-autocomplete-target'],
    ]);
    Game::factory()->create([
        'name' => 'URL Only Match',
        'url' => ['itch_io' => 'https://autocomplete.itch.io/url-only-match'],
    ]);
    $request = AdditionRequest::factory()->create(['game_id' => null]);

    $component = Livewire::actingAs($admin)
        ->test(EditAdditionRequest::class, ['record' => $request->getRouteKey()])
        ->instance()
        ->getSchema('form')
        ->getFlatFields(withHidden: true)['game_id'];

    expect($component->getSearchResultsForJs('Au'))->toBeEmpty()
        ->and($component->getSearchResultsForJs('Target'))->toBeEmpty();

    $results = $component->getSearchResultsForJs('Aut');

    expect($results)
        ->toHaveCount(1)
        ->and($results[0]['label'])
        ->toBe('Autocomplete Target (studio/autocomplete-target)')
        ->and($results[0]['value'])
        ->toBe((string) $game->id);
});
