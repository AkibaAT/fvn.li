<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\ListController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\PrivateTagController;
use App\Http\Controllers\Api\V1\TrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.api.token', 'throttle:user-api'])->group(function () {
    Route::middleware('sanctum.token:catalog:read')->group(function () {
        Route::get('games', [GameController::class, 'index']);
        Route::get('games/resolve', [GameController::class, 'resolve']);
        Route::post('games/resolve', [GameController::class, 'resolveBatch'])->middleware('throttle:user-api-lookup');
        Route::get('games/{game}', [GameController::class, 'show'])->whereNumber('game');
    });

    Route::middleware('sanctum.token:tags:read')->group(function () {
        Route::get('private-tags', [PrivateTagController::class, 'index']);
        Route::get('private-tags/{tag}/games', [PrivateTagController::class, 'games'])->whereNumber('tag');
        Route::get('games/{game}/private-tags', [PrivateTagController::class, 'forGame'])->whereNumber('game');
    });
    Route::middleware(['sanctum.token:tags:write', 'throttle:user-api-write'])->group(function () {
        Route::post('private-tags', [PrivateTagController::class, 'store']);
        Route::patch('private-tags/{tag}', [PrivateTagController::class, 'update'])->whereNumber('tag');
        Route::delete('private-tags/{tag}', [PrivateTagController::class, 'destroy'])->whereNumber('tag');
        Route::put('games/{game}/private-tags/{tag}', [PrivateTagController::class, 'attach'])->whereNumber('game')->whereNumber('tag');
        Route::delete('games/{game}/private-tags/{tag}', [PrivateTagController::class, 'detach'])->whereNumber('game')->whereNumber('tag');
    });

    Route::middleware('sanctum.token:lists:read')->group(function () {
        Route::get('lists', [ListController::class, 'index']);
        Route::get('lists/{list}', [ListController::class, 'show'])->whereNumber('list');
        Route::get('lists/{list}/entries', [ListController::class, 'entries'])->whereNumber('list');
    });
    Route::middleware(['sanctum.token:lists:write', 'throttle:user-api-write'])->group(function () {
        Route::post('lists', [ListController::class, 'store']);
        Route::patch('lists/{list}', [ListController::class, 'update'])->whereNumber('list');
        Route::delete('lists/{list}', [ListController::class, 'destroy'])->whereNumber('list');
        Route::put('lists/{list}/games/{game}', [ListController::class, 'addGame'])->whereNumber('list')->whereNumber('game');
        Route::delete('lists/{list}/games/{game}', [ListController::class, 'removeGame'])->whereNumber('list')->whereNumber('game');
        Route::patch('lists/{list}/entries/{entry}', [ListController::class, 'updateEntry'])->whereNumber('list')->whereNumber('entry');
        Route::post('lists/{list}/entries/{entry}/move', [ListController::class, 'moveEntry'])->whereNumber('list')->whereNumber('entry');
        Route::post('lists/{list}/entries/reorder', [ListController::class, 'reorder'])->whereNumber('list');
    });

    Route::middleware('sanctum.token:tracking:read')->group(function () {
        Route::get('me/tracked-games', [TrackingController::class, 'index']);
        Route::get('me/tracked-games/{game}', [TrackingController::class, 'show'])->whereNumber('game');
    });
    Route::patch('me/tracked-games/{game}', [TrackingController::class, 'update'])
        ->middleware(['sanctum.token:tracking:write', 'throttle:user-api-write'])->whereNumber('game');

    Route::middleware('sanctum.token:preferences:read')->group(function () {
        Route::get('me/search-preferences', [PreferenceController::class, 'show']);
        Route::get('me/ignored-games', [PreferenceController::class, 'ignored']);
    });
    Route::middleware(['sanctum.token:preferences:write', 'throttle:user-api-write'])->group(function () {
        Route::patch('me/search-preferences', [PreferenceController::class, 'update']);
        Route::put('me/ignored-games/{game}', [PreferenceController::class, 'ignore'])->whereNumber('game');
        Route::delete('me/ignored-games/{game}', [PreferenceController::class, 'unignore'])->whereNumber('game');
    });
});
