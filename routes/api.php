<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DiscordBotController;
use App\Http\Controllers\Api\DiscordNotificationHistoryController;
use App\Http\Controllers\Api\DiscordNotificationsController;
use App\Http\Controllers\Api\DiscordServerController;
use App\Http\Controllers\Api\DiscordSubscriptionController;
use App\Http\Controllers\Api\GameReviewsController;
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

// Legacy Discord bot routes
Route::middleware('auth:sanctum')->prefix('discord')->group(function () {
    Route::post('search', [DiscordBotController::class, 'searchGames']);
    Route::post('updates', [DiscordBotController::class, 'getUpdates']);
    Route::post('subscribe', [DiscordBotController::class, 'subscribe']);
    Route::post('unsubscribe', [DiscordBotController::class, 'unsubscribe']);
});

// New Discord notification routes
Route::middleware('auth:sanctum')->prefix('discord-notifications')->group(function () {
    Route::get('pending', [DiscordNotificationsController::class, 'getPendingNotifications']);
    Route::get('addition-requests', [DiscordNotificationsController::class, 'getPendingAdditionRequests']);
    Route::post('status', [DiscordNotificationsController::class, 'recordDeliveryStatus']);
});

// User notification routes
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::post('subscribers', [App\Http\Controllers\Api\UserNotificationsController::class, 'getGameSubscribers']);
    Route::post('record', [App\Http\Controllers\Api\UserNotificationsController::class, 'recordNotification']);
});

// Push notification subscription routes moved to react-api (session-based)

// Multi-server Discord management routes
Route::middleware('auth:sanctum')->prefix('discord-servers')->group(function () {
    // Server management
    Route::post('register', [DiscordServerController::class, 'register']);
    Route::get('', [DiscordServerController::class, 'index']);
    Route::get('{server}', [DiscordServerController::class, 'show']);
    Route::post('{server}/config', [DiscordServerController::class, 'updateConfig']);
    Route::delete('{server}', [DiscordServerController::class, 'destroy']);
    Route::get('{server}/stats', [DiscordServerController::class, 'stats']);

    // Game subscriptions
    Route::post('{server}/subscribe', [DiscordSubscriptionController::class, 'subscribeGame']);
    Route::delete('{server}/games/{game}', [DiscordSubscriptionController::class, 'unsubscribeGame']);
    Route::get('{server}/subscriptions', [DiscordSubscriptionController::class, 'listSubscriptions']);
    Route::post('{server}/bulk-subscribe', [DiscordSubscriptionController::class, 'bulkSubscribe']);

    // Game metadata management (per-server)
    Route::get('{server}/games/{game}/metadata', [DiscordSubscriptionController::class, 'getGameMetadata']);
    Route::post('{server}/games/{game}/metadata', [DiscordSubscriptionController::class, 'updateGameMetadata']);
    Route::post('{server}/games/{game}/rating', [DiscordSubscriptionController::class, 'updateGameRating']);

    // Tag subscriptions
    Route::post('{server}/subscribe-tag', [DiscordSubscriptionController::class, 'subscribeTag']);
    Route::delete('{server}/tags/{tagName}', [DiscordSubscriptionController::class, 'unsubscribeTag']);
    Route::get('{server}/tags', [DiscordSubscriptionController::class, 'listTags']);

    // Notification history
    Route::get('{server}/notifications', [DiscordNotificationHistoryController::class, 'index']);
    Route::get('{server}/notifications/{notification}', [DiscordNotificationHistoryController::class, 'show']);
    Route::get('{server}/notifications-stats', [DiscordNotificationHistoryController::class, 'stats']);
    Route::post('{server}/notifications/{notification}/resend', [DiscordNotificationHistoryController::class, 'resend']);
    Route::post('{server}/test-notification', [DiscordNotificationHistoryController::class, 'sendTest']);
    Route::delete('{server}/notifications/clear', [DiscordNotificationHistoryController::class, 'clear']);
});

// Game reviews API for desktop client
Route::get('game-reviews', [GameReviewsController::class, 'getGameReviews']);
Route::get('game-reviews/paginated', [GameReviewsController::class, 'getPaginatedReviews']);
