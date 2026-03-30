<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DiscordServer;
use App\Models\DiscordServerConfig;
use App\Models\DiscordServerGameOverride;
use App\Models\DiscordServerMember;
use App\Models\Game;
use App\Models\Language;
use App\Models\SocialAccount;
use App\Models\Tag;
use App\Services\Discord\DiscordEmbedRendererService;
use App\Services\Discord\DiscordRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordConfigController extends Controller
{
    private const DISCORD_BOT_INSTALL_SESSION_KEY = 'discord_bot_install';

    private const DISCORD_PERMISSION_ADMINISTRATOR = 0x8;

    private const DISCORD_PERMISSION_MANAGE_GUILD = 0x20;

    private const DISCORD_PERMISSION_VIEW_CHANNEL = 0x400;

    private const DISCORD_PERMISSION_SEND_MESSAGES = 0x800;

    private const DISCORD_PERMISSION_EMBED_LINKS = 0x4000;

    private const DISCORD_PERMISSION_READ_MESSAGE_HISTORY = 0x10000;

    private const DISCORD_BOT_REQUIRED_PERMISSIONS =
        self::DISCORD_PERMISSION_VIEW_CHANNEL
        | self::DISCORD_PERMISSION_SEND_MESSAGES
        | self::DISCORD_PERMISSION_EMBED_LINKS
        | self::DISCORD_PERMISSION_READ_MESSAGE_HISTORY;

    public function guilds(Request $request): JsonResponse
    {
        $user = $request->user();
        $discordAccount = SocialAccount::where('user_id', $user->id)
            ->where('provider_name', 'discord')
            ->first();

        if (! $discordAccount) {
            return response()->json(['guilds' => [], 'has_discord' => false]);
        }

        $managedGuilds = $this->getManagedGuildsForDiscordAccount($discordAccount);

        $managedGuildIds = $managedGuilds->pluck('id')->all();
        $existingServers = DiscordServer::whereIn('discord_server_id', $managedGuildIds)
            ->with('config')
            ->get()
            ->keyBy('discord_server_id');

        $ownedServerIds = DiscordServer::where('owner_user_id', $user->id)->pluck('discord_server_id');
        $adminMemberServerIds = DiscordServerMember::where('user_id', $user->id)
            ->where('is_admin', true)
            ->pluck('discord_server_id');
        $managedServerIds = $ownedServerIds->merge($adminMemberServerIds)->unique()->values()->all();

        $result = $managedGuilds->filter(function ($guild) use ($existingServers, $managedServerIds) {
            $server = $existingServers->get($guild['id']);

            if ($server !== null) {
                return in_array($guild['id'], $managedServerIds, false);
            }

            return true;
        })->map(function ($guild) use ($existingServers) {
            $server = $existingServers->get($guild['id']);

            return [
                'id' => $guild['id'],
                'name' => $guild['name'],
                'icon' => $guild['icon'] ?? null,
                'owner' => $guild['owner'] ?? false,
                'has_bot' => $server !== null,
                'server' => $server,
                'bot_install_url' => $server === null
                    ? $this->getBotInstallUrl($guild['id'])
                    : null,
            ];
        })->values();

        return response()->json([
            'guilds' => $result,
            'has_discord' => true,
        ]);
    }

    public function redirectToBotInstall(Request $request, string $guildId): RedirectResponse
    {
        $discordAccount = SocialAccount::where('user_id', $request->user()->id)
            ->where('provider_name', 'discord')
            ->first();

        abort_unless($discordAccount !== null, 404);

        $guild = $this->getManagedGuildsForDiscordAccount($discordAccount)
            ->firstWhere('id', $guildId);

        abort_unless($guild !== null, 403);

        $state = Str::random(40);
        $request->session()->put(self::DISCORD_BOT_INSTALL_SESSION_KEY, [
            'state' => $state,
            'guild_id' => $guildId,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->away($this->buildDiscordBotAuthorizationUrl($guildId, $state));
    }

    public function handleBotInstallCallback(Request $request): RedirectResponse
    {
        $install = $request->session()->pull(self::DISCORD_BOT_INSTALL_SESSION_KEY);
        $user = $request->user();

        if (! is_array($install) || ! $user || ($install['user_id'] ?? null) !== $user->id) {
            return redirect()->route('dashboard.discord.index')
                ->with('error', 'Discord bot install session expired. Please try again.');
        }

        if ($request->filled('error')) {
            return redirect()->route('dashboard.discord.index')
                ->with('error', 'Discord bot installation was cancelled or failed.');
        }

        if (! hash_equals((string) ($install['state'] ?? ''), (string) $request->query('state', ''))) {
            return redirect()->route('dashboard.discord.index')
                ->with('error', 'Discord bot install validation failed. Please try again.');
        }

        $guildId = (string) $request->query('guild_id', $install['guild_id']);
        if ($guildId === '') {
            return redirect()->route('dashboard.discord.index')
                ->with('error', 'Discord did not return a guild for this install.');
        }

        $discordAccount = SocialAccount::where('user_id', $user->id)
            ->where('provider_name', 'discord')
            ->first();

        if (! $discordAccount) {
            return redirect()->route('dashboard.discord.index')
                ->with('error', 'Your Discord account is no longer connected.');
        }

        $guild = $this->getManagedGuildsForDiscordAccount($discordAccount)
            ->firstWhere('id', $guildId);

        if (! $guild) {
            return redirect()->route('dashboard.discord.index')
                ->with('error', 'You no longer have permission to manage that Discord server.');
        }

        $server = DiscordServer::firstOrNew(['discord_server_id' => $guildId]);
        $server->discord_server_name = $guild['name'];
        $server->owner_user_id ??= $user->id;
        $server->is_active = true;
        $server->bot_joined_at = now();
        $server->save();

        if (! $server->config) {
            DiscordServerConfig::create([
                'discord_server_id' => $server->id,
                'notification_format' => 'detailed',
            ]);
        }

        DiscordServerMember::updateOrCreate(
            [
                'discord_server_id' => $server->id,
                'discord_user_id' => (string) $discordAccount->provider_id,
            ],
            [
                'user_id' => $user->id,
                'discord_username' => $user->name,
                'is_admin' => true,
            ],
        );

        return redirect()->route('dashboard.discord.server', ['server' => $server->id])
            ->with('success', 'Discord bot installed successfully.');
    }

    public function servers(Request $request): JsonResponse
    {
        $user = $request->user();

        $ownedServerIds = DiscordServer::where('owner_user_id', $user->id)->pluck('id');
        $adminMemberServerIds = DiscordServerMember::where('user_id', $user->id)
            ->where('is_admin', true)
            ->pluck('discord_server_id');
        $serverIds = $ownedServerIds->merge($adminMemberServerIds)->unique();

        $servers = DiscordServer::whereIn('id', $serverIds)
            ->with($this->getServerRelations())
            ->get();

        return response()->json(['servers' => $servers]);
    }

    public function ruleMetadata(Request $request): JsonResponse
    {
        $request->user();

        return response()->json([
            'fields' => [
                'notification_type' => [
                    'type' => 'enum',
                    'operators' => ['equals', 'not_equals', 'in', 'not_in'],
                    'options' => [
                        ['value' => 'new_game', 'label' => 'New Game'],
                        ['value' => 'update', 'label' => 'Update'],
                    ],
                ],
                'status' => [
                    'type' => 'enum',
                    'operators' => ['equals', 'not_equals', 'in', 'not_in'],
                    'options' => Game::query()
                        ->whereNotNull('status')
                        ->where('status', '!=', '')
                        ->distinct()
                        ->orderBy('status')
                        ->pluck('status')
                        ->map(fn (string $status) => ['value' => $status, 'label' => $status])
                        ->values(),
                ],
                'source_language' => [
                    'type' => 'enum',
                    'operators' => ['equals', 'not_equals', 'in', 'not_in'],
                    'options' => Language::query()
                        ->whereIn('id', Game::query()->whereNotNull('source_language_id')->distinct()->pluck('source_language_id'))
                        ->orderBy('ref_name')
                        ->get(['id', 'ref_name'])
                        ->map(fn (Language $language) => ['value' => $language->id, 'label' => $language->ref_name])
                        ->values(),
                ],
                'tags' => [
                    'type' => 'multi_enum',
                    'operators' => ['contains', 'not_contains', 'contains_any'],
                    'options' => Tag::query()
                        ->orderBy('name')
                        ->get(['name'])
                        ->map(fn (Tag $tag) => ['value' => mb_strtolower($tag->name), 'label' => $tag->name])
                        ->values(),
                ],
                'content_type' => [
                    'type' => 'enum',
                    'operators' => ['equals', 'not_equals', 'in', 'not_in'],
                    'options' => Game::query()
                        ->whereNotNull('content_type')
                        ->where('content_type', '!=', '')
                        ->distinct()
                        ->orderBy('content_type')
                        ->pluck('content_type')
                        ->map(fn (string $value) => ['value' => $value, 'label' => ucfirst(str_replace('_', ' ', $value))])
                        ->values(),
                ],
                'platform' => [
                    'type' => 'enum',
                    'operators' => ['equals', 'not_equals', 'in', 'not_in'],
                    'options' => Game::query()
                        ->whereNotNull('platform')
                        ->where('platform', '!=', '')
                        ->distinct()
                        ->orderBy('platform')
                        ->pluck('platform')
                        ->map(fn (string $value) => ['value' => $value, 'label' => ucfirst(str_replace('_', ' ', $value))])
                        ->values(),
                ],
                'is_nsfw' => [
                    'type' => 'boolean',
                    'operators' => ['equals', 'not_equals'],
                    'options' => [
                        ['value' => true, 'label' => 'Yes'],
                        ['value' => false, 'label' => 'No'],
                    ],
                ],
                'is_paid' => [
                    'type' => 'boolean',
                    'operators' => ['equals', 'not_equals'],
                    'options' => [
                        ['value' => true, 'label' => 'Paid'],
                        ['value' => false, 'label' => 'Free'],
                    ],
                ],
                'developer' => [
                    'type' => 'enum',
                    'operators' => ['equals', 'not_equals', 'in', 'not_in'],
                    'options' => Game::query()
                        ->whereNotNull('developer')
                        ->where('developer', '!=', '')
                        ->distinct()
                        ->orderBy('developer')
                        ->limit(500)
                        ->pluck('developer')
                        ->map(fn (string $developer) => ['value' => $developer, 'label' => $developer])
                        ->values(),
                ],
            ],
        ]);
    }

    public function show(DiscordServer $server): JsonResponse
    {
        $this->authorize('view', $server);

        $server->load($this->getServerRelations([
            'notificationHistory' => fn ($q) => $q->latest()->limit(50),
        ]));

        return response()->json([
            'server' => $server,
        ]);
    }

    public function updateConfig(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'notification_channel_id' => 'nullable|string',
            'notification_format' => 'sometimes|in:compact,detailed,custom',
            'custom_template' => 'nullable|string|max:2000',
            'include_game_description' => 'boolean',
            'include_thumbnail' => 'boolean',
            'include_ratings' => 'boolean',
            'ping_role_id' => 'nullable|string',
            'routing_rules' => 'nullable|array',
            'new_game_embed' => 'nullable|array',
            'update_embed' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if (array_key_exists('is_active', $validated)) {
            $server->update(['is_active' => $validated['is_active']]);
            unset($validated['is_active']);
        }

        $config = $server->config ?? DiscordServerConfig::create([
            'discord_server_id' => $server->id,
        ]);

        $config->update($validated);

        return response()->json([
            'message' => 'Configuration updated successfully',
            'config' => $config->fresh(),
        ]);
    }

    public function overrides(DiscordServer $server): JsonResponse
    {
        $this->authorize('view', $server);

        $overrides = $server->gameOverrides()->with('game')->get();

        return response()->json(['overrides' => $overrides]);
    }

    public function storeOverride(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'game_id' => 'required|exists:games,id',
            'is_ignored' => 'boolean',
            'channel_id' => 'nullable|string',
            'new_game_embed' => 'nullable|array',
            'update_embed' => 'nullable|array',
        ]);

        $override = $server->gameOverrides()->updateOrCreate(
            ['game_id' => $validated['game_id']],
            $validated,
        );

        return response()->json([
            'message' => 'Override saved successfully',
            'override' => $override->load('game'),
        ], $override->wasRecentlyCreated ? 201 : 200);
    }

    public function updateOverride(DiscordServer $server, DiscordServerGameOverride $override, Request $request): JsonResponse
    {
        $this->authorize('update', $server);
        abort_unless($override->discord_server_id === $server->id, 404);

        $validated = $request->validate([
            'is_ignored' => 'boolean',
            'channel_id' => 'nullable|string',
            'new_game_embed' => 'nullable|array',
            'update_embed' => 'nullable|array',
        ]);

        $override->update($validated);

        return response()->json([
            'message' => 'Override updated successfully',
            'override' => $override->fresh()->load('game'),
        ]);
    }

    public function deleteOverride(DiscordServer $server, DiscordServerGameOverride $override): JsonResponse
    {
        $this->authorize('update', $server);
        abort_unless($override->discord_server_id === $server->id, 404);

        $override->delete();

        return response()->json(['message' => 'Override deleted successfully']);
    }

    public function previewEmbed(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('view', $server);

        $validated = $request->validate([
            'embed_template' => 'required|array',
            'game_id' => 'nullable|exists:games,id',
            'notification_type' => 'string|in:new_game,update',
        ]);

        $renderer = app(DiscordEmbedRendererService::class);
        $notificationType = $validated['notification_type'] ?? 'update';

        $game = isset($validated['game_id'])
            ? Game::with(['tags', 'sourceLanguage', 'latestVersion'])->find($validated['game_id'])
            : $this->getSampleGame();

        if (! $game) {
            return response()->json(['error' => 'No game available for preview'], 404);
        }

        $gameVersion = $game->latestVersion ?? null;

        $embed = $renderer->renderEmbed(
            $validated['embed_template'],
            $game,
            $notificationType,
            $gameVersion,
            $server,
        );

        return response()->json(['embed' => $embed]);
    }

    public function channels(DiscordServer $server): JsonResponse
    {
        $this->authorize('view', $server);

        $channels = $server->available_channels ?? [];
        $syncedAt = $server->channels_synced_at;

        if (empty($channels)) {
            $freshChannels = $this->fetchGuildChannelsWithBotToken($server->discord_server_id);

            if ($freshChannels !== null) {
                $server->update([
                    'available_channels' => $freshChannels,
                    'channels_synced_at' => now(),
                ]);

                $channels = $freshChannels;
                $syncedAt = $server->fresh()->channels_synced_at;
            }
        }

        return response()->json([
            'channels' => $channels,
            'synced_at' => $syncedAt,
        ]);
    }

    public function roles(DiscordServer $server): JsonResponse
    {
        $this->authorize('view', $server);

        $roles = $this->fetchGuildRolesWithBotToken($server->discord_server_id);

        return response()->json([
            'roles' => $roles ?? [],
        ]);
    }

    public function testNotification(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $config = $server->config;
        if (! $config || ! $config->notification_channel_id) {
            return response()->json(['error' => 'Server not configured'], 422);
        }

        $routingService = app(DiscordRoutingService::class);
        $renderer = app(DiscordEmbedRendererService::class);

        $game = Game::with(['tags', 'sourceLanguage', 'latestVersion'])
            ->inRandomOrder()
            ->first();

        if (! $game) {
            return response()->json(['error' => 'No games available'], 404);
        }

        $gameVersion = $game->latestVersion;
        $notificationType = 'update';

        $result = $routingService->evaluateRoutes($server, $game, $notificationType, $gameVersion);

        if ($result->shouldSkip) {
            return response()->json(['message' => 'Routing rules would skip this notification']);
        }

        $targetChannels = $result->getTargetChannels();
        if (empty($targetChannels)) {
            return response()->json(['error' => 'No target channels determined'], 422);
        }

        $target = $targetChannels[0];

        $embedTemplate = $target['embed_override']
            ?? ($notificationType === 'new_game' ? $config->new_game_embed : $config->update_embed)
            ?? ($notificationType === 'new_game' ? $renderer->getDefaultNewGameEmbed() : $renderer->getDefaultUpdateEmbed());

        $payload = [
            'embeds' => [$renderer->renderEmbed($embedTemplate, $game, $notificationType, $gameVersion, $server)],
        ];

        $notification = $server->notificationHistory()->create([
            'game_id' => $game->id,
            'notification_type' => 'manual',
            'channel_id' => $target['channel_id'],
            'delivery_status' => 'pending',
            'sent_at' => now(),
            'payload' => $payload,
        ]);

        return response()->json([
            'message' => 'Test notification queued',
            'notification' => $notification,
        ]);
    }

    private function fetchFreshGuilds(SocialAccount $discordAccount): ?array
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer '.$discordAccount->token,
                'Accept' => 'application/json',
            ])->get('https://discord.com/api/v10/users/@me/guilds');

            if (! $response->successful()) {
                throw new \RuntimeException('Discord returned HTTP '.$response->status());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::warning('Failed to fetch fresh Discord guilds', [
                'user_id' => $discordAccount->user_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchGuildChannelsWithBotToken(string $guildId): ?array
    {
        $botToken = config('services.discord.bot_token');
        if (! is_string($botToken) || trim($botToken) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bot '.$botToken,
                'Accept' => 'application/json',
            ])->get("https://discord.com/api/v10/guilds/{$guildId}/channels");

            if (! $response->successful()) {
                throw new \RuntimeException('Discord returned HTTP '.$response->status());
            }

            return collect($response->json())
                ->filter(fn (array $channel): bool => in_array((int) ($channel['type'] ?? -1), [0, 5], true))
                ->sortBy([
                    ['position', 'asc'],
                    ['name', 'asc'],
                ])
                ->map(fn (array $channel): array => [
                    'id' => (string) $channel['id'],
                    'name' => (string) $channel['name'],
                    'type' => (int) ($channel['type'] ?? 0),
                    'nsfw' => (bool) ($channel['nsfw'] ?? false),
                ])
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Discord guild channels with bot token', [
                'discord_server_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchGuildRolesWithBotToken(string $guildId): ?array
    {
        $botToken = config('services.discord.bot_token');
        if (! is_string($botToken) || trim($botToken) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bot '.$botToken,
                'Accept' => 'application/json',
            ])->get("https://discord.com/api/v10/guilds/{$guildId}/roles");

            if (! $response->successful()) {
                throw new \RuntimeException('Discord returned HTTP '.$response->status());
            }

            return collect($response->json())
                ->filter(fn (array $role): bool => (string) ($role['id'] ?? '') !== $guildId)
                ->filter(fn (array $role): bool => ! (bool) ($role['managed'] ?? false))
                ->sortByDesc(fn (array $role): int => (int) ($role['position'] ?? 0))
                ->values()
                ->map(fn (array $role): array => [
                    'id' => (string) $role['id'],
                    'name' => (string) $role['name'],
                    'color' => (int) ($role['color'] ?? 0),
                    'mentionable' => (bool) ($role['mentionable'] ?? false),
                    'position' => (int) ($role['position'] ?? 0),
                ])
                ->all();
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Discord guild roles with bot token', [
                'discord_server_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function canManageGuild(array $guild): bool
    {
        if (($guild['owner'] ?? false) === true) {
            return true;
        }

        $permissions = $guild['permissions_new'] ?? $guild['permissions'] ?? '0';
        $permissionBits = (int) $permissions;

        return ($permissionBits & self::DISCORD_PERMISSION_ADMINISTRATOR) !== 0
            || ($permissionBits & self::DISCORD_PERMISSION_MANAGE_GUILD) !== 0;
    }

    private function getBotInstallUrl(string $guildId): string
    {
        return route('dashboard.discord.install', ['guild' => $guildId]);
    }

    private function buildDiscordBotAuthorizationUrl(string $guildId, string $state): string
    {
        $clientId = config('services.discord.client_id');

        return 'https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id' => $clientId,
            'guild_id' => $guildId,
            'integration_type' => 0,
            'scope' => 'bot applications.commands',
            'permissions' => self::DISCORD_BOT_REQUIRED_PERMISSIONS,
            'response_type' => 'code',
            'redirect_uri' => route('dashboard.discord.install.callback'),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function getManagedGuildsForDiscordAccount(SocialAccount $discordAccount)
    {
        $guilds = $this->fetchFreshGuilds($discordAccount);

        if ($guilds === null) {
            $guilds = $discordAccount->provider_data['guilds'] ?? [];
        }

        return collect($guilds)
            ->filter(fn (array $guild): bool => $this->canManageGuild($guild))
            ->values();
    }

    private function getServerRelations(array $extra = []): array
    {
        $relations = [
            'config',
            'gameSubscriptions.game',
            'members',
            'gameOverrides.game',
        ];

        foreach ($extra as $name => $constraint) {
            $relations[$name] = $constraint;
        }

        return $relations;
    }

    private function getSampleGame(): ?Game
    {
        return Game::with(['tags', 'sourceLanguage', 'latestVersion'])
            ->where('is_visible', true)
            ->inRandomOrder()
            ->first();
    }
}
