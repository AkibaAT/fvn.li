<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { apiFetch } from '@/hooks/api/client';
    import { toast } from '@/utils/toast';
    import RuleBuilder from './components/rule-builder.svelte';
    import EmbedEditor from './components/embed-editor.svelte';
    import VnOverrideManager from './components/vn-override-manager.svelte';

    interface DiscordChannel {
        id: string;
        name: string;
        type?: number;
        nsfw?: boolean;
    }

    interface DiscordRole {
        id: string;
        name: string;
        color?: number;
        mentionable?: boolean;
        position?: number;
    }

    interface GameOverride {
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

    interface ServerConfig {
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

    interface RoutingRule {
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

    interface RuleFieldMetadata {
        type: 'enum' | 'multi_enum' | 'boolean';
        operators: string[];
        options: RuleOption[];
    }

    interface RuleMetadataResponse {
        fields: Record<string, RuleFieldMetadata>;
    }

    interface DiscordServer {
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
    let channelPickerOpen = $state(false);
    let channelSearch = $state('');
    let channelPickerEl: HTMLDivElement | undefined = $state();
    let rolePickerOpen = $state(false);
    let roleSearch = $state('');
    let rolePickerEl: HTMLDivElement | undefined = $state();
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
                const data = await apiFetch<{ server: DiscordServer }>(route('browser-api.discord.servers.show', { server: serverId }));
                server = data.server;
                if (data.server.config) {
                    config = { ...config, ...data.server.config };
                }
                overrides = data.server.gameOverrides || [];
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
                const data = await apiFetch<{ channels: DiscordChannel[] }>(route('browser-api.discord.servers.channels', { server: serverId }));
                channels = data.channels;
            } catch {
                channels = server?.available_channels || [];
            }
        })();
    });

    $effect(() => {
        if (!server) return;
        (async () => {
            try {
                const data = await apiFetch<{ roles: DiscordRole[] }>(route('browser-api.discord.servers.roles', { server: serverId }));
                roles = data.roles;
            } catch {
                roles = [];
            }
        })();
    });

    $effect(() => {
        (async () => {
            try {
                const data = await apiFetch<RuleMetadataResponse>(route('browser-api.discord.rule-metadata'));
                ruleFieldMetadata = data.fields;
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
            await apiFetch(route('browser-api.discord.servers.config', { server: serverId }), {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
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
            const data = await apiFetch<{ message: string }>(route('browser-api.discord.servers.test-notification', { server: serverId }), {
                method: 'POST',
                body: JSON.stringify({}),
            });
            toast.success(data.message);
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

    function getSelectedChannelName(): string {
        if (!config.notification_channel_id) return '';
        return channels.find((channel) => channel.id === config.notification_channel_id)?.name ?? config.notification_channel_id;
    }

    function getSelectedChannel(): DiscordChannel | undefined {
        if (!config.notification_channel_id) return undefined;

        return channels.find((channel) => channel.id === config.notification_channel_id);
    }

    function selectNotificationChannel(channelId: string | null) {
        handleConfigChange('notification_channel_id', channelId);
        saveConfig({ notification_channel_id: channelId } as Partial<ServerConfig>);
        channelPickerOpen = false;
        channelSearch = '';
    }

    function getSelectedRoleName(): string {
        if (!config.ping_role_id) return '';

        return roles.find((role) => role.id === config.ping_role_id)?.name ?? config.ping_role_id;
    }

    function selectPingRole(roleId: string | null) {
        handleConfigChange('ping_role_id', roleId);
        saveConfig({ ping_role_id: roleId } as Partial<ServerConfig>);
        rolePickerOpen = false;
        roleSearch = '';
    }

    function getStatusColor(status: string): string {
        switch (status) {
            case 'sent':
                return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
            case 'failed':
                return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
            case 'pending':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
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

    const filteredChannels = $derived(
        channelSearch.trim() ? channels.filter((channel) => channel.name.toLowerCase().includes(channelSearch.trim().toLowerCase())) : channels,
    );

    const filteredRoles = $derived(
        roleSearch.trim() ? roles.filter((role) => role.name.toLowerCase().includes(roleSearch.trim().toLowerCase())) : roles,
    );

    $effect(() => {
        if (!channelPickerOpen) return;

        const handleClickOutside = (event: MouseEvent) => {
            if (channelPickerEl && !channelPickerEl.contains(event.target as Node)) {
                channelPickerOpen = false;
                channelSearch = '';
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    });

    $effect(() => {
        if (!rolePickerOpen) return;

        const handleClickOutside = (event: MouseEvent) => {
            if (rolePickerEl && !rolePickerEl.contains(event.target as Node)) {
                rolePickerOpen = false;
                roleSearch = '';
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    });
</script>

<svelte:head>
    <title>{server?.discord_server_name || 'Server'} - Discord Configuration</title>
</svelte:head>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <Link
                href={route('dashboard.discord.index')}
                class="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{server?.discord_server_name || 'Loading...'}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Discord server configuration</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
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
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    Saving...
                {:else}
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Save All
                {/if}
            </button>
        </div>
    </div>

    {#if loading}
        <div class="flex items-center justify-center py-20">
            <svg class="h-8 w-8 animate-spin text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
            </svg>
        </div>
    {:else if error}
        <div class="rounded-xl border border-red-200/50 bg-red-50/80 p-6 backdrop-blur-xl dark:border-red-800/50 dark:bg-red-900/20">
            <p class="text-red-700 dark:text-red-400">{error}</p>
            <button onclick={() => window.location.reload()} class="mt-3 rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                >Retry</button
            >
        </div>
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
            <div class="rounded-xl border border-gray-200/50 bg-white/70 p-6 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                <h2 class="mb-6 text-lg font-semibold text-gray-900 dark:text-white">General Settings</h2>
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-700 dark:text-gray-300">Server Active</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Enable or disable notifications for this server</div>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                class="peer sr-only"
                                checked={server?.is_active}
                                onchange={async () => {
                                    if (!server) return;
                                    server = { ...server, is_active: !server.is_active };
                                    try {
                                        await apiFetch(route('browser-api.discord.servers.config', { server: serverId }), {
                                            method: 'PUT',
                                            body: JSON.stringify({
                                                is_active: server.is_active,
                                            }),
                                        });
                                        toast.success(server.is_active ? 'Server activated' : 'Server deactivated');
                                    } catch {
                                        server = { ...server, is_active: !server.is_active };
                                        toast.error('Failed to toggle server status');
                                    }
                                }}
                            />
                            <div
                                class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-indigo-600 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700"
                            ></div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Default Notification Channel</label>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Select the channel where notifications will be sent by default</p>
                        {#if channels.length > 0}
                            <div class="relative mt-1" bind:this={channelPickerEl}>
                                <button
                                    type="button"
                                    onclick={() => {
                                        channelPickerOpen = !channelPickerOpen;
                                        if (!channelPickerOpen) channelSearch = '';
                                    }}
                                    class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    aria-expanded={channelPickerOpen}
                                    aria-haspopup="listbox"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span
                                            class="{config.notification_channel_id
                                                ? 'text-gray-900 dark:text-white'
                                                : 'text-gray-500 dark:text-gray-400'} truncate"
                                        >
                                            {config.notification_channel_id ? `#${getSelectedChannelName()}` : 'Select a channel...'}
                                        </span>
                                        {#if getSelectedChannel()?.nsfw}
                                            <span
                                                class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                            >
                                                NSFW
                                            </span>
                                        {/if}
                                    </span>
                                    <svg
                                        class="h-4 w-4 shrink-0 transition-transform {channelPickerOpen ? 'rotate-180' : ''}"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                {#if channelPickerOpen}
                                    <div
                                        class="absolute z-20 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
                                    >
                                        <div class="p-2">
                                            <input
                                                type="text"
                                                bind:value={channelSearch}
                                                placeholder="Type to filter channels..."
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            />
                                        </div>

                                        <div class="max-h-60 overflow-y-auto py-1" role="listbox">
                                            <button
                                                type="button"
                                                onclick={() => selectNotificationChannel(null)}
                                                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                                            >
                                                <span>Default / none</span>
                                                {#if !config.notification_channel_id}
                                                    <svg
                                                        class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                {/if}
                                            </button>

                                            {#if filteredChannels.length === 0}
                                                <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No channels found</div>
                                            {:else}
                                                {#each filteredChannels as ch (ch.id)}
                                                    <button
                                                        type="button"
                                                        onclick={() => selectNotificationChannel(ch.id)}
                                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                                                    >
                                                        <span class="flex min-w-0 items-center gap-2">
                                                            <span class="truncate">#{ch.name}</span>
                                                            {#if ch.nsfw}
                                                                <span
                                                                    class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                                                >
                                                                    NSFW
                                                                </span>
                                                            {/if}
                                                        </span>
                                                        {#if config.notification_channel_id === ch.id}
                                                            <svg
                                                                class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                                stroke="currentColor"
                                                                stroke-width="2"
                                                            >
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        {/if}
                                                    </button>
                                                {/each}
                                            {/if}
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        {:else}
                            <input
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ping Role ID</label>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Role to ping when notifications are sent (optional)</p>
                        {#if roles.length > 0}
                            <div class="relative" bind:this={rolePickerEl}>
                                <button
                                    type="button"
                                    onclick={() => {
                                        rolePickerOpen = !rolePickerOpen;
                                        if (!rolePickerOpen) roleSearch = '';
                                    }}
                                    class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    aria-expanded={rolePickerOpen}
                                    aria-haspopup="listbox"
                                >
                                    <span
                                        class="{config.ping_role_id ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'} truncate"
                                    >
                                        {config.ping_role_id ? `@${getSelectedRoleName()}` : 'Select a role...'}
                                    </span>
                                    <svg
                                        class="h-4 w-4 shrink-0 transition-transform {rolePickerOpen ? 'rotate-180' : ''}"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                {#if rolePickerOpen}
                                    <div
                                        class="absolute z-20 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
                                    >
                                        <div class="p-2">
                                            <input
                                                type="text"
                                                bind:value={roleSearch}
                                                placeholder="Type to filter roles..."
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            />
                                        </div>

                                        <div class="max-h-60 overflow-y-auto py-1" role="listbox">
                                            <button
                                                type="button"
                                                onclick={() => selectPingRole(null)}
                                                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                                            >
                                                <span>No ping role</span>
                                                {#if !config.ping_role_id}
                                                    <svg
                                                        class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                {/if}
                                            </button>

                                            {#if filteredRoles.length === 0}
                                                <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No roles found</div>
                                            {:else}
                                                {#each filteredRoles as role (role.id)}
                                                    <button
                                                        type="button"
                                                        onclick={() => selectPingRole(role.id)}
                                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                                                    >
                                                        <span class="truncate">@{role.name}</span>
                                                        {#if config.ping_role_id === role.id}
                                                            <svg
                                                                class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                                stroke="currentColor"
                                                                stroke-width="2"
                                                            >
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        {/if}
                                                    </button>
                                                {/each}
                                            {/if}
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        {:else}
                            <input
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
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                class="peer sr-only"
                                checked={config.include_game_description}
                                onchange={() => {
                                    handleConfigChange('include_game_description', !config.include_game_description);
                                    saveConfig({ include_game_description: !config.include_game_description } as Partial<ServerConfig>);
                                }}
                            />
                            <div
                                class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-indigo-600 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700"
                            ></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-700 dark:text-gray-300">Include Thumbnail</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Include the game thumbnail in notifications</div>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                class="peer sr-only"
                                checked={config.include_thumbnail}
                                onchange={() => {
                                    handleConfigChange('include_thumbnail', !config.include_thumbnail);
                                    saveConfig({ include_thumbnail: !config.include_thumbnail } as Partial<ServerConfig>);
                                }}
                            />
                            <div
                                class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-indigo-600 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700"
                            ></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-700 dark:text-gray-300">Include Ratings</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Include game ratings in notifications</div>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                class="peer sr-only"
                                checked={config.include_ratings}
                                onchange={() => {
                                    handleConfigChange('include_ratings', !config.include_ratings);
                                    saveConfig({ include_ratings: !config.include_ratings } as Partial<ServerConfig>);
                                }}
                            />
                            <div
                                class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-indigo-600 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700"
                            ></div>
                        </label>
                    </div>
                </div>
            </div>
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
                <div
                    class="rounded-xl border border-gray-200/50 bg-white/70 p-6 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70"
                >
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">New Game Embed</h2>
                    <EmbedEditor template={config.new_game_embed || {}} notificationType="new_game" {serverId} onchange={handleNewGameEmbedChange} />
                </div>
                <div
                    class="rounded-xl border border-gray-200/50 bg-white/70 p-6 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70"
                >
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Update Embed</h2>
                    <EmbedEditor template={config.update_embed || {}} notificationType="update" {serverId} onchange={handleUpdateEmbedChange} />
                </div>
            </div>
        {/if}

        {#if activeTab === 'history'}
            <div class="rounded-xl border border-gray-200/50 bg-white/70 p-6 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
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
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {getStatusColor(
                                                    entry.delivery_status,
                                                )}"
                                            >
                                                {entry.delivery_status}
                                            </span>
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
            </div>
        {/if}
    {/if}
</div>
