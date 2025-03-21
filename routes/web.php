<?php

declare(strict_types=1);

use App\Livewire\DialogueBrowser;
use App\Livewire\GameList;
use App\Models\Game;
use App\Models\Rater;
use Illuminate\Http\Request;

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

Route::get('/', GameList::class)->name('games.index');
Route::get('games/{game:slug}', App\Livewire\GameDetail::class)->name('games.show');
Route::get('dialogue/browser/{gameId?}/{versionId?}', DialogueBrowser::class)->name('dialogue.browser');

Route::get('raters/{rater}', App\Livewire\RaterDetail::class)->name('raters.show');
Route::get('system/status', App\Livewire\SystemStatus::class)->name('system.status');

// Login route
Route::get('login', function () {
    return view('auth.login');
})->name('login');

// Social Authentication Routes
Route::get('auth/telegram', function () {
    return view('auth.telegram-login');
})->name('auth.telegram');
Route::get('auth/{provider}/redirect', [App\Http\Controllers\SocialAuthController::class, 'redirectToProvider'])
    ->name('auth.redirect');
Route::get('auth/{provider}/callback', [App\Http\Controllers\SocialAuthController::class, 'handleProviderCallback'])
    ->name('auth.callback');

// Authenticated VN List Routes
Route::middleware(['auth'])->group(function () {
    Route::get('lists/create', [App\Http\Controllers\VnListController::class, 'create'])->name('vn-lists.create');
    Route::post('lists', [App\Http\Controllers\VnListController::class, 'store'])->name('vn-lists.store');
    Route::get('lists/{vnList}/edit', [App\Http\Controllers\VnListController::class, 'edit'])->name('vn-lists.edit');
    Route::put('lists/{vnList}', [App\Http\Controllers\VnListController::class, 'update'])->name('vn-lists.update');
    Route::delete('lists/{vnList}', [App\Http\Controllers\VnListController::class, 'destroy'])->name('vn-lists.destroy');
    Route::post('lists/{vnList}/toggle-visibility', [App\Http\Controllers\VnListController::class, 'toggleVisibility'])->name('vn-lists.toggle-visibility');

    // Game operations
    Route::post('games/{game:id}/add-to-list', [App\Http\Controllers\VnListController::class, 'addGame'])->name('games.add-to-list');
    Route::post('lists/{vnList}/add-game', [App\Http\Controllers\VnListController::class, 'addToCustomList'])->name('list-entries.add-to-custom');

    // List entries
    Route::put('list-entries/{entry}', [App\Http\Controllers\VnListController::class, 'updateEntry'])->name('list-entries.update');
    Route::post('list-entries/{entry}/move', [App\Http\Controllers\VnListController::class, 'moveGame'])->name('list-entries.move');
    Route::delete('list-entries/{entry}', [App\Http\Controllers\VnListController::class, 'removeGame'])->name('list-entries.destroy');

    // User Game Progress
    Route::put('user-progress/{game:id}', [App\Http\Controllers\UserGameProgressController::class, 'update'])->name('user-progress.update');

    // VN Lists ordering
    Route::post('vn-lists/{vnList}/update-order', [App\Http\Controllers\VnListController::class, 'updateOrder'])->name('vn-lists.update-order');
});

// Public VN List Routes (no auth required)
Route::get('lists/public', [App\Http\Controllers\VnListController::class, 'publicLists'])->name('vn-lists.public');
Route::get('lists', [App\Http\Controllers\VnListController::class, 'index'])->name('vn-lists.index');
Route::get('users/{user}/lists', [App\Http\Controllers\VnListController::class, 'userPublicLists'])->name('vn-lists.user-public');
Route::get('lists/{vnList}', [App\Http\Controllers\VnListController::class, 'show'])->name('vn-lists.show');

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

// Catch-all route for game slugs (must be last and exclude 'lists' path)
Route::get('{game:slug}', function ($slug) {
    return redirect(status: 301)->route('games.show', $slug);
})->where('game:slug', '^(?!lists).*$');

Route::get('by-game-id/{game:game_id}', function ($gameId) {
    $slug = Game::where('game_id', $gameId)->first()?->slug;
    if (! $slug) {
        abort(404);
    }

    return redirect(status: 301)->route('games.show', $slug);
});
