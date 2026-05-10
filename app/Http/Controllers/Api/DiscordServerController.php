<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordServer;
use App\Models\DiscordServerConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordServerController extends Controller
{
    /**
     * Register a new Discord server.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'discord_server_id' => 'required|string|unique:discord_servers',
            'discord_server_name' => 'required|string|max:255',
            'bot_joined_at' => 'nullable|date',
        ]);

        $server = DiscordServer::create([
            ...$validated,
            'owner_user_id' => $request->user()?->id,
            'is_active' => true,
        ]);

        // Create default config
        DiscordServerConfig::create([
            'discord_server_id' => $server->id,
            'notification_format' => 'detailed',
        ]);

        return response()->json([
            'message' => 'Server registered successfully',
            'server' => $server->load('config'),
        ], 201);
    }

    /**
     * Get all servers for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $servers = DiscordServer::where('owner_user_id', $request->user()->id)
            ->with(['config', 'gameSubscriptions', 'members'])
            ->get();

        return response()->json([
            'servers' => $servers,
            'count' => $servers->count(),
        ]);
    }

    /**
     * Get a specific server.
     */
    public function show(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('view', $server);

        return response()->json([
            'server' => $server->load([
                'config',
                'gameSubscriptions.game',
                'members',
                'notificationHistory' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(50),
            ]),
        ]);
    }

    /**
     * Update server configuration.
     */
    public function updateConfig(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'notification_channel_id' => 'nullable|string',
            'notification_format' => 'required|in:compact,detailed,custom',
            'custom_template' => 'nullable|string|max:2000',
            'include_game_description' => 'boolean',
            'include_thumbnail' => 'boolean',
            'include_ratings' => 'boolean',
            'ping_role_id' => 'nullable|string',
        ]);

        $config = $server->config ?? DiscordServerConfig::create([
            'discord_server_id' => $server->id,
        ]);

        $config->update($validated);

        return response()->json([
            'message' => 'Configuration updated successfully',
            'config' => $config,
        ]);
    }

    /**
     * Unlink a Discord server.
     */
    public function destroy(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('delete', $server);

        $server->delete();

        return response()->json([
            'message' => 'Server unlinked successfully',
        ]);
    }

    /**
     * Get server statistics.
     */
    public function stats(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('view', $server);

        return response()->json([
            'subscription_count' => $server->getSubscriptionCount(),
            'tag_subscription_count' => $server->getTagSubscriptionCount(),
            'member_count' => $server->members()->count(),
            'notification_stats' => $server->getNotificationStats(),
            'is_configured' => $server->isConfigured(),
        ]);
    }
}
