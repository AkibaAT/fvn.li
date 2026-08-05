import http from '@/utils/http';

export interface DiscordGuild {
    id: string;
    name: string;
    icon: string | null;
    owner: boolean;
    has_bot: boolean;
    server?: { id: number; discord_server_id: string; discord_server_name: string } | null;
    bot_install_url: string | null;
}

export interface GuildsResponse {
    guilds: DiscordGuild[];
    has_discord: boolean;
}

export interface DiscordChannel {
    id: string;
    name: string;
    type?: number;
    nsfw?: boolean;
}

export interface DiscordRole {
    id: string;
    name: string;
    color?: number;
    mentionable?: boolean;
    position?: number;
}

export interface GameOverride {
    id: number;
    game_id: number;
    is_ignored: boolean;
    channel_id: string | null;
    new_game_embed: Record<string, unknown> | null;
    update_embed: Record<string, unknown> | null;
    game: { id: number; name: string; slug: string; thumb_url?: string };
}

interface NotificationHistoryEntry {
    id: number;
    game_id: number;
    notification_type: string;
    channel_id: string;
    delivery_status: string;
    error_message: string | null;
    sent_at: string;
    game?: { id: number; name: string; slug: string };
}

export interface ServerConfig {
    id?: number;
    discord_server_id?: number;
    notification_channel_id: string | null;
    notification_format: string;
    custom_template: string | null;
    include_game_description: boolean;
    include_thumbnail: boolean;
    include_ratings: boolean;
    ping_role_id: string | null;
    routing_rules: RoutingRule[];
    new_game_embed: Record<string, unknown> | null;
    update_embed: Record<string, unknown> | null;
}

export interface RoutingRule {
    id: string;
    name: string;
    enabled: boolean;
    priority: number;
    conditions: RuleCondition[];
    action: { type: 'ignore' | 'route'; channel_id?: string };
}

interface RuleCondition {
    field: string;
    operator: string;
    value: string | string[] | boolean;
}

interface RuleOption {
    value: string | boolean;
    label: string;
}

export interface RuleFieldMetadata {
    type: 'enum' | 'multi_enum' | 'boolean';
    operators: string[];
    options: RuleOption[];
}

export interface DiscordServer {
    id: number;
    discord_server_id: string;
    discord_server_name: string;
    is_active: boolean;
    bot_joined_at: string | null;
    available_channels: DiscordChannel[];
    channels_synced_at: string | null;
    config: ServerConfig | null;
    gameOverrides: GameOverride[];
    notificationHistory: NotificationHistoryEntry[];
}

export interface GameSearchResult {
    id: number;
    name: string;
    slug: string;
    thumb_url?: string;
    cover_image?: string;
}

export async function fetchDiscordGuilds(): Promise<GuildsResponse> {
    const { data } = await http.get<GuildsResponse>(route('browser-api.discord.guilds'));
    return data;
}

export async function fetchDiscordServer(serverId: number): Promise<DiscordServer> {
    const { data } = await http.get<{ server: DiscordServer }>(route('browser-api.discord.servers.show', { server: serverId }));
    return data.server;
}

export async function fetchDiscordServerChannels(serverId: number): Promise<DiscordChannel[]> {
    const { data } = await http.get<{ channels: DiscordChannel[] }>(route('browser-api.discord.servers.channels', { server: serverId }));
    return data.channels;
}

export async function fetchDiscordServerRoles(serverId: number): Promise<DiscordRole[]> {
    const { data } = await http.get<{ roles: DiscordRole[] }>(route('browser-api.discord.servers.roles', { server: serverId }));
    return data.roles;
}

export async function fetchRuleMetadata(): Promise<Record<string, RuleFieldMetadata>> {
    const { data } = await http.get<{ fields: Record<string, RuleFieldMetadata> }>(route('browser-api.discord.rule-metadata'));
    return data.fields;
}

export async function updateDiscordServerConfig(serverId: number, payload: Partial<ServerConfig> & { is_active?: boolean }): Promise<void> {
    await http.put(route('browser-api.discord.servers.config', { server: serverId }), payload);
}

export async function sendTestNotification(serverId: number): Promise<string> {
    const { data } = await http.post<{ message: string }>(route('browser-api.discord.servers.test-notification', { server: serverId }), {});
    return data.message;
}

export async function previewEmbed(
    serverId: number,
    embedTemplate: Record<string, unknown>,
    notificationType: string,
): Promise<Record<string, unknown>> {
    const { data } = await http.post<{ embed: Record<string, unknown> }>(route('browser-api.discord.servers.preview-embed', { server: serverId }), {
        embed_template: embedTemplate,
        notification_type: notificationType,
    });
    return data.embed;
}

export async function searchGames(query: string, limit = 10): Promise<GameSearchResult[]> {
    const { data } = await http.get<GameSearchResult[] | { games?: GameSearchResult[] }>(route('api.games.search'), {
        params: { q: query, limit },
    });
    return Array.isArray(data) ? data : (data.games ?? []);
}

export async function createGameOverride(
    serverId: number,
    payload: { game_id: number; is_ignored: boolean; channel_id: string | null },
): Promise<GameOverride> {
    const { data } = await http.post<{ override: GameOverride }>(route('browser-api.discord.servers.overrides.store', { server: serverId }), payload);
    return data.override;
}

export async function updateGameOverride(
    serverId: number,
    overrideId: number,
    payload: { is_ignored?: boolean; channel_id?: string | null },
): Promise<GameOverride> {
    const { data } = await http.put<{ override: GameOverride }>(
        route('browser-api.discord.servers.overrides.update', { server: serverId, override: overrideId }),
        payload,
    );
    return data.override;
}

export async function deleteGameOverride(serverId: number, overrideId: number): Promise<void> {
    await http.delete(route('browser-api.discord.servers.overrides.delete', { server: serverId, override: overrideId }));
}
