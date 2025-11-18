<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordUser;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscordBotController extends Controller
{
    public function searchGames(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $games = Game::query()
            ->fromItchio()
            ->with('latestVersion')
            ->where('is_visible', true)
            ->where('name', 'ilike', "%{$request->input('name')}%")
            ->get();

        return response()->json([
            'matches' => $games->count(),
            'games' => $games->map(fn ($game) => [
                'name' => $game->name,
                'version' => $game->latestVersion?->version,
                'published_at' => $game->latestVersion?->published_at ? $game->latestVersion->published_at->timestamp : null,
                'url' => $game->url, // Multi-platform URLs as JSONB object
            ]),
        ]);
    }

    public function getUpdates(): JsonResponse
    {
        // Get all subscribed users that need updates
        $users = DiscordUser::query()
            ->where('processed_at', '<', now()->subMinutes(25)) // Give some buffer before 30min mark
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['updates' => []]);
        }

        // Get games updated since last processed timestamp
        $games = Game::query()
            ->with('latestVersion')
            ->where('is_visible', true)
            ->whereHas('latestVersion', function ($query) use ($users) {
                $query->where('created_at', '>', $users->min('processed_at'));
            })
            ->orderBy('name')
            ->get();

        // Update processed_at timestamp for all fetched users
        DiscordUser::whereIn('id', $users->pluck('id'))
            ->update(['processed_at' => now()]);

        return response()->json([
            'discord_users' => $users->pluck('discord_id'),
            'updates' => $games->map(fn ($game) => [
                'name' => $game->name,
                'version' => $game->latestVersion?->version,
                'published_at' => $game->latestVersion?->published_at ? $game->latestVersion->published_at->timestamp : null,
                'url' => $game->url, // Multi-platform URLs as JSONB object
                'devlog' => $game->latestVersion?->devlog,
            ]),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'discord_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $user = DiscordUser::firstOrNew([
            'discord_id' => $request->input('discord_id'),
        ]);

        if (! $user->exists) {
            $user->processed_at = now();
            $user->save();

            return response()->json(['message' => 'Subscribed successfully']);
        }

        return response()->json(['message' => 'Already subscribed']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'discord_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $deleted = DiscordUser::where('discord_id', $request->input('discord_id'))->delete();

        return response()->json([
            'message' => $deleted ? 'Unsubscribed successfully' : 'Not subscribed',
        ]);
    }
}
