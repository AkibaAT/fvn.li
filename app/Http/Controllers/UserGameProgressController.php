<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\UserGameProgress;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserGameProgressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the user's progress for a game.
     *
     * @param  Request  $request  The incoming request
     * @param  Game  $game  The game to update progress for
     */
    public function update(Request $request, Game $game): RedirectResponse|JsonResponse
    {
        try {
            // Log the incoming request data for debugging
            Log::debug('UserGameProgress update request', [
                'request' => $request->all(),
                'game_id' => $game->id,
                'route' => $request->route()->uri,
                'parameters' => $request->route()->parameters(),
            ]);

            // Ensure we have a valid game instance
            $gameId = $game->id;

            // Double check: if we have a game_id parameter and our model binding failed,
            // attempt to load the game directly
            if (empty($gameId) && $request->has('game_id')) {
                $game = Game::find($request->input('game_id'));
                if (! $game) {
                    throw new Exception('Game not found with ID: ' . $request->input('game_id'));
                }
                $gameId = $game->id;
            }

            $validated = $request->validate([
                'started_at' => 'nullable|date',
                'completed_at' => 'nullable|date',
                'personal_notes' => 'nullable|string',
                'game_version_id' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($game) {
                        // Skip validation if the value is empty or null
                        if (empty($value)) {
                            return;
                        }

                        // Check if the version belongs to this game
                        $exists = GameVersion::where('id', $value)
                            ->where('game_id', $game->id)
                            ->exists();

                        if (! $exists) {
                            $fail('The selected version is invalid.');
                        }
                    },
                ],
            ]);

            // Handle empty string for game_version_id
            if (isset($validated['game_version_id']) && ($validated['game_version_id'] === '')) {
                $validated['game_version_id'] = null;
            }

            // Get the current status from the user's default lists
            $status = 'plan_to_read'; // Default status
            $userId = Auth::id();

            if ($game->userProgress()->where('user_id', $userId)->exists()) {
                // Update existing record
                $progress = $game->userProgress()->where('user_id', $userId)->first();
                $progress->update($validated);
            } else {
                // Create new record
                $progress = new UserGameProgress($validated);
                $progress->user_id = $userId;
                $progress->game_id = $game->id;
                $progress->status = $status;
                $progress->save();
            }

            // Update status based on dates
            if ($progress->completed_at) {
                $progress->status = 'completed';
            } elseif ($progress->started_at) {
                $progress->status = 'reading';
            }
            $progress->save();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Game progress updated successfully',
                ]);
            }

            return back()->with('success', 'Game progress updated successfully');
        } catch (Exception $e) {
            Log::error('Error updating user game progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'game_id' => $game->id ?? null,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating game progress: ' . $e->getMessage(),
                ]);
            }

            return back()->with('error', 'Error updating game progress: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
