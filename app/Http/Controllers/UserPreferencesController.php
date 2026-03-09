<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferencesController extends Controller
{
    /**
     * Get the user's language preferences
     */
    public function getLanguagePreferences(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $preferences = UserPreference::where('user_id', $authId)->first();

        return response()->json([
            'success' => true,
            'preferred_languages' => $preferences?->preferred_languages ?? [],
        ]);
    }

    /**
     * Update the user's language preferences
     */
    public function updateLanguagePreferences(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'preferred_languages' => 'nullable|array',
            'preferred_languages.*' => 'string|size:3',
        ]);

        $languages = $request->input('preferred_languages', []);

        $preferences = UserPreference::updateOrCreate(
            ['user_id' => $authId],
            ['preferred_languages' => ! empty($languages) ? $languages : null],
        );

        return response()->json([
            'success' => true,
            'preferred_languages' => $preferences->preferred_languages ?? [],
        ]);
    }

    /**
     * Get the user's excluded tags preferences
     */
    public function getExcludedTags(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $preferences = UserPreference::where('user_id', $authId)->first();

        return response()->json([
            'success' => true,
            'excluded_tags' => $preferences?->excluded_tags ?? [],
        ]);
    }

    /**
     * Update the user's excluded tags preferences
     */
    public function updateExcludedTags(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'excluded_tags' => 'nullable|array',
            'excluded_tags.*' => 'integer|exists:tags,id',
        ]);

        $tagIds = $request->input('excluded_tags', []);

        $preferences = UserPreference::updateOrCreate(
            ['user_id' => $authId],
            ['excluded_tags' => ! empty($tagIds) ? array_map('intval', $tagIds) : null],
        );

        return response()->json([
            'success' => true,
            'excluded_tags' => $preferences->excluded_tags ?? [],
        ]);
    }

    /**
     * Get the list of game IDs that the user has ignored
     */
    public function getIgnoredGames(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user = User::findOrFail($authId);
        $ignoredGameIds = $user->ignoredGames()->pluck('games.id')->toArray();

        return response()->json([
            'success' => true,
            'ignored_game_ids' => $ignoredGameIds,
        ]);
    }

    /**
     * Add a game to the user's ignore list
     */
    public function ignoreGame(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $request->validate([
            'game_id' => 'required|integer|exists:games,id',
        ]);

        $user = User::findOrFail($authId);
        $game = Game::findOrFail($request->game_id);

        // Check if already ignored
        if ($user->ignoredGames()->where('games.id', $game->id)->exists()) {
            return response()->json([
                'success' => true,
                'message' => 'Game already ignored',
                'ignored_game_ids' => $user->ignoredGames()->pluck('games.id')->toArray(),
            ]);
        }

        // Add to ignored games
        $user->ignoredGames()->attach($game->id);

        return response()->json([
            'success' => true,
            'message' => 'Game added to ignore list',
            'ignored_game_ids' => $user->ignoredGames()->pluck('games.id')->toArray(),
        ]);
    }

    /**
     * Remove a game from the user's ignore list
     */
    public function unignoreGame(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $request->validate([
            'game_id' => 'required|integer|exists:games,id',
        ]);

        $user = User::findOrFail($authId);
        $game = Game::findOrFail($request->game_id);

        // Remove from ignored games
        $user->ignoredGames()->detach($game->id);

        return response()->json([
            'success' => true,
            'message' => 'Game removed from ignore list',
            'ignored_game_ids' => $user->ignoredGames()->pluck('games.id')->toArray(),
        ]);
    }

    /**
     * Toggle a game's ignored status
     */
    public function toggleIgnoreGame(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $request->validate([
            'game_id' => 'required|integer|exists:games,id',
        ]);

        $user = User::findOrFail($authId);
        $game = Game::findOrFail($request->game_id);

        // Check if already ignored
        $isIgnored = $user->ignoredGames()->where('games.id', $game->id)->exists();

        if ($isIgnored) {
            $user->ignoredGames()->detach($game->id);
            $message = 'Game removed from ignore list';
        } else {
            $user->ignoredGames()->attach($game->id);
            $message = 'Game added to ignore list';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_ignored' => ! $isIgnored,
            'ignored_game_ids' => $user->ignoredGames()->pluck('games.id')->toArray(),
        ]);
    }
}
