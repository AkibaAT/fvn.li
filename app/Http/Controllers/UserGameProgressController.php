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

class UserGameProgressController extends Controller
{
    /**
     * Update the user's game progress.
     */
    public function update(Request $request, Game $game): JsonResponse
    {
        try {
            // Log all request data for debugging
            Log::info('UserGameProgressController update request', [
                'request_data' => $request->all(),
                'game_id' => $game->id,
                'user_id' => Auth::id(),
            ]);

            // Find existing progress record or create a new one
            $progress = UserGameProgress::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'game_id' => $game->id,
                ]
            );

            // Authorize against the progress policy
            $this->authorize('update', $progress);

            $validatedData = $request->validate([
                'game_version_id' => [
                    'nullable',
                    'exists:game_versions,id',
                    function ($attribute, $value, $fail) use ($game) {
                        if (! empty($value)) {
                            $exists = GameVersion::where('id', $value)
                                ->where('game_id', $game->id)
                                ->exists();
                            if (! $exists) {
                                $fail('The selected version does not belong to this game.');
                            }
                        }
                    },
                ],
                'started_at' => 'nullable|date',
                'completed_at' => 'nullable|date',
                'personal_notes' => 'nullable|string',
                'status' => 'nullable|string',
                'receive_updates' => 'nullable|boolean',
            ]);

            // Log validated data
            Log::info('UserGameProgressController validated data', [
                'validated_data' => $validatedData,
                'progress_before' => $progress->toArray(),
            ]);

            // Update the progress record with the validated data
            $progress->fill(
                array_filter($validatedData, function ($value) {
                    return $value !== null;
                })
            );
            $progress->save();

            // Log the result after saving
            Log::info('UserGameProgressController after save', [
                'progress_after' => $progress->fresh()->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progress updated successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error updating user game progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'game_id' => $game->id ?? null,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating game progress: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle update notifications for a game.
     */
    public function toggleUpdates(Request $request, Game $game): JsonResponse
    {
        try {
            // Check if the game is paid - we don't allow notifications for paid games
            if ($game->is_paid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notifications are not available for paid games.',
                ], 400);
            }

            // Find or create progress record for this game
            $progress = UserGameProgress::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'game_id' => $game->id,
                ],
                [
                    'status' => 'custom',
                ]
            );

            // Authorize against the progress record
            $this->authorize('update', $progress);

            // Determine if updates should be received based on the checkbox state
            $receiveUpdates = false;

            // Check if the checkbox is checked (value is '1' or true)
            if ($request->input('receive_updates') == '1' || $request->input('receive_updates') === true) {
                $receiveUpdates = true;
            }

            $progress->receive_updates = $receiveUpdates;
            $progress->save();

            $message = 'Update notifications ' . ($receiveUpdates ? 'enabled' : 'disabled') . ' for ' . $game->name;

            return response()->json([
                'success' => true,
                'message' => $message,
                'receive_updates' => $receiveUpdates,
            ]);
        } catch (Exception $e) {
            Log::error('Error toggling game update notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'game_id' => $game->id ?? null,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error toggling update notifications: ' . $e->getMessage(),
            ], 500);
        }
    }
}
