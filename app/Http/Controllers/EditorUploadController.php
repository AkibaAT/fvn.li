<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EditorUploadController extends Controller
{
    public function uploadEditorImage(Request $request): JsonResponse
    {
        // Auth required
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $validated = $request->validate([
            'file' => 'required|image|max:8192', // 8MB
            'game_id' => 'required|integer|exists:games,id',
        ]);

        /** @var Game $game */
        $game = Game::findOrFail($validated['game_id']);

        // Permission check
        if (! $game->canUserEdit($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this game.',
            ], 403);
        }

        $file = $request->file('file');
        $path = $file->store("editor/{$game->id}", 'public');

        // Public URL
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'location' => $url,
        ]);
    }
}
