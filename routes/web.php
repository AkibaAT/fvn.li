<?php

declare(strict_types=1);

use App\Livewire\DialogueBrowser;
use App\Livewire\GameList;
use App\Models\Game;
use App\Models\Rater;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    // If user is already authenticated, redirect to home page
    if (Auth::check()) {
        return redirect()->route('games.index');
    }

    // Store the previous URL as the intended destination after login
    // Only if not already set by the middleware and the previous URL is not the login page itself
    $previousUrl = url()->previous();
    if (! session()->has('url.intended') && ! str_contains($previousUrl, route('login'))) {
        session()->put('url.intended', $previousUrl);
    }

    return view('users.auth.login', ['metaTags' => ['title' => 'Log in'], 'noindex' => true]);
})->name('login');

// Social Authentication Routes
Route::get('auth/telegram', function () {
    return view('users.auth.telegram-login');
})->name('auth.telegram');

Route::get('auth/{provider}/redirect', [App\Http\Controllers\SocialAuthController::class, 'redirectToProvider'])
    ->name('auth.redirect');

// Special routes for itch.io
Route::get('auth/itchio/callback', function () {
    return view('users.auth.itchio-callback');
})->name('auth.itchio.callback');

Route::get('auth/itchio/process', function () {
    return app()->make(\App\Http\Controllers\SocialAuthController::class)->handleProviderCallback('itchio');
})->name('auth.itchio.process');

Route::get('auth/{provider}/callback', [App\Http\Controllers\SocialAuthController::class, 'handleProviderCallback'])
    ->name('auth.callback');

// Authenticated VN List Routes
Route::middleware(['auth'])->group(function () {
    Route::get('user/dashboard',
        [App\Http\Controllers\UserDashboardController::class, 'show'])->name('user.dashboard.show');
    Route::get('user/notifications/digest/{date}', [
        App\Http\Controllers\UserDashboardController::class, 'showDigestNotifications',
    ])->name('user.notifications.digest');
    Route::delete('user/account',
        [App\Http\Controllers\UserDashboardController::class, 'deleteAccount'])->name('user.delete');
    Route::post('user/merge/{provider}',
        [App\Http\Controllers\UserDashboardController::class, 'mergeSocialAccounts'])->name('user.merge');
    Route::delete('user/disconnect/{provider}',
        [App\Http\Controllers\UserDashboardController::class, 'disconnectSocialAccount'])->name('user.disconnect');
    Route::get('user/export', [App\Http\Controllers\UserDashboardController::class, 'exportData'])->name('user.export');
    Route::put('user/notifications', [
        App\Http\Controllers\UserDashboardController::class, 'updateNotificationPreferences',
    ])->name('user.dashboard.notifications.update');
    Route::post('users/dashboard/version-comparison', [
        App\Http\Controllers\UserDashboardController::class, 'getVersionComparison',
    ])->name('users.dashboard.version-comparison');

    Route::get('lists', [App\Http\Controllers\VnListController::class, 'index'])->name('vn-lists.index');
    Route::get('lists/create', [App\Http\Controllers\VnListController::class, 'create'])->name('vn-lists.create');
    Route::post('lists', [App\Http\Controllers\VnListController::class, 'store'])->name('vn-lists.store');
    Route::get('lists/{vnList}/edit', [App\Http\Controllers\VnListController::class, 'edit'])->name('vn-lists.edit');
    Route::put('lists/{vnList}', [App\Http\Controllers\VnListController::class, 'update'])->name('vn-lists.update');
    Route::delete('lists/{vnList}',
        [App\Http\Controllers\VnListController::class, 'destroy'])->name('vn-lists.destroy');
    Route::post('lists/{vnList}/toggle-visibility',
        [App\Http\Controllers\VnListController::class, 'toggleVisibility'])->name('vn-lists.toggle-visibility');

    // Game operations
    Route::post('games/{game:id}/add-to-list',
        [App\Http\Controllers\VnListController::class, 'addGame'])->name('games.add-to-list');
    Route::post('lists/{vnList}/add-game',
        [App\Http\Controllers\VnListController::class, 'addToCustomList'])->name('list-entries.add-to-custom');

    // List entries
    Route::put('list-entries/{entry}',
        [App\Http\Controllers\VnListController::class, 'updateEntry'])->name('list-entries.update');
    Route::post('list-entries/{entry}/move',
        [App\Http\Controllers\VnListController::class, 'moveGame'])->name('list-entries.move');
    Route::delete('list-entries/{entry}',
        [App\Http\Controllers\VnListController::class, 'removeGame'])->name('list-entries.destroy');
    Route::patch('lists/{vnList}/toggle-all-updates', function (Request $request, \App\Models\VnList $vnList) {
        return app()->make(App\Http\Controllers\VnListController::class)->toggleAllUpdates($request, $vnList);
    })->name('vn-lists.toggle-all-updates');

    // User Game Progress
    Route::put('user-progress/{game:id}',
        [App\Http\Controllers\UserGameProgressController::class, 'update'])->name('user-progress.update');
    Route::patch('user-progress/{game:id}/toggle-updates', function (Request $request, Game $game) {
        return app()->make(App\Http\Controllers\UserGameProgressController::class)->toggleUpdates($request, $game);
    })->name('user-progress.toggle-updates');

    // VN Lists ordering
    Route::post('vn-lists/{vnList}/update-order',
        [App\Http\Controllers\VnListController::class, 'updateOrder'])->name('vn-lists.update-order');
});

// Public VN List Routes (no auth required)
Route::get('lists/public', [App\Http\Controllers\VnListController::class, 'publicLists'])->name('vn-lists.public');
Route::get('users/{user}/lists',
    [App\Http\Controllers\VnListController::class, 'userPublicLists'])->name('vn-lists.user-public');
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
