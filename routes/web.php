<?php

declare(strict_types=1);

use App\Livewire\GameList;
use App\Models\Game;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Permanent Redirects for old URLs
|--------------------------------------------------------------------------
*/

// Redirect old ratings and game-versions filters to game pages
Route::get('ratings', function (Request $request) {
    $tableFilters = $request->query('tableFilters');
    $gameId = $tableFilters['game']['value'] ?? null;
    if (! $gameId) {
        return redirect(status: 301)->route('games.index');
    }

    $game = Game::find($gameId);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->to(
        $game->is_visible
            ? route('games.show', $game)
            : route('games.show.game-id', $game->game_id)
    );
});

Route::get('game-versions', function (Request $request) {
    $tableFilters = $request->query('tableFilters');
    $gameId = $tableFilters['game']['value'] ?? null;
    if (! $gameId) {
        return redirect(status: 301)->route('games.index');
    }

    $game = Game::find($gameId);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->to(
        $game->is_visible
            ? route('games.show', $game)
            : route('games.show.game-id', $game->game_id)
    );
});

// Redirect old review URLs to game pages
Route::get('reviews/{id}', function ($id) {
    $game = Game::find($id);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->to(
        $game->is_visible
            ? route('games.show', $game)
            : route('games.show.game-id', $game->game_id)
    );
});

Route::get('api/reviews/{id}', function ($id) {
    $game = Game::find($id);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->to(
        $game->is_visible
            ? route('games.show', $game)
            : route('games.show.game-id', $game->game_id)
    );
});

Route::get('versions/{id}', function ($id) {
    $game = Game::find($id);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->to(
        $game->is_visible
            ? route('games.show', $game)
            : route('games.show.game-id', $game->game_id)
    );
});

// Redirect old users URLs to raters
Route::get('users/{id}', function ($id) {
    return redirect(status: 301)->route('raters.show', $id);
});

Route::get('api/users/{id}', function ($id) {
    return redirect(status: 301)->route('raters.show', $id);
});

/*
|--------------------------------------------------------------------------
| Current Routes
|--------------------------------------------------------------------------
*/

Route::get('/', GameList::class)->name('games.index');
Route::get('{game:slug}', App\Livewire\GameDetail::class)->name('games.show');
Route::get('by-url/{url}', function ($url) {
    $game = Game::firstWhere('url', $url);
    if (! $game) {
        abort(404);
    }

    return redirect()->to(
        $game->is_visible
            ? route('games.show', $game)
            : route('games.show.game-id', $game->game_id)
    );
})->where('url', '.*');
Route::get('by-game-id/{game:game_id}', App\Livewire\GameDetail::class)->name('games.show.game-id');
Route::get('raters/{rater}', App\Livewire\RaterDetail::class)->name('raters.show');
