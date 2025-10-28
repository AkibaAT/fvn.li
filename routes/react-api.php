<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\ClickTrackingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DialogueController;
use App\Http\Controllers\GameContentController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\MyGamesController;
use App\Http\Controllers\VnListController;
use App\Http\Middleware\CanEditGame;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| React API Routes (session-based JSON)
|--------------------------------------------------------------------------
|
| These endpoints are consumed by the browser UI and rely on the web
| middleware (session + CSRF). RouteServiceProvider already prefixes
| this file with '/react-api'. Keep stateless APIs in routes/api.php.
|
*/

Route::middleware(['web'])->group(function () {
    // Dialogue JSON
    Route::get('dialogue', [DialogueController::class, 'getDialogueData'])
        ->name('react-api.dialogue.index');
    Route::get('dialogue/options', [DialogueController::class, 'getDialogueOptions'])
        ->name('react-api.dialogue.options');
    Route::get('dialogue/search', [DialogueController::class, 'searchDialogue'])
        ->name('react-api.dialogue.search');
    Route::get('dialogue/search-enhanced', [DialogueController::class, 'searchDialogueEnhanced'])
        ->name('react-api.dialogue.search-enhanced');
    Route::get('dialogue/duplicates', [DialogueController::class, 'duplicateDialogue'])
        ->name('react-api.dialogue.duplicates');
    Route::get('dialogue/version-stats', [DialogueController::class, 'versionStats'])
        ->name('react-api.dialogue.version-stats');

    // Game search/filter and details (keep legacy api.* names)
    Route::get('games/search', [GamesController::class, 'searchGames'])->name('api.games.search');
    Route::get('games/search-enhanced', [GamesController::class, 'searchGamesEnhanced'])->name('api.games.search-enhanced');
    Route::get('search/global', [GamesController::class, 'globalSearch'])->name('api.search.global');
    Route::get('games/{game:slug}/details', [GamesController::class, 'gameDetails'])->name('api.games.details');
    Route::get('games/{game:id}/compare-versions',
        [GamesController::class, 'compareGameVersions'])->name('api.games.compare-versions');

    // Reviews and version stats
    Route::get('games/{game}/reviews', [GamesController::class, 'getGameReviews'])->name('react-api.games.reviews');
    Route::get('games/{game}/versions', [GamesController::class, 'getGameVersions'])->name('react-api.games.versions');
    Route::get('games/{game:slug}/versions/{version}/character-stats',
        [GamesController::class, 'getVersionCharacterStats'])->name('react-api.games.version.character-stats');
    Route::get('games/{game:slug}/versions/{version}/file-stats',
        [GamesController::class, 'getVersionFileStats'])->name('react-api.games.version.file-stats');

    // Click tracking (session-based)
    Route::post('track/custom-link',
        [ClickTrackingController::class, 'trackCustomLink'])->name('react-api.track.custom-link');
    Route::middleware('auth')->group(function () {
        Route::get('games/{game}/stats', [ClickTrackingController::class, 'getGameStats'])->name('api.games.stats');
        Route::get('games/{game}/analytics',
            [ClickTrackingController::class, 'getDailyAnalytics'])->name('api.games.analytics');
    });

    // Auth-protected APIs
    Route::middleware('auth')->group(function () {
        // Dashboard
        Route::get('dashboard/notification-preferences',
            [DashboardController::class, 'getNotificationPreferences'])->name('react-api.dashboard.notifications.get');
        Route::post('dashboard/notification-preferences', [
            DashboardController::class, 'updateNotificationPreferences',
        ])->name('react-api.dashboard.notifications.update');

        Route::post('dashboard/addition-requests', [
            DashboardController::class, 'submitAdditionRequest',
        ])->name('react-api.dashboard.addition-requests.submit');
        Route::get('dashboard/addition-requests', [
            DashboardController::class, 'getUserAdditionRequests',
        ])->name('react-api.dashboard.addition-requests.index');
        Route::post('dashboard/addition-requests/{request}/cancel', [
            DashboardController::class, 'cancelAdditionRequest',
        ])->whereNumber('request')->name('react-api.dashboard.addition-requests.cancel');

        Route::get('dashboard/game-stats',
            [DashboardController::class, 'getUserGameStats'])->name('react-api.dashboard.game-stats');

        // User data export
        Route::get('user/export', [DashboardController::class, 'exportUserData'])->name('react-api.user.export');

        // My Games update
        Route::put('my-games/{game:slug}',
            [MyGamesController::class, 'myGamesUpdate'])->name('react-api.my-games.update');

        // Thumbnail management
        Route::post('my-games/{game:slug}/thumbnail',
            [MyGamesController::class, 'updateThumbnail'])->name('react-api.my-games.thumbnail.update');
        Route::delete('my-games/{game:slug}/thumbnail',
            [MyGamesController::class, 'deleteThumbnail'])->name('react-api.my-games.thumbnail.delete');

        // Screenshot management
        Route::post('my-games/{game:slug}/screenshots',
            [MyGamesController::class, 'uploadScreenshots'])->name('react-api.my-games.screenshots.upload');
        Route::delete('my-games/{game:slug}/screenshots',
            [MyGamesController::class, 'deleteScreenshot'])->name('react-api.my-games.screenshots.delete');
        Route::post('my-games/{game:slug}/screenshots/reorder',
            [MyGamesController::class, 'reorderScreenshots'])->name('react-api.my-games.screenshots.reorder');

        // VN Lists (CRUD) - keep api.* names used in frontend
        Route::post('vn-lists', [VnListController::class, 'storeVnList'])->name('api.vn-lists.store');
        Route::put('vn-lists/{vnList}', [VnListController::class, 'updateVnList'])->name('api.vn-lists.update');
        Route::delete('vn-lists/{vnList}', [VnListController::class, 'destroyVnList'])->name('api.vn-lists.destroy');
        Route::post('vn-lists/{vnList}/toggle-visibility',
            [VnListController::class, 'toggleVnListVisibility'])->name('api.vn-lists.toggle-visibility');
        Route::patch('vn-lists/{vnList}/toggle-all-updates',
            [VnListController::class, 'toggleAllUpdates'])->name('api.vn-lists.toggle-all-updates');

        // VN List entries
        Route::put('list-entries/{entry}',
            [VnListController::class, 'updateListEntry'])->name('api.list-entries.update');
        Route::post('list-entries/{entry}/move',
            [VnListController::class, 'moveListEntry'])->name('api.list-entries.move');
        Route::delete('list-entries/{entry}',
            [VnListController::class, 'removeListEntry'])->name('api.list-entries.destroy');
        Route::post('lists/{vnList}/reorder',
            [VnListController::class, 'reorderListEntries'])->name('api.lists.reorder');

        // Game content editing
        Route::middleware(CanEditGame::class)->group(function () {
            Route::put('games/{game:id}/name', [GameContentController::class, 'updateName'])
                ->name('react-api.games.name.update');
            Route::put('games/{game:id}/content', [GameContentController::class, 'updateContent'])
                ->name('react-api.games.content.update');
            Route::post('games/{game:id}/content/revert', [GameContentController::class, 'revertContent'])
                ->name('react-api.games.content.revert');
            Route::get('games/{game:id}/content/view', [GameContentController::class, 'getContentForView'])
                ->name('react-api.games.content.view');
            Route::put('games/{game:id}/view-mode', [GameContentController::class, 'setViewMode'])
                ->name('react-api.games.content.view-mode');
        });

        // Image uploads for content editor (auth required, but game permission checked in controller)
        Route::post('upload-editor-image', [App\Http\Controllers\EditorUploadController::class, 'uploadEditorImage'])
            ->name('react-api.upload-editor-image');

        // Game operations + user lists (support both api.* and react-api.* where used)
        Route::get('games/{game:id}/lists',
            [VnListController::class, 'getGameLists'])->name('react-api.games.lists');
        Route::post('games/{game:id}/add-to-list',
            [VnListController::class, 'addGameToList'])->name('react-api.games.add-to-list');
        Route::post('games/{game:id}/add-to-list',
            [VnListController::class, 'addGameToList'])->name('api.games.add-to-list');
        Route::post('lists/{vnList}/add-game',
            [VnListController::class, 'addGameToCustomList'])->name('api.list-entries.add-to-custom');
        Route::get('vn-lists', [VnListController::class, 'getUserLists'])->name('api.vn-lists.index');
        Route::get('user/lists', [VnListController::class, 'getUserLists'])->name('react-api.user.lists');

        // User progress
        Route::get('user-progress/{game:id}/status',
            [VnListController::class, 'getUserProgressStatus'])->name('react-api.user-progress.status');
        Route::put('user-progress/{game:id}',
            [VnListController::class, 'updateUserProgress'])->name('api.user-progress.update');
        Route::patch('user-progress/{game:id}/toggle-updates',
            [VnListController::class, 'toggleUserProgressUpdates'])->name('api.user-progress.toggle-updates');

        // Push Subscriptions (moved from api.php; session-based)
        Route::post('push-subscriptions',
            [PushSubscriptionController::class, 'store'])->name('react-api.push-subscriptions.store');
        Route::post('push-subscriptions/verify',
            [PushSubscriptionController::class, 'verify'])->name('react-api.push-subscriptions.verify');
        Route::delete('push-subscriptions',
            [PushSubscriptionController::class, 'destroy'])->name('react-api.push-subscriptions.destroy');

        // Notifications (persistent)
        Route::get('notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('react-api.notifications.index');
        Route::post('notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('react-api.notifications.read');
    });

    // Health/test
    Route::get('test', function () {
        return response()->json([
            'success' => true,
            'message' => 'React API is working',
            'timestamp' => now()->toISOString(),
        ]);
    })->name('react-api.test');
});
