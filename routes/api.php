<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DiscordBotController;
use App\Http\Controllers\Api\GameListController;
use App\Http\Controllers\Api\GameReviewsController;
use App\Http\Controllers\Api\GameVersionStatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('discord')->group(function () {
    Route::post('search', [DiscordBotController::class, 'searchGames']);
    Route::post('updates', [DiscordBotController::class, 'getUpdates']);
    Route::post('subscribe', [DiscordBotController::class, 'subscribe']);
    Route::post('unsubscribe', [DiscordBotController::class, 'unsubscribe']);
});

Route::middleware(['web'])->group(function () {
    Route::get('games', [GameListController::class, 'index'])
        ->name('api.games.index');

    Route::get('games/{game:id}/reviews', [GameReviewsController::class, 'index'])
        ->name('api.games.reviews');

    Route::get('games/{game:id}/versions', [GameVersionStatsController::class, 'versionHistory'])
        ->name('api.games.versions');

    Route::get('games/{game:id}/versions/{version:id}/character-stats', [GameVersionStatsController::class, 'characterStats'])
        ->name('api.games.versions.character-stats');

    Route::get('games/{game:id}/versions/{version:id}/file-stats', [GameVersionStatsController::class, 'fileStats'])
        ->name('api.games.versions.file-stats');
});
