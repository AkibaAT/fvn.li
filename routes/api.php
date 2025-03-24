<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DiscordBotController;
use App\Http\Controllers\Api\DiscordNotificationsController;
use App\Http\Controllers\Api\PushSubscriptionController;
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
    Route::post('status', [DiscordNotificationsController::class, 'recordDeliveryStatus']);
});

// User notification routes
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::post('subscribers', [App\Http\Controllers\Api\UserNotificationsController::class, 'getGameSubscribers']);
    Route::post('record', [App\Http\Controllers\Api\UserNotificationsController::class, 'recordNotification']);
});

// Push notification subscription routes
Route::middleware('web')->group(function () {
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy']);
});
