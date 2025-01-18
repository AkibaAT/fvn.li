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
            ->select([
                'games.*',
                'game_versions.version',
                'game_versions.published_at',
            ])
            ->join('game_versions', function ($join) {
                $join->on('games.id', '=', 'game_versions.game_id')
                    ->where('game_versions.is_latest', true);
            })
            ->where('games.is_visible', true)
            ->where('games.name', 'ilike', "%{$request->input('name')}%")
            ->get();

        return response()->json([
            'matches' => $games->count(),
            'games' => $games->map(fn ($game) => [
                'name' => $game->name,
                'version' => $game->version,
                'published_at' => $game->published_at,
                'url' => $game->url,
            ]),
        ]);
    }

    public function getUpdates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'discord_id' => 'required|string',
            'after' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $games = Game::query()
            ->select([
                'games.name',
                'games.url',
                'game_versions.version',
                'game_versions.published_at',
                'game_versions.devlog',
            ])
            ->join('game_versions', function ($join) {
                $join->on('games.id', '=', 'game_versions.game_id')
                    ->where('game_versions.is_latest', true);
            })
            ->where('games.is_visible', true)
            ->where('game_versions.created_at', '>', $request->input('after'))
            ->orderBy('games.name')
            ->get();

        return response()->json([
            'updates' => $games->map(fn ($game) => [
                'name' => $game->name,
                'version' => $game->version,
                'published_at' => $game->published_at?->timestamp,
                'url' => $game->url,
                'devlog' => $game->devlog,
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
