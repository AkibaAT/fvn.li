<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordServer;
use App\Models\DiscordServerTag;
use App\Models\Game;
use App\Models\GameDiscordSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordSubscriptionController extends Controller
{
    /**
     * Subscribe a server to a game.
     */
    public function subscribeGame(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'game_id' => 'required|exists:games,id',
        ]);

        $game = Game::findOrFail($validated['game_id']);

        // Check if already subscribed
        $existing = GameDiscordSubscription::where([
            'game_id' => $game->id,
            'discord_server_id' => $server->id,
        ])->first();

        if ($existing) {
            // Reactivate if inactive
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return response()->json([
                'message' => 'Already subscribed to this game',
                'subscription' => $existing,
            ]);
        }

        $subscription = GameDiscordSubscription::create([
            'game_id' => $game->id,
            'discord_server_id' => $server->id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Subscribed successfully',
            'subscription' => $subscription,
        ], 201);
    }

    /**
     * Unsubscribe a server from a game.
     */
    public function unsubscribeGame(DiscordServer $server, Game $game, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $subscription = GameDiscordSubscription::where([
            'game_id' => $game->id,
            'discord_server_id' => $server->id,
        ])->firstOrFail();

        $subscription->delete();

        return response()->json([
            'message' => 'Unsubscribed successfully',
        ]);
    }

    /**
     * Get all subscriptions for a server.
     */
    public function listSubscriptions(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('view', $server);

        $subscriptions = $server->gameSubscriptions()
            ->with('game')
            ->paginate(50);

        return response()->json($subscriptions);
    }

    /**
     * Subscribe a server to a tag.
     */
    public function subscribeTag(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'tag_name' => 'required|string|max:255',
        ]);

        $tag = DiscordServerTag::firstOrCreate([
            'discord_server_id' => $server->id,
            'tag_name' => $validated['tag_name'],
        ]);

        $tag->update(['is_subscribed' => true]);

        return response()->json([
            'message' => 'Tag subscription created',
            'tag' => $tag,
        ], 201);
    }

    /**
     * Unsubscribe a server from a tag.
     */
    public function unsubscribeTag(DiscordServer $server, string $tagName, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $tag = DiscordServerTag::where([
            'discord_server_id' => $server->id,
            'tag_name' => $tagName,
        ])->firstOrFail();

        $tag->delete();

        return response()->json([
            'message' => 'Tag unsubscribed',
        ]);
    }

    /**
     * Get all tag subscriptions for a server.
     */
    public function listTags(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('view', $server);

        $tags = $server->tagSubscriptions()->get();

        return response()->json([
            'tags' => $tags,
            'count' => $tags->count(),
        ]);
    }

    /**
     * Bulk subscribe to games.
     */
    public function bulkSubscribe(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'game_ids' => 'required|array',
            'game_ids.*' => 'exists:games,id',
        ]);

        $created = 0;
        $skipped = 0;

        foreach ($validated['game_ids'] as $gameId) {
            $existing = GameDiscordSubscription::where([
                'game_id' => $gameId,
                'discord_server_id' => $server->id,
            ])->first();

            if ($existing) {
                $skipped++;

                continue;
            }

            GameDiscordSubscription::create([
                'game_id' => $gameId,
                'discord_server_id' => $server->id,
                'is_active' => true,
            ]);

            $created++;
        }

        return response()->json([
            'message' => 'Bulk subscription completed',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Get Discord metadata for a game in a specific server.
     */
    public function getGameMetadata(DiscordServer $server, Game $game): JsonResponse
    {
        $this->authorize('view', $server);

        $subscription = GameDiscordSubscription::where([
            'game_id' => $game->id,
            'discord_server_id' => $server->id,
        ])->firstOrFail();

        return response()->json([
            'id' => $game->id,
            'name' => $game->name,
            'url' => $game->getPrimaryUrl(),
            'platform' => $game->platform,
            'content_type' => $game->content_type,
            'discord_channel_id' => $subscription->discord_channel_id,
            'discord_message_id' => $subscription->discord_message_id,
            'discord_likes' => $subscription->discord_likes ?? [],
            'discord_dislikes' => $subscription->discord_dislikes ?? [],
            'abbreviations' => $subscription->abbreviations ?? [],
            'discord_tags' => $subscription->discord_tags ?? [],
            'discord_updated_at' => $subscription->discord_updated_at,
        ]);
    }

    /**
     * Update Discord metadata for a game in a specific server.
     */
    public function updateGameMetadata(DiscordServer $server, Game $game, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $subscription = GameDiscordSubscription::where([
            'game_id' => $game->id,
            'discord_server_id' => $server->id,
        ])->firstOrFail();

        $validated = $request->validate([
            'discord_channel_id' => 'nullable|string',
            'discord_message_id' => 'nullable|string',
            'discord_likes' => 'nullable|array',
            'discord_dislikes' => 'nullable|array',
            'abbreviations' => 'nullable|array',
            'discord_tags' => 'nullable|array',
            'content_type' => 'nullable|in:visual_novel,adjacent,other',
        ]);

        $updates = array_filter($validated, fn ($value) => $value !== null);

        if (! empty($updates)) {
            // If content_type is being updated, update it on the game model too
            if (isset($updates['content_type'])) {
                $game->update(['content_type' => $updates['content_type']]);
                unset($updates['content_type']);
            }

            $updates['discord_updated_at'] = now();
            $subscription->update($updates);
        }

        return response()->json([
            'message' => 'Game metadata updated successfully',
            'metadata' => $this->getGameMetadata($server, $game)->getData(true),
        ]);
    }

    /**
     * Update Discord rating (like/dislike) for a game in a specific server.
     */
    public function updateGameRating(DiscordServer $server, Game $game, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $subscription = GameDiscordSubscription::where([
            'game_id' => $game->id,
            'discord_server_id' => $server->id,
        ])->firstOrFail();

        $validated = $request->validate([
            'user_id' => 'required|string',
            'rating' => 'required|in:like,dislike,none',
        ]);

        $userId = $validated['user_id'];
        $rating = $validated['rating'];

        $likes = $subscription->discord_likes ?? [];
        $dislikes = $subscription->discord_dislikes ?? [];

        // Remove user from both arrays first
        $likes = array_filter($likes, fn ($id) => $id !== $userId);
        $dislikes = array_filter($dislikes, fn ($id) => $id !== $userId);

        // Add to appropriate array
        if ($rating === 'like') {
            $likes[] = $userId;
        } elseif ($rating === 'dislike') {
            $dislikes[] = $userId;
        }

        $subscription->update([
            'discord_likes' => array_values($likes),
            'discord_dislikes' => array_values($dislikes),
            'discord_updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Rating updated successfully',
            'discord_likes' => $subscription->discord_likes,
            'discord_dislikes' => $subscription->discord_dislikes,
        ]);
    }
}
