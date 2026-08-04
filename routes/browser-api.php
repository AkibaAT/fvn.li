<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\BugReportController;
use App\Http\Controllers\ClickTrackingController;
use App\Http\Controllers\Dashboard\DashboardAdditionRequestController;
use App\Http\Controllers\Dashboard\DashboardNotificationController;
use App\Http\Controllers\Dashboard\DashboardStatsController;
use App\Http\Controllers\Dashboard\UserDataExportController;
use App\Http\Controllers\DialogueController;
use App\Http\Controllers\DiscordConfigController;
use App\Http\Controllers\EditorUploadController;
use App\Http\Controllers\GameContentController;
use App\Http\Controllers\Games\GamesDisplayController;
use App\Http\Controllers\Games\GamesReviewController;
use App\Http\Controllers\Games\GamesSearchController;
use App\Http\Controllers\Games\GamesVersionController;
use App\Http\Controllers\Games\RouteMapController;
use App\Http\Controllers\MyGamesController;
use App\Http\Controllers\ReviewReportController;
use App\Http\Controllers\UserReviewController;
use App\Http\Controllers\VnLists\VnListCrudController;
use App\Http\Controllers\VnLists\VnListEntryController;
use App\Http\Controllers\VnLists\VnListGameController;
use App\Http\Middleware\CanEditGame;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Browser API Routes (session-based JSON)
|--------------------------------------------------------------------------
|
| These endpoints are consumed by the browser UI and rely on the web
| middleware (session + CSRF). RouteServiceProvider already prefixes
| this file with '/browser-api'. Keep stateless APIs in routes/api.php.
|
*/

Route::middleware(['web'])->group(function () {
    // Dialogue JSON
    Route::get('dialogue', [DialogueController::class, 'getDialogueData'])
        ->name('browser-api.dialogue.index');
    Route::get('dialogue/options', [DialogueController::class, 'getDialogueOptions'])
        ->name('browser-api.dialogue.options');
    Route::get('dialogue/search', [DialogueController::class, 'searchDialogue'])
        ->middleware('throttle:60,1')
        ->name('browser-api.dialogue.search');
    Route::get('dialogue/duplicates', [DialogueController::class, 'duplicateDialogue'])
        ->name('browser-api.dialogue.duplicates');
    Route::get('dialogue/version-stats', [DialogueController::class, 'versionStats'])
        ->name('browser-api.dialogue.version-stats');
    Route::get('dialogue/word-frequency', [DialogueController::class, 'getWordFrequency'])
        ->middleware('throttle:20,1')
        ->name('browser-api.dialogue.word-frequency');

    // Route names use the api.* prefix; the frontend resolves them via Ziggy.
    Route::get('games/search', [GamesSearchController::class, 'searchGames'])->name('api.games.search');
    Route::get('games/search-enhanced', [GamesSearchController::class, 'searchGamesWithFilters'])->name('api.games.search-enhanced');
    Route::get('search/global', [GamesSearchController::class, 'globalSearch'])->name('api.search.global');
    Route::get('games/{game:slug}/details', [GamesDisplayController::class, 'details'])->name('api.games.details');
    Route::get('games/{game:id}/compare-versions',
        [GamesVersionController::class, 'compareVersions'])
        ->whereNumber('game')
        ->name('api.games.compare-versions');

    // Reviews and version stats
    Route::get('games/{game}/reviews', [GamesReviewController::class, 'getGameReviews'])->name('browser-api.games.reviews');
    Route::get('games/{game}/versions', [GamesVersionController::class, 'getGameVersions'])->name('browser-api.games.versions');
    Route::get('games/{game:slug}/versions/{version}/character-stats',
        [GamesVersionController::class, 'getVersionCharacterStats'])->name('browser-api.games.version.character-stats');
    Route::get('games/{game:slug}/versions/{version}/file-stats',
        [GamesVersionController::class, 'getVersionFileStats'])->name('browser-api.games.version.file-stats');
    Route::get('games/{game:slug}/versions/{version}/route-graph',
        [RouteMapController::class, 'getRouteGraph'])->name('browser-api.games.version.route-graph');
    Route::post('games/{game:slug}/versions/{version}/parse-save',
        [RouteMapController::class, 'parseSaveFile'])
        ->middleware('throttle:save-parser')
        ->name('browser-api.games.version.parse-save');

    // Click tracking (session-based)
    Route::post('track/custom-link',
        [ClickTrackingController::class, 'trackCustomLink'])->name('browser-api.track.custom-link');
    Route::middleware('auth')->group(function () {
        Route::get('games/{game}/stats', [ClickTrackingController::class, 'getGameStats'])->name('api.games.stats');
        Route::get('games/{game}/analytics',
            [ClickTrackingController::class, 'getDailyAnalytics'])->name('api.games.analytics');
    });

    // Auth-protected APIs
    Route::middleware('auth')->group(function () {
        // Dashboard
        Route::get('dashboard/notification-preferences',
            [DashboardNotificationController::class, 'getNotificationPreferences'])->name('browser-api.dashboard.notifications.get');
        Route::post('dashboard/notification-preferences', [
            DashboardNotificationController::class, 'updateNotificationPreferences',
        ])->name('browser-api.dashboard.notifications.update');

        Route::post('dashboard/addition-requests', [
            DashboardAdditionRequestController::class, 'submitAdditionRequest',
        ])->name('browser-api.dashboard.addition-requests.submit');
        Route::get('dashboard/addition-requests', [
            DashboardAdditionRequestController::class, 'getUserAdditionRequests',
        ])->name('browser-api.dashboard.addition-requests.index');
        Route::post('dashboard/addition-requests/{request}/cancel', [
            DashboardAdditionRequestController::class, 'cancelAdditionRequest',
        ])->whereNumber('request')->name('browser-api.dashboard.addition-requests.cancel');

        Route::get('dashboard/game-stats',
            [DashboardStatsController::class, 'getUserGameStats'])->name('browser-api.dashboard.game-stats');

        // User data export
        Route::get('user/export', [UserDataExportController::class, 'exportUserData'])->name('browser-api.user.export');

        Route::middleware(CanEditGame::class)->group(function () {
            Route::put('my-games/{game:slug}',
                [MyGamesController::class, 'myGamesUpdate'])->name('browser-api.my-games.update');
            Route::post('my-games/{game:slug}/thumbnail',
                [MyGamesController::class, 'updateThumbnail'])->name('browser-api.my-games.thumbnail.update');
            Route::delete('my-games/{game:slug}/thumbnail',
                [MyGamesController::class, 'deleteThumbnail'])->name('browser-api.my-games.thumbnail.delete');
            Route::post('my-games/{game:slug}/screenshots',
                [MyGamesController::class, 'uploadScreenshots'])->name('browser-api.my-games.screenshots.upload');
            Route::delete('my-games/{game:slug}/screenshots',
                [MyGamesController::class, 'deleteScreenshot'])->name('browser-api.my-games.screenshots.delete');
            Route::post('my-games/{game:slug}/screenshots/reorder',
                [MyGamesController::class, 'reorderScreenshots'])->name('browser-api.my-games.screenshots.reorder');
        });

        // VN Lists (CRUD)
        Route::post('vn-lists', [VnListCrudController::class, 'storeVnList'])->name('api.vn-lists.store');
        Route::put('vn-lists/{vnList}', [VnListCrudController::class, 'updateVnList'])->name('api.vn-lists.update');
        Route::delete('vn-lists/{vnList}', [VnListCrudController::class, 'destroyVnList'])->name('api.vn-lists.destroy');
        Route::post('vn-lists/{vnList}/toggle-visibility',
            [VnListCrudController::class, 'toggleVnListVisibility'])->name('api.vn-lists.toggle-visibility');
        Route::patch('vn-lists/{vnList}/toggle-all-updates',
            [VnListCrudController::class, 'toggleAllUpdates'])->name('api.vn-lists.toggle-all-updates');

        // VN List entries
        Route::put('list-entries/{entry}',
            [VnListEntryController::class, 'updateListEntry'])->name('api.list-entries.update');
        Route::post('list-entries/{entry}/move',
            [VnListEntryController::class, 'moveListEntry'])->name('api.list-entries.move');
        Route::delete('list-entries/{entry}',
            [VnListEntryController::class, 'removeListEntry'])->name('api.list-entries.destroy');
        Route::post('lists/{vnList}/reorder',
            [VnListEntryController::class, 'reorderListEntries'])->name('api.lists.reorder');

        // Game content editing
        Route::middleware(CanEditGame::class)->group(function () {
            Route::put('games/{game:id}/name', [GameContentController::class, 'updateName'])
                ->name('browser-api.games.name.update');
            Route::put('games/{game:id}/content', [GameContentController::class, 'updateContent'])
                ->name('browser-api.games.content.update');
            Route::post('games/{game:id}/content/revert', [GameContentController::class, 'revertContent'])
                ->name('browser-api.games.content.revert');
            Route::get('games/{game:id}/content/view', [GameContentController::class, 'getContentForView'])
                ->name('browser-api.games.content.view');
            Route::put('games/{game:id}/view-mode', [GameContentController::class, 'setViewMode'])
                ->name('browser-api.games.content.view-mode');
        });

        // Image uploads for content editor (auth required, but game permission checked in controller)
        Route::post('upload-editor-image', [EditorUploadController::class, 'uploadEditorImage'])
            ->name('browser-api.upload-editor-image');

        // Game operations + user lists (support both api.* and browser-api.* where used)
        Route::get('games/{game:id}/lists',
            [VnListGameController::class, 'getGameLists'])->name('browser-api.games.lists');
        Route::post('games/{game:id}/add-to-list',
            [VnListGameController::class, 'addGameToList'])->name('browser-api.games.add-to-list');
        Route::post('games/{game:id}/add-to-list',
            [VnListGameController::class, 'addGameToList'])->name('api.games.add-to-list');
        Route::post('lists/{vnList}/add-game',
            [VnListGameController::class, 'addGameToCustomList'])->name('api.list-entries.add-to-custom');
        Route::get('vn-lists', [VnListGameController::class, 'getUserLists'])->name('api.vn-lists.index');
        Route::get('user/lists', [VnListGameController::class, 'getUserLists'])->name('browser-api.user.lists');

        // User progress
        Route::get('user-progress/{game:id}/status',
            [VnListGameController::class, 'getUserProgressStatus'])->name('browser-api.user-progress.status');
        Route::put('user-progress/{game:id}',
            [VnListGameController::class, 'updateUserProgress'])->name('api.user-progress.update');
        Route::patch('user-progress/{game:id}/toggle-updates',
            [VnListGameController::class, 'toggleUserProgressUpdates'])->name('api.user-progress.toggle-updates');

        // Push Subscriptions (moved from api.php; session-based)
        Route::post('push-subscriptions',
            [PushSubscriptionController::class, 'store'])->name('browser-api.push-subscriptions.store');
        Route::post('push-subscriptions/verify',
            [PushSubscriptionController::class, 'verify'])->name('browser-api.push-subscriptions.verify');
        Route::delete('push-subscriptions',
            [PushSubscriptionController::class, 'destroy'])->name('browser-api.push-subscriptions.destroy');

        // User Reviews
        Route::get('user-reviews/{game}', [UserReviewController::class, 'show'])
            ->whereNumber('game')->name('browser-api.user-reviews.show');
        Route::post('user-reviews/{game}', [UserReviewController::class, 'store'])
            ->whereNumber('game')->name('browser-api.user-reviews.store');
        Route::delete('user-reviews/{game}', [UserReviewController::class, 'destroy'])
            ->whereNumber('game')->name('browser-api.user-reviews.destroy');

        // Review Reports
        Route::post('review-reports/{rating}', [ReviewReportController::class, 'store'])
            ->whereNumber('rating')->name('browser-api.review-reports.store');
        Route::get('review-reports', [ReviewReportController::class, 'index'])
            ->name('browser-api.review-reports.index');
        Route::post('review-reports/{report}/resolve', [ReviewReportController::class, 'resolve'])
            ->whereNumber('report')->name('browser-api.review-reports.resolve');

        // Bug Reports
        Route::post('bug-reports', [BugReportController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('browser-api.bug-reports.store');
        Route::get('bug-reports', [BugReportController::class, 'index'])->name('browser-api.bug-reports.index');
        Route::get('bug-reports/{bugReport}', [BugReportController::class, 'show'])->name('browser-api.bug-reports.show');
        Route::post('bug-reports/{bugReport}/comments', [BugReportController::class, 'addComment'])->name('browser-api.bug-reports.comments.store');
        Route::post('bug-reports/{bugReport}/close', [BugReportController::class, 'close'])->name('browser-api.bug-reports.close');

        Route::middleware('discord.server-bot.enabled')->prefix('discord')->group(function () {
            Route::get('guilds', [DiscordConfigController::class, 'guilds'])->name('browser-api.discord.guilds');
            Route::get('rule-metadata', [DiscordConfigController::class, 'ruleMetadata'])->name('browser-api.discord.rule-metadata');
            Route::get('servers', [DiscordConfigController::class, 'servers'])->name('browser-api.discord.servers');
            Route::get('servers/{server}', [DiscordConfigController::class, 'show'])->name('browser-api.discord.servers.show');
            Route::put('servers/{server}/config', [DiscordConfigController::class, 'updateConfig'])->name('browser-api.discord.servers.config');
            Route::get('servers/{server}/overrides', [DiscordConfigController::class, 'overrides'])->name('browser-api.discord.servers.overrides');
            Route::post('servers/{server}/overrides', [DiscordConfigController::class, 'storeOverride'])->name('browser-api.discord.servers.overrides.store');
            Route::put('servers/{server}/overrides/{override}', [DiscordConfigController::class, 'updateOverride'])->name('browser-api.discord.servers.overrides.update');
            Route::delete('servers/{server}/overrides/{override}', [DiscordConfigController::class, 'deleteOverride'])->name('browser-api.discord.servers.overrides.delete');
            Route::post('servers/{server}/preview-embed', [DiscordConfigController::class, 'previewEmbed'])->name('browser-api.discord.servers.preview-embed');
            Route::get('servers/{server}/channels', [DiscordConfigController::class, 'channels'])->name('browser-api.discord.servers.channels');
            Route::get('servers/{server}/roles', [DiscordConfigController::class, 'roles'])->name('browser-api.discord.servers.roles');
            Route::post('servers/{server}/test-notification', [DiscordConfigController::class, 'testNotification'])->name('browser-api.discord.servers.test-notification');
        });
    });

    // Health/test
    Route::get('test', function () {
        return response()->json([
            'success' => true,
            'message' => 'Browser API is working',
            'timestamp' => now()->toISOString(),
        ]);
    })->name('browser-api.test');
});
