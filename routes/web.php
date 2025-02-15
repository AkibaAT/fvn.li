<?php

declare(strict_types=1);

use App\Livewire\GameList;
use App\Models\Game;
use App\Models\Rater;
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

    return redirect(status: 301)->route('games.show', $game);
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

    return redirect(status: 301)->route('games.show', $game);
});

// Redirect old review URLs to game pages
Route::get('reviews/{id}', function ($id) {
    $game = Game::find($id);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->route('games.show', $game);
});

Route::get('api/reviews/{id}', function ($id) {
    $game = Game::find($id);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->route('games.show', $game);
});

Route::get('versions/{id}', function ($id) {
    $game = Game::find($id);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->route('games.show', $game);
});

// Redirect old users URLs to raters
Route::get('users/{id}', function ($id) {
    $rater = Rater::find($id);
    if (! $rater) {
        $rater = Rater::firstWhere('user_id', $id);
    }
    if (! $rater) {
        abort(404);
    }

    return redirect(status: 301)->route('raters.show', $rater->id);
});

Route::get('api/users/{id}', function ($id) {
    $rater = Rater::find($id);
    if (! $rater) {
        $rater = Rater::firstWhere('user_id', $id);
    }
    if (! $rater) {
        abort(404);
    }

    return redirect(status: 301)->route('raters.show', $rater->id);
});

Route::get('{game:slug}', function ($slug) {
    return redirect(status: 301)->route('games.show', $slug);
});

Route::get('by-game-id/{game:game_id}', function ($gameId) {
    $slug = Game::where('game_id', $gameId)->first()?->slug;
    if (! $slug) {
        abort(404);
    }

    return redirect(status: 301)->route('games.show', $slug);
});

/*
|--------------------------------------------------------------------------
| Current Routes
|--------------------------------------------------------------------------
*/

Route::get('by-url/{url}', function ($url) {
    $game = Game::firstWhere('url', $url);
    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->route('games.show', $game);
})->where('url', '.*');

// Shorter caching
Route::middleware('cache.headers:public;max_age=3600;etag')->group(function () {
    Route::get('/', GameList::class)->name('games.index');
    Route::get('games/{game:slug}', App\Livewire\GameDetail::class)->name('games.show');
});

// Longer caching
Route::middleware('cache.headers:public;max_age=86400;etag')->group(function () {
    Route::get('raters/{rater}', App\Livewire\RaterDetail::class)->name('raters.show');
    Route::get('system/status', App\Livewire\SystemStatus::class)->name('system.status');
});
