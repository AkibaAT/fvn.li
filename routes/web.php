<?php

declare(strict_types=1);

use App\Livewire\GameList;
use App\Models\Game;

Route::get('/', GameList::class)->name('games.index');
Route::get('{game:slug}', App\Livewire\GameDetail::class)->name('games.show');
Route::get('by-url/{url}', function ($id) {
    $game = Game::firstWhere('url', $id);
    if (! $game) {
        abort(404);
    }

    return redirect()->route('games.show.game-id', $game);
})->where('url', '.*');
Route::get('by-game-id/{game:game_id}', App\Livewire\GameDetail::class)->name('games.show.game-id');
Route::get('raters/{rater}', App\Livewire\RaterDetail::class)->name('raters.show');
