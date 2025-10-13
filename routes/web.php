<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DialogueController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyGamesController;
use App\Http\Controllers\RatingsController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\UserGameProgressController;
use App\Http\Controllers\VnListController;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Health Check Route
|--------------------------------------------------------------------------
*/

Route::get('health', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();

        // Check Redis connection
        cache()->store('redis')->put('health_check', 'ok', 1);
        cache()->store('redis')->get('health_check');

        return response()->json(['status' => 'ok'], 200);
    } catch (Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 503);
    }
});

/*
|--------------------------------------------------------------------------
| Web Routes (React/Inertia frontend)
|--------------------------------------------------------------------------
*/

Route::get('by-url/{url}', function (Request $request, $url) {
    $game = Game::firstWhere('url', $url);

    if (! $game) {
        $decodedUrl = urldecode($url);
        $game = Game::firstWhere('url', $decodedUrl);
    }

    if (! $game && str_starts_with($url, 'https:/') && ! str_starts_with($url, 'https://')) {
        $fixedUrl = str_replace('https:/', 'https://', $url);
        $game = Game::firstWhere('url', $fixedUrl);
    }

    if (! $game) {
        $fullPath = $request->getPathInfo();
        $urlPart = substr($fullPath, strlen('/by-url/'));
        $game = Game::firstWhere('url', $urlPart);
    }

    if (! $game) {
        abort(404);
    }

    return redirect(status: 301)->route('games.show', $game);
})->where('url', '.*');

// Home route
Route::get('/', [HomeController::class, 'home'])
    ->name('home');

// Games routes
Route::get('games', [GamesController::class, 'gamesIndex'])
    ->name('games.index');
Route::get('games/{game:slug}', [GamesController::class, 'gameShow'])
    ->name('games.show')
    ->middleware('track.page.views');

// Auth routes
Route::get('login', [AuthController::class, 'login'])
    ->name('login');
Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// User Dashboard
Route::get('dashboard', [DashboardController::class, 'dashboard'])
    ->middleware(['auth'])
    ->name('dashboard');

// User Account Management
Route::middleware(['auth'])->group(function () {
    Route::delete('user/account', [DashboardController::class, 'deleteAccount'])
        ->name('user.account.delete');
    Route::post('user/merge/{provider}', [DashboardController::class, 'mergeSocialAccounts'])
        ->name('user.merge');
    Route::delete('user/disconnect/{provider}', [DashboardController::class, 'disconnectSocialAccount'])
        ->name('user.disconnect');
    Route::get('user/notifications/digest/{date}', [DashboardController::class, 'showDigestNotifications'])
        ->name('user.notifications.digest');
    Route::post('users/dashboard/version-comparison', [DashboardController::class, 'getVersionComparison'])
        ->name('users.dashboard.version-comparison');
    Route::put('user-progress/{game:id}', [UserGameProgressController::class, 'update'])
        ->name('user-progress.update');
});

// VN Lists routes
Route::get('lists', [VnListController::class, 'listsIndex'])
    ->middleware(['auth'])
    ->name('lists.index');
Route::get('lists/create', [VnListController::class, 'listCreate'])
    ->middleware(['auth'])
    ->name('lists.create');

// Public VN Lists routes (no auth required) - place before dynamic {vnList} routes
Route::get('lists/public', [VnListController::class, 'publicLists'])
    ->name('lists.public');
Route::get('users/{user}/lists', [VnListController::class, 'userPublicLists'])
    ->name('lists.user-public');

// Dynamic VN List routes (constrained to numeric IDs)
Route::get('lists/{vnList}/edit', [VnListController::class, 'listEdit'])
    ->middleware(['auth'])
    ->whereNumber('vnList')
    ->name('lists.edit');
Route::get('lists/{vnList}', [VnListController::class, 'listShow'])
    ->whereNumber('vnList')
    ->name('lists.show');

// System Status
Route::get('system/status', [SystemStatusController::class, 'systemStatus'])
    ->name('system.status');

// Social Authentication Routes
Route::get('auth/telegram', function () {
    return Inertia::render('auth/telegram-login', [
        'metaTags' => ['title' => 'Login with Telegram'],
    ]);
})->name('auth.telegram');

Route::get('auth/{provider}/redirect', [App\Http\Controllers\SocialAuthController::class, 'redirectToProvider'])
    ->name('auth.redirect');

// Special routes for itch.io
Route::get('auth/itchio/callback', function () {
    return Inertia::render('auth/itchio-callback', [
        'metaTags' => ['title' => 'Completing itch.io Login'],
    ]);
})->name('auth.itchio.callback');

Route::get('auth/itchio/process', function () {
    return app()->make(\App\Http\Controllers\SocialAuthController::class)->handleProviderCallback('itchio');
})->name('auth.itchio.process');

Route::get('auth/{provider}/callback', [App\Http\Controllers\SocialAuthController::class, 'handleProviderCallback'])
    ->name('auth.callback');

// Debug-only auth helpers (local/development only)
if (app()->environment(['local', 'development'])) {
    Route::get('__debug/login-7', function () {
        Auth::loginUsingId(7);

        return redirect('/my/games');
    })->name('debug.login-7');

    Route::get('__debug/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('debug.logout');
}

// React/Inertia Dialogue Browser + JSON API
// gameId is now required - dialogue browser is only accessible from game detail page
Route::get('dialogue/browser/{gameId}/{versionId?}', [DialogueController::class, 'dialogueBrowser'])
    ->where(['gameId' => '[0-9]+', 'versionId' => '[0-9]+'])
    ->name('dialogue.browser');
// JSON endpoints moved to routes/react-api.php

// Ratings domain (React/Inertia) scaffolds
Route::get('ratings', [RatingsController::class, 'ratingsIndex'])
    ->name('ratings.index');
Route::get('raters/{rater}', [RatingsController::class, 'raterShow'])
    ->whereNumber('rater')
    ->name('raters.show');

// Rating history JSON for React modal
Route::get('raters/{rater}/games/{game}/history', [RatingsController::class, 'getRatingHistory'])
    ->whereNumber('rater')
    ->whereNumber('game')
    ->name('raters.games.history');

// Manage My Games (React/Inertia pages)
Route::middleware('auth')->group(function () {
    Route::get('my/games', [MyGamesController::class, 'myGamesIndex'])->name('my-games.index');
    Route::get('my/games/{game:slug}/edit', [MyGamesController::class, 'myGamesEdit'])->name('my-games.edit');
});

// Use slug for these endpoints to match Show.tsx paths
// React API JSON for versions moved to react-api.php

// JSON list APIs moved to react-api.php

// Click tracking routes (public)
Route::get('track/link', [App\Http\Controllers\ClickTrackingController::class, 'redirectCustomLink'])
    ->name('track.custom-link');
Route::get('track/external', [App\Http\Controllers\ClickTrackingController::class, 'redirectExternalProject'])
    ->name('track.external-project');
// Click tracking JSON moved to react-api.php
