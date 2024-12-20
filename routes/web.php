<?php

declare(strict_types=1);

use App\Livewire\GameList;

Route::prefix('games')->group(function () {
    Route::get('/', GameList::class)->name('games.index');
});
