<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DiscordBotController;
use App\Http\Controllers\Api\DiscordBotServerController;
use App\Http\Controllers\Api\DiscordNotificationHistoryController;
use App\Http\Controllers\Api\DiscordNotificationsController;
use App\Http\Controllers\Api\DiscordServerController;
use App\Http\Controllers\Api\DiscordSubscriptionController;
use App\Http\Controllers\Api\GameReviewsController;
use App\Http\Controllers\Api\RenpyAnalyzerController;
use App\Http\Controllers\Api\UserNotificationsController;
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

// Discord bot game search
Route::middleware(['auth:sanctum', 'sanctum.token:discord-bot'])->prefix('discord')->group(function () {
    Route::post('search', [DiscordBotController::class, 'searchGames']);
});

// Bot API routes (for migration and ongoing integration)
Route::middleware(['auth:sanctum', 'sanctum.token:discord-bot'])->prefix('bot')->group(function () {
    Route::post('find-by-url', [DiscordBotController::class, 'findByUrl']);
    Route::post('bulk-find-by-url', [DiscordBotController::class, 'bulkFindByUrl']);
    Route::get('games/{id}', [DiscordBotController::class, 'getGame']);
});

// Discord notification routes (per-user DMs, channel announcements, admin alerts)
Route::middleware(['auth:sanctum', 'sanctum.token:discord-notifications'])->prefix('discord-notifications')->group(function () {
    Route::get('pending', [DiscordNotificationsController::class, 'getPendingNotifications']);
    Route::post('status', [DiscordNotificationsController::class, 'recordDeliveryStatus']);
    Route::get('channel-updates', [DiscordNotificationsController::class, 'getChannelUpdates']);
    Route::post('channel-status', [DiscordNotificationsController::class, 'recordChannelDeliveryStatus']);
    Route::get('addition-requests', [DiscordNotificationsController::class, 'getPendingAdditionRequests']);
    Route::get('review-reports', [DiscordNotificationsController::class, 'getPendingReviewReports']);
});

// User notification service routes
Route::middleware(['auth:sanctum', 'sanctum.token:notifications'])->prefix('notifications')->group(function () {
    Route::post('subscribers', [UserNotificationsController::class, 'getGameSubscribers']);
    Route::post('record', [UserNotificationsController::class, 'recordNotification']);
});

// Push notification subscription routes moved to browser-api (session-based)

// Multi-server Discord management routes
Route::middleware(['discord.server-bot.enabled', 'auth:sanctum'])->prefix('discord-servers')->group(function () {
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

// Bot-facing server notification delivery
Route::middleware(['discord.server-bot.enabled', 'auth:sanctum', 'sanctum.token:discord-bot'])->prefix('bot/servers')->group(function () {
    Route::get('pending-notifications', [DiscordBotServerController::class, 'pendingNotifications']);
    Route::post('notifications/{notification}/delivered', [DiscordBotServerController::class, 'markDelivered']);
    Route::post('notifications/{notification}/failed', [DiscordBotServerController::class, 'markFailed']);
    Route::post('sync-channels', [DiscordBotServerController::class, 'syncChannels']);
    Route::post('sync-members', [DiscordBotServerController::class, 'syncMembers']);
    Route::post('reconcile-guilds', [DiscordBotServerController::class, 'reconcileGuilds']);
    Route::post('bot-joined', [DiscordBotServerController::class, 'botJoined']);
    Route::post('{server}/bot-left', [DiscordBotServerController::class, 'botLeft']);
});

// Game reviews API for desktop client
Route::get('game-reviews', [GameReviewsController::class, 'getGameReviews']);
Route::get('game-reviews/paginated', [GameReviewsController::class, 'getPaginatedReviews']);

Route::post('renpy-analyzer/analyze', [RenpyAnalyzerController::class, 'analyze']);
