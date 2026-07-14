<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\DiscordServerConfig;
use App\Models\DiscordServerMember;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiscordBotServerController extends Controller
{
    public function pendingNotifications(Request $request): JsonResponse
    {
        $limit = min($request->input('limit', 50), 100);

        return DB::transaction(function () use ($limit) {
            $notifications = DiscordNotificationHistory::where('delivery_status', 'pending')
                ->whereHas('discordServer', fn ($q) => $q->where('is_active', true))
                ->with(['discordServer.config', 'game.latestVersion'])
                ->orderBy('created_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $batchKey = uniqid('bot_', true);
            DiscordNotificationHistory::whereIn('id', $notifications->pluck('id'))
                ->update([
                    'delivery_status' => 'processing',
                ]);

            $payload = $notifications->map(fn ($notification) => [
                'id' => $notification->id,
                'discord_server_id' => $notification->discordServer->discord_server_id,
                'channel_id' => $notification->channel_id,
                'payload' => $notification->payload,
                'game_name' => $notification->game?->name,
                'notification_type' => $notification->notification_type,
                'delivery_mode' => $notification->delivery_mode,
                'message_id' => $notification->message_id,
            ]);

            return response()->json([
                'notifications' => $payload,
                'batch_key' => $batchKey,
                'count' => $payload->count(),
            ]);
        });
    }

    public function markDelivered(DiscordNotificationHistory $notification, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'nullable|string',
        ]);

        $notification->update([
            'delivery_status' => 'sent',
            'message_id' => $validated['message_id'] ?? null,
            'sent_at' => now(),
        ]);

        if ($notification->notification_type === 'new_game' && $notification->game_id) {
            DB::table('discord_server_games')->upsert([[
                'discord_server_id' => $notification->discord_server_id,
                'game_id' => $notification->game_id,
                'discord_channel_id' => $notification->channel_id,
                'discord_message_id' => $validated['message_id'] ?? $notification->message_id,
                'discord_payload_hash' => $notification->payload_hash,
                'discord_updated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['discord_server_id', 'game_id'], [
                'discord_channel_id', 'discord_message_id', 'discord_payload_hash', 'discord_updated_at', 'updated_at',
            ]);
        }

        return response()->json(['message' => 'Marked as delivered']);
    }

    public function markFailed(DiscordNotificationHistory $notification, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'error_message' => 'nullable|string|max:1000',
        ]);

        $notification->update([
            'delivery_status' => 'failed',
            'error_message' => $validated['error_message'] ?? 'Unknown error',
        ]);

        return response()->json(['message' => 'Marked as failed']);
    }

    public function syncChannels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'discord_server_id' => 'required|string',
            'channels' => 'required|array',
            'channels.*.id' => 'required|string',
            'channels.*.name' => 'required|string',
            'channels.*.type' => 'nullable|integer',
            'channels.*.nsfw' => 'nullable|boolean',
        ]);

        $server = DiscordServer::where('discord_server_id', $validated['discord_server_id'])->first();

        if (! $server) {
            return response()->json(['error' => 'Server not found'], 404);
        }

        $server->update([
            'available_channels' => $validated['channels'],
            'channels_synced_at' => now(),
        ]);

        return response()->json([
            'message' => 'Channels synced successfully',
            'count' => count($validated['channels']),
        ]);
    }

    public function syncMembers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'discord_server_id' => 'required|string',
            'members' => 'required|array',
            'members.*.discord_user_id' => 'required|string',
            'members.*.discord_username' => 'required|string',
            'members.*.is_admin' => 'boolean',
        ]);

        $server = DiscordServer::where('discord_server_id', $validated['discord_server_id'])->first();

        if (! $server) {
            return response()->json(['error' => 'Server not found'], 404);
        }

        foreach ($validated['members'] as $memberData) {
            $linkedUserId = SocialAccount::where('provider_name', 'discord')
                ->where('provider_id', $memberData['discord_user_id'])
                ->value('user_id');

            DiscordServerMember::updateOrCreate(
                [
                    'discord_server_id' => $server->id,
                    'discord_user_id' => $memberData['discord_user_id'],
                ],
                [
                    'user_id' => $linkedUserId,
                    'discord_username' => $memberData['discord_username'],
                    'is_admin' => $memberData['is_admin'] ?? false,
                ],
            );
        }

        return response()->json([
            'message' => 'Members synced successfully',
            'count' => count($validated['members']),
        ]);
    }

    public function botJoined(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'discord_server_id' => 'required|string',
            'discord_server_name' => 'required|string|max:255',
            'owner_discord_id' => 'nullable|string',
            'channels' => 'nullable|array',
        ]);

        $server = DiscordServer::updateOrCreate(
            ['discord_server_id' => $validated['discord_server_id']],
            [
                'discord_server_name' => $validated['discord_server_name'],
                'is_active' => true,
                'bot_joined_at' => now(),
                'available_channels' => $validated['channels'] ?? null,
                'channels_synced_at' => $validated['channels'] ? now() : null,
            ],
        );

        if (empty($server->owner_user_id) && ! empty($validated['owner_discord_id'])) {
            $ownerUserId = SocialAccount::where('provider_name', 'discord')
                ->where('provider_id', $validated['owner_discord_id'])
                ->value('user_id');

            if ($ownerUserId) {
                $server->update(['owner_user_id' => $ownerUserId]);
            }
        }

        if (! $server->config) {
            DiscordServerConfig::create([
                'discord_server_id' => $server->id,
                'notification_format' => 'detailed',
            ]);
        }

        Log::info('Discord bot joined server', [
            'server_id' => $server->id,
            'discord_server_id' => $validated['discord_server_id'],
            'name' => $validated['discord_server_name'],
        ]);

        return response()->json([
            'message' => 'Server registration updated',
            'server' => $server->load('config'),
        ]);
    }

    public function reconcileGuilds(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guilds' => 'present|array',
            'guilds.*.discord_server_id' => 'required|string',
            'guilds.*.discord_server_name' => 'required|string|max:255',
            'guilds.*.owner_discord_id' => 'nullable|string',
            'guilds.*.channels' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated) {
            $activeGuildIds = collect($validated['guilds'])
                ->pluck('discord_server_id')
                ->all();

            DiscordServer::where('is_active', true)
                ->when($activeGuildIds !== [], fn ($query) => $query->whereNotIn('discord_server_id', $activeGuildIds))
                ->update(['is_active' => false]);

            foreach ($validated['guilds'] as $guild) {
                $server = DiscordServer::updateOrCreate(
                    ['discord_server_id' => $guild['discord_server_id']],
                    [
                        'discord_server_name' => $guild['discord_server_name'],
                        'is_active' => true,
                        'bot_joined_at' => now(),
                        'available_channels' => $guild['channels'] ?? null,
                        'channels_synced_at' => ! empty($guild['channels']) ? now() : null,
                    ],
                );

                if (empty($server->owner_user_id) && ! empty($guild['owner_discord_id'])) {
                    $ownerUserId = SocialAccount::where('provider_name', 'discord')
                        ->where('provider_id', $guild['owner_discord_id'])
                        ->value('user_id');

                    if ($ownerUserId) {
                        $server->update(['owner_user_id' => $ownerUserId]);
                    }
                }

                DiscordServerConfig::firstOrCreate(
                    ['discord_server_id' => $server->id],
                    ['notification_format' => 'detailed'],
                );
            }

            Log::info('Discord bot guild state reconciled', [
                'active_guild_count' => count($activeGuildIds),
            ]);

            return response()->json([
                'message' => 'Guild state reconciled',
                'count' => count($activeGuildIds),
            ]);
        });
    }

    public function botLeft(Request $request, string $server): JsonResponse
    {
        $discordServer = DiscordServer::where('discord_server_id', $server)->first();

        if ($discordServer) {
            $discordServer->update(['is_active' => false]);

            Log::info('Discord bot left server', [
                'server_id' => $discordServer->id,
                'discord_server_id' => $server,
            ]);
        }

        return response()->json(['message' => 'Server marked as inactive']);
    }
}
