<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UserGameProgress;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UserGameProgressController extends Controller
{
    /**
     * Update the user's game progress.
     */
    public function update(Request $request, Game $game): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'game_version_id' => 'nullable|exists:game_versions,id',
            'status' => 'nullable|string|in:reading,completed,plan_to_read,on_hold,dropped',
            'personal_notes' => 'nullable|string|max:1000',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);

        try {
            // If a game version is specified, ensure it belongs to the game before writing anything.
            if ($validated['game_version_id'] ?? null) {
                $gameVersion = GameVersion::find($validated['game_version_id']);
                if ($gameVersion && $gameVersion->game_id !== $game->id) {
                    return response()->json([
                        'message' => 'The specified game version does not belong to this game',
                    ], 422);
                }
            }

            $values = [];
            if (array_key_exists('game_version_id', $validated)) {
                $values['game_version_id'] = $validated['game_version_id'];
            }
            if (array_key_exists('status', $validated) && $validated['status'] !== null) {
                $values['status'] = $validated['status'];
            }
            if (array_key_exists('personal_notes', $validated)) {
                $values['personal_notes'] = $validated['personal_notes'];
            }
            if (Schema::hasColumn('user_game_progress', 'progress') && array_key_exists('progress', $validated)) {
                $values['progress'] = $validated['progress'];
            }

            // Find or create the user's game progress record
            $progress = UserGameProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                ],
                $values
            );

            return response()->json([
                'message' => 'Game progress updated successfully',
                'progress' => $progress,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update game progress', [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to update game progress',
            ], 500);
        }
    }
}
