<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClickTrackingController;
use App\Http\Controllers\CsrfTokenController;
use App\Http\Controllers\Dashboard\DashboardNotificationController;
use App\Http\Controllers\Dashboard\DigestNotificationController;
use App\Http\Controllers\Dashboard\ItchioGameOwnershipController;
use App\Http\Controllers\Dashboard\UserAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperPortalController;
use App\Http\Controllers\DialogueController;
use App\Http\Controllers\DiscordConfigController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\Games\GamesDisplayController;
use App\Http\Controllers\Games\GamesSearchController;
use App\Http\Controllers\Games\GameTagsController;
use App\Http\Controllers\Games\RouteMapController;
use App\Http\Controllers\GameUrlRedirectController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyGamesController;
use App\Http\Controllers\RatingsController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\UserGameProgressController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\VnLists\PublicVnListController;
use App\Http\Controllers\VnLists\VnListPageController;
use App\Http\Middleware\EnsureGameIsVisible;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('developers')->group(function () {
    Route::get('', [DeveloperPortalController::class, 'index'])->name('developers');
    Route::get('swagger', [DeveloperPortalController::class, 'swagger'])->name('developers.swagger');
    Route::get('openapi.yaml', [DeveloperPortalController::class, 'openapi'])->name('developers.openapi');
});

/*
|--------------------------------------------------------------------------
| Health Check Route
|--------------------------------------------------------------------------
*/

Route::get('health', HealthCheckController::class)
    ->withoutMiddleware(['web'])
    ->name('health');

/*
|--------------------------------------------------------------------------
| CSRF Token Refresh Route
|--------------------------------------------------------------------------
| Returns a fresh CSRF token for long-running tabs where the session may
| have expired. This allows the frontend to recover from 419 errors.
*/

Route::get('csrf-token', CsrfTokenController::class)->name('csrf.token');

/*
|--------------------------------------------------------------------------
| Web Routes (Svelte/Inertia frontend)
|--------------------------------------------------------------------------
*/

Route::get('by-url/{url}', GameUrlRedirectController::class)->where('url', '.*');

// Home route
Route::get('/', [HomeController::class, 'home'])
    ->middleware('cache.public:games')
    ->name('home');

// Games routes
Route::get('games', [GamesSearchController::class, 'index'])
    ->middleware('cache.public:games')
    ->name('games.index');
Route::get('games/random', [GamesSearchController::class, 'randomGame'])
    ->name('games.random');
Route::get('games/{game:slug}', [GamesDisplayController::class, 'show'])
    ->name('games.show')
    ->middleware('cache.public:game');
Route::get('games/{game:slug}/route-map', [RouteMapController::class, 'show'])
    ->middleware(EnsureGameIsVisible::class)
    ->name('games.route-map');

Route::get('tags', [GameTagsController::class, 'index'])
    ->middleware('cache.public:games')
    ->name('tags.index');
Route::get('tags/{tag:slug}', [GameTagsController::class, 'show'])
    ->middleware('cache.public:games')
    ->name('tags.show');

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
    Route::delete('user/account', [UserAccountController::class, 'deleteAccount'])
        ->name('user.account.delete');
    Route::post('user/merge/{provider}', [UserAccountController::class, 'mergeSocialAccounts'])
        ->name('user.merge');
    Route::delete('user/disconnect/{provider}', [UserAccountController::class, 'disconnectSocialAccount'])
        ->name('user.disconnect');
    Route::get('user/notifications/digest/{date}', [DigestNotificationController::class, 'showDigestNotifications'])
        ->name('user.notifications.digest');
    Route::put('user-progress/{game:id}', [UserGameProgressController::class, 'update'])
        ->name('user-progress.update');
    Route::post('user/itchio-games/sync', [ItchioGameOwnershipController::class, 'sync'])
        ->middleware('throttle:6,1')
        ->name('user.itchio-games.sync');

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
Route::get('lists', [VnListPageController::class, 'listsIndex'])
    ->middleware(['auth'])
    ->name('lists.index');
Route::get('lists/create', [VnListPageController::class, 'listCreate'])
    ->middleware(['auth'])
    ->name('lists.create');

// Public VN Lists routes (no auth required) - place before dynamic {vnList} routes
Route::get('lists/public', [PublicVnListController::class, 'publicLists'])
    ->middleware(['throttle:30,1', 'cache.public:lists'])
    ->name('lists.public');
Route::get('users/{user}/lists', [PublicVnListController::class, 'userPublicLists'])
    ->whereNumber('user')
    ->name('lists.user-public');

// Dynamic VN List routes (constrained to numeric IDs)
Route::get('lists/{vnList}/edit', [VnListPageController::class, 'listEdit'])
    ->middleware(['auth'])
    ->whereNumber('vnList')
    ->name('lists.edit');
Route::get('lists/{vnList}', [VnListPageController::class, 'listShow'])
    ->whereNumber('vnList')
    ->name('lists.show');

// System Status
Route::get('system/status', [SystemStatusController::class, 'systemStatus'])
    ->middleware('cache.public')
    ->name('system.status');

// Social Authentication Routes
Route::get('auth/telegram', [SocialAuthController::class, 'showTelegramLogin'])
    ->name('auth.telegram');

Route::post('auth/telegram/callback', [SocialAuthController::class, 'handleProviderCallback'])
    ->defaults('provider', 'telegram')
    ->middleware('throttle:30,1')
    ->name('auth.telegram.callback');

Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirectToProvider'])
    ->whereIn('provider', ['discord', 'google', 'steam', 'telegram', 'itchio'])
    ->middleware('throttle:30,1')
    ->name('auth.redirect');

// Special routes for itch.io
Route::get('auth/itchio/callback', [SocialAuthController::class, 'showItchioCallback'])
    ->name('auth.itchio.callback');

Route::get('auth/itchio/process', [SocialAuthController::class, 'processItchioCallback'])
    ->middleware('throttle:30,1')
    ->name('auth.itchio.process');

Route::get('auth/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback'])
    ->whereIn('provider', ['discord', 'google', 'steam', 'telegram', 'itchio'])
    ->middleware('throttle:30,1')
    ->name('auth.callback');

// Svelte/Inertia Dialogue Browser + JSON API
// game slug is required so public dialogue browsing does not expose an ID-to-slug lookup.
Route::get('dialogue/browser/{game:slug}/{versionId?}', [DialogueController::class, 'dialogueBrowser'])
    ->where(['versionId' => '[0-9]+'])
    ->middleware([EnsureGameIsVisible::class, 'cache.public:game'])
    ->name('dialogue.browser');
// JSON endpoints moved to routes/browser-api.php

// Ratings domain (Svelte/Inertia) scaffolds
Route::get('ratings', [RatingsController::class, 'ratingsIndex'])
    ->middleware('cache.public:ratings')
    ->name('ratings.index');
Route::get('reviews/{rating}', [RatingsController::class, 'reviewShow'])
    ->whereNumber('rating')
    ->name('reviews.show');
Route::get('users/{user}/reviews', [RatingsController::class, 'userReviews'])
    ->whereNumber('user')
    ->name('users.reviews');
Route::get('raters/{rater}', [RatingsController::class, 'raterShow'])
    ->whereNumber('rater')
    ->middleware('cache.public:ratings')
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
    Route::get('my/games/{game:slug}/versions/{version}/optimized/download', [MyGamesController::class, 'downloadOptimizedArchive'])->name('my-games.optimized-download');
});

Route::middleware('auth')->group(function () {
    Route::get('discord/user-install', [DashboardNotificationController::class, 'redirectToDiscordUserInstall'])
        ->name('dashboard.discord.user-install');
    Route::get('discord/user-install/callback', [DashboardNotificationController::class, 'handleDiscordUserInstallCallback'])
        ->name('dashboard.discord.user-install.callback');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('discord', [DiscordConfigController::class, 'index'])->name('dashboard.discord.index');
    Route::get('discord/install/callback', [DiscordConfigController::class, 'handleBotInstallCallback'])->name('dashboard.discord.install.callback');
    Route::get('discord/install/{guild}', [DiscordConfigController::class, 'redirectToBotInstall'])->name('dashboard.discord.install');
    Route::get('discord/{server}', [DiscordConfigController::class, 'server'])->whereNumber('server')->name('dashboard.discord.server');
});

// Browser API JSON for versions moved to browser-api.php

// JSON list APIs moved to browser-api.php

// RSS Feeds
Route::get('feed/new', [FeedController::class, 'newGames'])->middleware('cache.public:games')->name('feed.new');
Route::get('feed/updates', [FeedController::class, 'updatedGames'])->middleware('cache.public:games')->name('feed.updates');

// Click tracking routes (public)
Route::get('track/link', [ClickTrackingController::class, 'redirectCustomLink'])
    ->name('track.custom-link');
Route::get('track/external', [ClickTrackingController::class, 'redirectExternalProject'])
    ->name('track.external-project');
// Click tracking JSON moved to browser-api.php
