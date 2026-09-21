<script lang="ts">
    import { formatLocalDateTime } from '@/utils/date-formatting';
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
    import { page, router } from '@inertiajs/svelte';
    import { SvelteURL } from 'svelte/reactivity';

    interface Props {
        server: number;
    }

    let { server: serverId }: Props = $props();

    type Tab = 'general' | 'routing' | 'ignored' | 'overrides' | 'embeds' | 'history';
    const activeTab = $derived.by(() => {
        const tab = new SvelteURL(page.url, 'http://localhost').searchParams.get('tab');
        return tabs.find((item) => item.id === tab)?.id ?? 'general';
    });

    function setTab(tab: Tab) {
        if (tab === activeTab) return;
        const url = new SvelteURL(window.location.href);
        if (tab === 'general') url.searchParams.delete('tab');
        else url.searchParams.set('tab', tab);
        router.push({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true });
    }

    let server = $state<DiscordServer | null>(null);
    let channels = $state<DiscordChannel[]>([]);
    let roles = $state<DiscordRole[]>([]);
    let loading = $state(true);
    let error = $state<string | null>(null);
    let pendingSaves = $state(0);
    let embedJsonValid = $state({ new_game: true, update: true });
    let savedConfig: ServerConfig;
    const pendingConfigSaves: Partial<ServerConfig>[] = [];
    const saving = $derived(pendingSaves > 0);
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
        let active = true;
        (async () => {
            loading = true;
            error = null;
            try {
                const data = await fetchDiscordServer(serverId);
                if (!active) return;
                server = data;
                if (data.config) {
                    config = { ...config, ...data.config };
                }
                savedConfig = $state.snapshot(config);
                overrides = data.game_overrides || [];
            } catch (e) {
                if (active) error = e instanceof Error ? e.message : 'Failed to load server configuration';
            } finally {
                if (active) loading = false;
            }
        })();
        return () => {
            active = false;
        };
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
        if (!partial && (!embedJsonValid.new_game || !embedJsonValid.update)) {
            toast.error('Fix the embed JSON before saving.');
            return;
        }
        const payload = $state.snapshot(partial ?? config);
        pendingConfigSaves.push(payload);
        pendingSaves += 1;
        config = { ...config, ...payload };
        try {
            await updateDiscordServerConfig(serverId, payload);
            savedConfig = { ...savedConfig, ...payload };
            toast.success('Configuration saved');
            return true;
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Failed to save configuration');
            return false;
        } finally {
            pendingConfigSaves.splice(pendingConfigSaves.indexOf(payload), 1);
            config = Object.assign({}, savedConfig, ...pendingConfigSaves);
            pendingSaves -= 1;
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

    function handleRulesChange(rules: RoutingRule[]) {
        saveConfig({ routing_rules: rules } as Partial<ServerConfig>);
    }

    function handleOverridesChange(newOverrides: GameOverride[]) {
        overrides = newOverrides;
    }

    function handleNewGameEmbedChange(template: Record<string, unknown>) {
        return saveConfig({ new_game_embed: template } as Partial<ServerConfig>);
    }

    function handleUpdateEmbedChange(template: Record<string, unknown>) {
        return saveConfig({ update_embed: template } as Partial<ServerConfig>);
    }

    function selectNotificationChannel(channelId: string | null) {
        saveConfig({ notification_channel_id: channelId } as Partial<ServerConfig>);
    }

    function selectPingRole(roleId: string | null) {
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
    >
        {#snippet actions()}
            <button
                onclick={sendTestNotification}
                disabled={sendingTest || !config.notification_channel_id}
                class="inline-flex items-center gap-2 rounded-md border border-border-strong bg-surface px-4 py-2 text-sm font-medium text-fg transition-colors hover:border-fg disabled:cursor-not-allowed disabled:opacity-50"
            >
                {sendingTest ? 'Sending test...' : 'Send Test Notification'}
            </button>
            <button
                onclick={() => saveConfig()}
                disabled={saving}
                class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-on-accent transition-colors hover:opacity-90 disabled:opacity-50"
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
            <LoadingSpinner size="lg" class="text-fg-muted" currentColor label="Loading Discord server settings" />
        </div>
    {:else if error}
        <Alert title="Failed to load server" tone="danger">
            <p>{error}</p>
            {#snippet actions()}
                <Button type="button" tone="danger" size="sm" onclick={() => window.location.reload()}>Retry</Button>
            {/snippet}
        </Alert>
    {:else}
        <div class="mb-6 border-b border-border">
            <div class="-mb-px flex flex-wrap gap-x-6" aria-label="Server config tabs" role="tablist">
                {#each tabs as tab (tab.id)}
                    <button
                        onclick={() => setTab(tab.id)}
                        role="tab"
                        aria-selected={activeTab === tab.id}
                        class="border-b-2 px-1 py-3 text-sm font-medium transition-colors {activeTab === tab.id
                            ? 'border-accent text-fg'
                            : 'border-transparent text-fg-muted hover:border-border-strong hover:text-fg'}"
                    >
                        {tab.label}
                    </button>
                {/each}
            </div>
        </div>

        {#if activeTab === 'general'}
            <Card variant="flat" padding="lg">
                <h2 class="mb-6 text-lg font-semibold text-fg">General Settings</h2>
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-fg">Server Active</div>
                            <div class="text-sm text-fg-muted">Enable or disable notifications for this server</div>
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
                        <label for="notification-channel" class="block text-sm font-medium text-fg-muted">Default Notification Channel</label>
                        <p class="mb-2 text-xs text-fg-muted">Select the channel where notifications will be sent by default</p>
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
                                    saveConfig({ notification_channel_id: val || null } as Partial<ServerConfig>);
                                }}
                                class="mt-1 w-full rounded-md border border-border bg-surface-alt px-3 py-2 text-sm text-fg placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
                            />
                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                Channel sync has not run yet. Paste a channel ID manually for now.
                            </p>
                        {/if}
                    </div>

                    <div>
                        <label for="ping-role" class="block text-sm font-medium text-fg-muted">Ping Role ID</label>
                        <p class="mb-2 text-xs text-fg-muted">Role to ping when notifications are sent (optional)</p>
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
                                    saveConfig({ ping_role_id: val || null } as Partial<ServerConfig>);
                                }}
                                class="w-full rounded-md border border-border bg-surface-alt px-3 py-2 text-sm text-fg placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
                            />
                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                Role sync is not available. Paste a role ID manually for now.
                            </p>
                        {/if}
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-fg">Include Game Description</div>
                            <div class="text-sm text-fg-muted">Add the description to generated embeds. Custom embeds control their own layout.</div>
                        </div>
                        <Switch
                            checked={config.include_game_description}
                            ariaLabel="Include game description"
                            onchange={() => {
                                saveConfig({ include_game_description: !config.include_game_description } as Partial<ServerConfig>);
                            }}
                        />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-fg">Include Thumbnail</div>
                            <div class="text-sm text-fg-muted">Add the thumbnail to generated embeds. Custom embeds control their own layout.</div>
                        </div>
                        <Switch
                            checked={config.include_thumbnail}
                            ariaLabel="Include thumbnail"
                            onchange={() => {
                                saveConfig({ include_thumbnail: !config.include_thumbnail } as Partial<ServerConfig>);
                            }}
                        />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-fg">Include Ratings</div>
                            <div class="text-sm text-fg-muted">Add ratings to generated embeds. Custom embeds control their own layout.</div>
                        </div>
                        <Switch
                            checked={config.include_ratings}
                            ariaLabel="Include ratings"
                            onchange={() => {
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
                <Card variant="flat" padding="lg">
                    <h2 class="mb-4 text-lg font-semibold text-fg">New Game Embed</h2>
                    <EmbedEditor
                        template={config.new_game_embed || {}}
                        notificationType="new_game"
                        {serverId}
                        onvaliditychange={(valid) => (embedJsonValid.new_game = valid)}
                        onchange={handleNewGameEmbedChange}
                    />
                </Card>
                <Card variant="flat" padding="lg">
                    <h2 class="mb-4 text-lg font-semibold text-fg">Update Embed</h2>
                    <EmbedEditor
                        template={config.update_embed || {}}
                        notificationType="update"
                        {serverId}
                        onvaliditychange={(valid) => (embedJsonValid.update = valid)}
                        onchange={handleUpdateEmbedChange}
                    />
                </Card>
            </div>
        {/if}

        {#if activeTab === 'history'}
            <Card variant="flat" padding="lg">
                <h2 class="mb-4 text-lg font-semibold text-fg">Notification History</h2>
                {#if server?.notification_history && server.notification_history.length > 0}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Game</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Channel</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-fg-muted uppercase">Sent At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                {#each server.notification_history as entry (entry.id)}
                                    <tr class="hover:bg-surface-alt">
                                        <td class="px-4 py-3 text-sm text-fg">
                                            {entry.game?.name || `Game #${entry.game_id}`}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-fg-muted capitalize">
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
                                        <td class="px-4 py-3 font-mono text-sm text-fg-muted">
                                            {entry.channel_id}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-fg-muted">
                                            {formatLocalDateTime(entry.sent_at)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {:else}
                    <div class="py-8 text-center text-sm text-fg-muted">No notification history yet</div>
                {/if}
            </Card>
        {/if}
    {/if}
</div>
