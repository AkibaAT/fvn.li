<?php

declare(strict_types=1);

use App\Livewire\GameList;

Route::prefix('/')->group(function () {
    Route::get('/', GameList::class)->name('games.index');
    Route::get('{game}', App\Livewire\GameDetail::class)->name('games.show');
});
