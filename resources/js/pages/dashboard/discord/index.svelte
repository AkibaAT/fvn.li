<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { apiFetch } from '@/utils/http';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Alert, Badge, Card } from '@/components/ui';

    interface DiscordGuild {
        id: string;
        name: string;
        icon: string | null;
        owner: boolean;
        has_bot: boolean;
        server?: { id: number; discord_server_id: string; discord_server_name: string } | null;
        bot_install_url: string | null;
    }

    interface GuildsResponse {
        guilds: DiscordGuild[];
        has_discord: boolean;
    }

    let guilds = $state<DiscordGuild[]>([]);
    let hasDiscord = $state(true);
    let loading = $state(true);
    let error = $state<string | null>(null);

    $effect(() => {
        (async () => {
            loading = true;
            error = null;
            try {
                const data = await apiFetch<GuildsResponse>(route('browser-api.discord.guilds'));
                guilds = data.guilds;
                hasDiscord = data.has_discord;
            } catch (e) {
                error = e instanceof Error ? e.message : 'Failed to load Discord guilds';
            } finally {
                loading = false;
            }
        })();
    });

    function getGuildIconUrl(guild: DiscordGuild): string {
        if (!guild.icon) return '';
        return `https://cdn.discordapp.com/icons/${guild.id}/${guild.icon}.png?size=128`;
    }
</script>

<svelte:head>
    <title>Discord Servers - Dashboard</title>
</svelte:head>

<div class="space-y-6">
    <PageHeader
        title="Discord Servers"
        description="Manage Discord server configurations for game notifications"
        backHref={route('dashboard')}
        backLabel="Back to Dashboard"
        class="mb-0"
    />

    {#if !hasDiscord}
        <Alert title="Discord Account Required">
            <p>You need to connect your Discord account before managing server configurations.</p>
            {#snippet actions()}
                <a
                    href={route('auth.redirect', 'discord')}
                    class="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#5865F2] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#4752C4]"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"
                        ><path
                            d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"
                        /></svg
                    >
                    Connect Discord
                </a>
            {/snippet}
        </Alert>
    {:else if loading}
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {#each Array(6) as _, i (i)}
                <Card variant="glass" padding="lg" class="animate-pulse shadow-none">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-4 w-3/4 rounded bg-gray-200 dark:bg-gray-700"></div>
                            <div class="h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
                        </div>
                    </div>
                </Card>
            {/each}
        </div>
    {:else if error}
        <Alert title="Failed to load servers" tone="danger">
            <p>{error}</p>
            {#snippet actions()}
                <button
                    onclick={() => window.location.reload()}
                    class="mt-3 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                >
                    Retry
                </button>
            {/snippet}
        </Alert>
    {:else if guilds.length === 0}
        <Card variant="glass" padding="lg" class="p-12 text-center shadow-none">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">No servers found</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                You don't manage any Discord servers where you have admin or "Manage Server" permissions.
            </p></Card
        >
    {:else}
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {#each guilds as guild (guild.id)}
                <Card variant="glass" padding="none" class="overflow-hidden transition-shadow hover:shadow-xl">
                    <div class="p-6">
                        <div class="flex items-center gap-4">
                            {#if guild.icon}
                                <img src={getGuildIconUrl(guild)} alt={guild.name} class="h-12 w-12 shrink-0 rounded-full" />
                            {:else}
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#5865F2] text-lg font-bold text-white"
                                >
                                    {guild.name.charAt(0).toUpperCase()}
                                </div>
                            {/if}
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate font-semibold text-gray-900 dark:text-white">{guild.name}</h3>
                                <div class="mt-1 flex items-center gap-2">
                                    {#if guild.owner}
                                        <Badge tone="warning" size="sm">Owner</Badge>
                                    {/if}
                                    {#if guild.has_bot}
                                        <Badge tone="success" size="sm" class="gap-1">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" /></svg>
                                            Bot Active
                                        </Badge>
                                    {:else}
                                        <Badge tone="neutral" size="sm" class="gap-1">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" /></svg>
                                            No Bot
                                        </Badge>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            {#if guild.has_bot && guild.server}
                                <Link
                                    href={route('dashboard.discord.server', { server: guild.server.id })}
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                        />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Configure
                                </Link>
                            {:else if guild.bot_install_url}
                                <a
                                    href={guild.bot_install_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#5865F2] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#4752C4]"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add to Server
                                </a>
                            {/if}
                        </div>
                    </div>
                </Card>
            {/each}
        </div>
    {/if}
</div>
