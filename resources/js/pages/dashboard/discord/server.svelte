<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import CheckIcon from '@/components/icons/Check.svelte';
    import {
        fetchDiscordServer,
        fetchDiscordServerChannels,
        fetchDiscordServerRoles,
        fetchRuleMetadata,
        sendTestNotification as sendTestNotificationRequest,
        updateDiscordServerConfig,
        type DiscordChannel,
        type DiscordRole,
        type DiscordServer,
        type GameOverride,
        type RoutingRule,
        type RuleFieldMetadata,
        type ServerConfig,
    } from '@/api/discord';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { toast } from '@/utils/toast';
    import RuleBuilder from './components/RuleBuilder.svelte';
    import EmbedEditor from './components/EmbedEditor.svelte';
    import VnOverrideManager from './components/VnOverrideManager.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Alert, Badge, Button, Card, Switch } from '@/components/ui';
    import type { BadgeTone } from '@/components/ui/Badge.svelte';
    import ChannelPicker from './components/ChannelPicker.svelte';

    interface Props {
        server: number;
    }

    let { server: serverId }: Props = $props();

    type Tab = 'general' | 'routing' | 'ignored' | 'overrides' | 'embeds' | 'history';
    let activeTab = $state<Tab>('general');

    let server = $state<DiscordServer | null>(null);
    let channels = $state<DiscordChannel[]>([]);
    let roles = $state<DiscordRole[]>([]);
    let loading = $state(true);
    let error = $state<string | null>(null);
    let saving = $state(false);
    let sendingTest = $state(false);
    let ruleFieldMetadata = $state<Record<string, RuleFieldMetadata>>({});

    let config = $state<ServerConfig>({
        notification_channel_id: null,
        notification_format: 'compact',
        custom_template: null,
        include_game_description: false,
        include_thumbnail: true,
        include_ratings: false,
        ping_role_id: null,
        routing_rules: [],
        new_game_embed: null,
        update_embed: null,
    });

    let overrides = $state<GameOverride[]>([]);

    $effect(() => {
        (async () => {
            loading = true;
            error = null;
            try {
                const data = await fetchDiscordServer(serverId);
                server = data;
                if (data.config) {
                    config = { ...config, ...data.config };
                }
                overrides = data.gameOverrides || [];
            } catch (e) {
                error = e instanceof Error ? e.message : 'Failed to load server configuration';
            } finally {
                loading = false;
            }
        })();
    });

    $effect(() => {
        if (!server) return;
        (async () => {
            try {
                channels = await fetchDiscordServerChannels(serverId);
            } catch {
                channels = server?.available_channels || [];
            }
        })();
    });

    $effect(() => {
        if (!server) return;
        (async () => {
            try {
                roles = await fetchDiscordServerRoles(serverId);
            } catch {
                roles = [];
            }
        })();
    });

    $effect(() => {
        (async () => {
            try {
                ruleFieldMetadata = await fetchRuleMetadata();
            } catch {
                ruleFieldMetadata = {};
            }
        })();
    });

    async function saveConfig(partial?: Partial<ServerConfig>) {
        if (!server) return;
        saving = true;
        try {
            const payload = partial ? { ...config, ...partial } : config;
            config = { ...config, ...partial };
            await updateDiscordServerConfig(serverId, payload);
            toast.success('Configuration saved');
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to save configuration');
        } finally {
            saving = false;
        }
    }

    async function sendTestNotification() {
        if (!server || !config.notification_channel_id) return;

        sendingTest = true;
        try {
            const message = await sendTestNotificationRequest(serverId);
            toast.success(message);
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to queue test notification');
        } finally {
            sendingTest = false;
        }
    }

    function handleConfigChange(key: keyof ServerConfig, value: unknown) {
        config = { ...config, [key]: value };
    }

    function handleRulesChange(rules: RoutingRule[]) {
        config = { ...config, routing_rules: rules };
        saveConfig({ routing_rules: rules } as Partial<ServerConfig>);
    }

    function handleOverridesChange(newOverrides: GameOverride[]) {
        overrides = newOverrides;
    }

    function handleNewGameEmbedChange(template: Record<string, unknown>) {
        config = { ...config, new_game_embed: template };
        saveConfig({ new_game_embed: template } as Partial<ServerConfig>);
    }

    function handleUpdateEmbedChange(template: Record<string, unknown>) {
        config = { ...config, update_embed: template };
        saveConfig({ update_embed: template } as Partial<ServerConfig>);
    }

    function formatDate(dateStr: string): string {
        return new Date(dateStr).toLocaleString();
    }

    function selectNotificationChannel(channelId: string | null) {
        handleConfigChange('notification_channel_id', channelId);
        saveConfig({ notification_channel_id: channelId } as Partial<ServerConfig>);
    }

    function selectPingRole(roleId: string | null) {
        handleConfigChange('ping_role_id', roleId);
        saveConfig({ ping_role_id: roleId } as Partial<ServerConfig>);
    }

    function getStatusTone(status: string): BadgeTone {
        switch (status) {
            case 'sent':
                return 'success';
            case 'failed':
                return 'danger';
            case 'pending':
                return 'warning';
            default:
                return 'neutral';
        }
    }

    const tabs: { id: Tab; label: string }[] = [
        { id: 'general', label: 'General' },
        { id: 'routing', label: 'Routing Rules' },
        { id: 'ignored', label: 'Ignored VNs' },
        { id: 'overrides', label: 'VN Overrides' },
        { id: 'embeds', label: 'Embeds' },
        { id: 'history', label: 'History' },
    ];
</script>

<SeoHead title={`${server?.discord_server_name || 'Server'} - Discord Configuration`} />

<div class="space-y-6">
    <PageHeader
        title={server?.discord_server_name || 'Loading...'}
        description="Discord server configuration"
        backHref={route('dashboard.discord.index')}
        backLabel="Back to Discord Servers"
        class="mb-0"
    >
        {#snippet actions()}
            <button
                onclick={sendTestNotification}
                disabled={sendingTest || !config.notification_channel_id}
                class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-700 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
            >
                {sendingTest ? 'Sending test...' : 'Send Test Notification'}
            </button>
            <button
                onclick={() => saveConfig()}
                disabled={saving}
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
            >
                {#if saving}
                    <LoadingSpinner size="sm" currentColor isBusy={false} />
                    Saving...
                {:else}
                    <CheckIcon class="h-4 w-4" />
                    Save All
                {/if}
            </button>
        {/snippet}
    </PageHeader>

    {#if loading}
        <div class="flex items-center justify-center py-20">
            <LoadingSpinner size="lg" class="text-indigo-600 dark:text-indigo-400" currentColor label="Loading Discord server settings" />
        </div>
    {:else if error}
        <Alert title="Failed to load server" tone="danger">
            <p>{error}</p>
            {#snippet actions()}
                <Button type="button" tone="danger" size="sm" onclick={() => window.location.reload()}>Retry</Button>
            {/snippet}
        </Alert>
    {:else}
        <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-6" aria-label="Server config tabs">
                {#each tabs as tab (tab.id)}
                    <button
                        onclick={() => (activeTab = tab.id)}
                        class="border-b-2 px-1 py-3 text-sm font-medium transition-colors {activeTab === tab.id
                            ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300'}"
                    >
                        {tab.label}
                    </button>
                {/each}
            </nav>
        </div>

        {#if activeTab === 'general'}
            <Card variant="glass" padding="lg">
                <h2 class="mb-6 text-lg font-semibold text-gray-900 dark:text-white">General Settings</h2>
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-700 dark:text-gray-300">Server Active</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Enable or disable notifications for this server</div>
                        </div>
                        <Switch
                            checked={Boolean(server?.is_active)}
                            ariaLabel="Enable server notifications"
                            onchange={async () => {
                                if (!server) return;
                                server = { ...server, is_active: !server.is_active };
                                try {
                                    await updateDiscordServerConfig(serverId, { is_active: server.is_active });
                                    toast.success(server.is_active ? 'Server activated' : 'Server deactivated');
                                } catch {
                                    server = { ...server, is_active: !server.is_active };
                                    toast.error('Failed to toggle server status');
                                }
                            }}
                        />
                    </div>

                    <div>
                        <label for="notification-channel" class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >Default Notification Channel</label
                        >
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Select the channel where notifications will be sent by default</p>
                        {#if channels.length > 0}
                            <ChannelPicker
                                id="notification-channel"
                                items={channels}
                                value={config.notification_channel_id}
                                placeholder="Select a channel..."
                                searchPlaceholder="Type to filter channels..."
                                emptyLabel="No channels found"
                                allowNone
                                noneLabel="Default / none"
                                onselect={selectNotificationChannel}
                            />
                        {:else}
                            <input
                                id="notification-channel"
                                type="text"
                                value={config.notification_channel_id || ''}
                                placeholder="Enter Discord channel ID"
                                onchange={(e) => {
                                    const val = (e.target as HTMLInputElement).value.trim();
                                    handleConfigChange('notification_channel_id', val || null);
                                    saveConfig({ notification_channel_id: val || null } as Partial<ServerConfig>);
                                }}
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                Channel sync has not run yet. Paste a channel ID manually for now.
                            </p>
                        {/if}
                    </div>

                    <div>
                        <label for="ping-role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ping Role ID</label>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Role to ping when notifications are sent (optional)</p>
                        {#if roles.length > 0}
                            <ChannelPicker
                                id="ping-role"
                                items={roles}
                                value={config.ping_role_id}
                                placeholder="Select a role..."
                                searchPlaceholder="Type to filter roles..."
                                emptyLabel="No roles found"
                                prefix="@"
                                allowNone
                                noneLabel="No ping role"
                                onselect={selectPingRole}
                            />
                        {:else}
                            <input
                                id="ping-role"
                                type="text"
                                value={config.ping_role_id || ''}
                                placeholder="Enter Discord role ID"
                                onchange={(e) => {
                                    const val = (e.target as HTMLInputElement).value.trim();
                                    handleConfigChange('ping_role_id', val || null);
                                    saveConfig({ ping_role_id: val || null } as Partial<ServerConfig>);
                                }}
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                Role sync is not available. Paste a role ID manually for now.
                            </p>
                        {/if}
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-700 dark:text-gray-300">Include Game Description</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Include the game description in notifications</div>
                        </div>
                        <Switch
                            checked={config.include_game_description}
                            ariaLabel="Include game description"
                            onchange={() => {
                                handleConfigChange('include_game_description', !config.include_game_description);
                                saveConfig({ include_game_description: !config.include_game_description } as Partial<ServerConfig>);
                            }}
                        />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-700 dark:text-gray-300">Include Thumbnail</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Include the game thumbnail in notifications</div>
                        </div>
                        <Switch
                            checked={config.include_thumbnail}
                            ariaLabel="Include thumbnail"
                            onchange={() => {
                                handleConfigChange('include_thumbnail', !config.include_thumbnail);
                                saveConfig({ include_thumbnail: !config.include_thumbnail } as Partial<ServerConfig>);
                            }}
                        />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-700 dark:text-gray-300">Include Ratings</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Include game ratings in notifications</div>
                        </div>
                        <Switch
                            checked={config.include_ratings}
                            ariaLabel="Include ratings"
                            onchange={() => {
                                handleConfigChange('include_ratings', !config.include_ratings);
                                saveConfig({ include_ratings: !config.include_ratings } as Partial<ServerConfig>);
                            }}
                        />
                    </div>
                </div>
            </Card>
        {/if}

        {#if activeTab === 'routing'}
            <RuleBuilder rules={config.routing_rules || []} {channels} fieldMetadata={ruleFieldMetadata} onchange={handleRulesChange} />
        {/if}

        {#if activeTab === 'ignored'}
            <VnOverrideManager {overrides} {serverId} {channels} onchange={handleOverridesChange} filter="ignored" />
        {/if}

        {#if activeTab === 'overrides'}
            <VnOverrideManager {overrides} {serverId} {channels} onchange={handleOverridesChange} />
        {/if}

        {#if activeTab === 'embeds'}
            <div class="space-y-6">
                <Card variant="glass" padding="lg">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">New Game Embed</h2>
                    <EmbedEditor template={config.new_game_embed || {}} notificationType="new_game" {serverId} onchange={handleNewGameEmbedChange} />
                </Card>
                <Card variant="glass" padding="lg">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Update Embed</h2>
                    <EmbedEditor template={config.update_embed || {}} notificationType="update" {serverId} onchange={handleUpdateEmbedChange} />
                </Card>
            </div>
        {/if}

        {#if activeTab === 'history'}
            <Card variant="glass" padding="lg">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Notification History</h2>
                {#if server?.notificationHistory && server.notificationHistory.length > 0}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >Game</th
                                    >
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >Type</th
                                    >
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >Status</th
                                    >
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >Channel</th
                                    >
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >Sent At</th
                                    >
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                {#each server.notificationHistory as entry (entry.id)}
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            {entry.game?.name || `Game #${entry.game_id}`}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 capitalize dark:text-gray-400">
                                            {entry.notification_type?.replace('_', ' ')}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <Badge tone={getStatusTone(entry.delivery_status)} size="sm">
                                                {entry.delivery_status}
                                            </Badge>
                                            {#if entry.error_message}
                                                <div class="mt-1 text-xs text-red-500">{entry.error_message}</div>
                                            {/if}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">
                                            {entry.channel_id}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            {formatDate(entry.sent_at)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {:else}
                    <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">No notification history yet</div>
                {/if}
            </Card>
        {/if}
    {/if}
</div>
