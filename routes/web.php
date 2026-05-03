<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClickTrackingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DialogueController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\Games\RouteMapController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyGamesController;
use App\Http\Controllers\RatingsController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\UserGameProgressController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\VnListController;
use App\Models\Game;
use Illuminate\Http\Request;
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
| CSRF Token Refresh Route
|--------------------------------------------------------------------------
| Returns a fresh CSRF token for long-running tabs where the session may
| have expired. This allows the frontend to recover from 419 errors.
*/

Route::get('csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.token');

/*
|--------------------------------------------------------------------------
| Web Routes (Svelte/Inertia frontend)
|--------------------------------------------------------------------------
*/

Route::get('by-url/{url}', function (Request $request, $url) {
    $game = Game::byUrl($url)->first();

    if (! $game) {
        $decodedUrl = urldecode($url);
        $game = Game::byUrl($decodedUrl)->first();
    }

    if (! $game && str_starts_with($url, 'https:/') && ! str_starts_with($url, 'https://')) {
        $fixedUrl = str_replace('https:/', 'https://', $url);
        $game = Game::byUrl($fixedUrl)->first();
    }

    if (! $game) {
        $fullPath = $request->getPathInfo();
        $urlPart = substr($fullPath, strlen('/by-url/'));
        $game = Game::byUrl($urlPart)->first();
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
Route::get('games/random', [GamesController::class, 'randomGame'])
    ->name('games.random');
Route::get('games/{game:slug}', [GamesController::class, 'gameShow'])
    ->name('games.show')
    ->middleware('track.page.views');
Route::get('games/{game:slug}/route-map', [RouteMapController::class, 'show'])
    ->name('games.route-map');

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

    // User Preferences - Language Preferences
    Route::get('user/language-preferences', [UserPreferencesController::class, 'getLanguagePreferences'])
        ->name('user.language-preferences.index');
    Route::put('user/language-preferences', [UserPreferencesController::class, 'updateLanguagePreferences'])
        ->name('user.language-preferences.update');

    // User Preferences - Excluded Tags
    Route::get('user/excluded-tags', [UserPreferencesController::class, 'getExcludedTags'])
        ->name('user.excluded-tags.index');
    Route::put('user/excluded-tags', [UserPreferencesController::class, 'updateExcludedTags'])
        ->name('user.excluded-tags.update');

    // User Preferences - Ignored Games
    Route::get('user/ignored-games', [UserPreferencesController::class, 'getIgnoredGames'])
        ->name('user.ignored-games.index');
    Route::post('user/ignored-games', [UserPreferencesController::class, 'ignoreGame'])
        ->name('user.ignored-games.store');
    Route::delete('user/ignored-games', [UserPreferencesController::class, 'unignoreGame'])
        ->name('user.ignored-games.destroy');
    Route::post('user/ignored-games/toggle', [UserPreferencesController::class, 'toggleIgnoreGame'])
        ->name('user.ignored-games.toggle');
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

Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirectToProvider'])
    ->name('auth.redirect');

// Special routes for itch.io
Route::get('auth/itchio/callback', function () {
    return Inertia::render('auth/itchio-callback', [
        'metaTags' => ['title' => 'Completing itch.io Login'],
    ]);
})->name('auth.itchio.callback');

Route::get('auth/itchio/process', function () {
    return app()->make(SocialAuthController::class)->handleProviderCallback('itchio');
})->name('auth.itchio.process');

Route::get('auth/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback'])
    ->name('auth.callback');

// Svelte/Inertia Dialogue Browser + JSON API
// gameId is now required - dialogue browser is only accessible from game detail page
Route::get('dialogue/browser/{gameId}', [DialogueController::class, 'dialogueBrowser'])
    ->where(['gameId' => '[0-9]+'])
    ->name('dialogue.browser');
// JSON endpoints moved to routes/browser-api.php

// Ratings domain (Svelte/Inertia) scaffolds
Route::get('ratings', [RatingsController::class, 'ratingsIndex'])
    ->name('ratings.index');
Route::get('reviews/{rating}', [RatingsController::class, 'reviewShow'])
    ->whereNumber('rating')
    ->name('reviews.show');
Route::get('users/{user}/reviews', [RatingsController::class, 'userReviews'])
    ->whereNumber('user')
    ->name('users.reviews');
Route::get('raters/{rater}', [RatingsController::class, 'raterShow'])
    ->whereNumber('rater')
    ->name('raters.show');

// Rating history JSON for browser modal
Route::get('raters/{rater}/games/{game}/history', [RatingsController::class, 'getRatingHistory'])
    ->whereNumber('rater')
    ->whereNumber('game')
    ->name('raters.games.history');

// Manage My Games (Svelte/Inertia pages)
Route::middleware('auth')->group(function () {
    Route::get('my/games', [MyGamesController::class, 'myGamesIndex'])->name('my-games.index');
    Route::get('my/games/{game:slug}/edit', [MyGamesController::class, 'myGamesEdit'])->name('my-games.edit');
});

// Use slug for these endpoints to match Svelte game page paths
// Browser API JSON for versions moved to browser-api.php

// JSON list APIs moved to browser-api.php

// RSS Feeds
Route::get('feed/new', [FeedController::class, 'newGames'])->name('feed.new');
Route::get('feed/updates', [FeedController::class, 'updatedGames'])->name('feed.updates');

// Click tracking routes (public)
Route::get('track/link', [ClickTrackingController::class, 'redirectCustomLink'])
    ->name('track.custom-link');
Route::get('track/external', [ClickTrackingController::class, 'redirectExternalProject'])
    ->name('track.external-project');
// Click tracking JSON moved to browser-api.php
