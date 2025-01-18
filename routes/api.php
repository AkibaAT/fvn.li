<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DiscordBotController;
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
