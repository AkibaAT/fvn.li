<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import CogIcon from '@/components/icons/Cog.svelte';
    import DiscordIcon from '@/components/icons/Discord.svelte';
    import PlusIcon from '@/components/icons/Plus.svelte';
    import StatusDotIcon from '@/components/icons/StatusDot.svelte';
    import { Link } from '@inertiajs/svelte';
    import { fetchDiscordGuilds, type DiscordGuild } from '@/api/discord';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Alert, Badge, Button, Card } from '@/components/ui';

    let guilds = $state<DiscordGuild[]>([]);
    let hasDiscord = $state(true);
    let loading = $state(true);
    let error = $state<string | null>(null);

    $effect(() => {
        (async () => {
            loading = true;
            error = null;
            try {
                const data = await fetchDiscordGuilds();
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

<SeoHead title="Discord Servers - Dashboard" />

<div class="space-y-6">
    <PageHeader
        title="Discord Servers"
        description="Manage Discord server configurations for game notifications"
        backHref={route('dashboard')}
        backLabel="Back to Dashboard"
    />

    {#if !hasDiscord}
        <Alert title="Discord Account Required" role="status">
            <p>You need to connect your Discord account before managing server configurations.</p>
            {#snippet actions()}
                <a
                    href={route('auth.redirect', 'discord')}
                    class="inline-flex items-center gap-2 rounded-lg bg-[#5865F2] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#4752C4]"
                >
                    <DiscordIcon class="h-5 w-5" />
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
                <Button type="button" tone="danger" size="sm" onclick={() => window.location.reload()}>Retry</Button>
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
                                            <StatusDotIcon class="h-3 w-3" />
                                            Bot Active
                                        </Badge>
                                    {:else}
                                        <Badge tone="neutral" size="sm" class="gap-1">
                                            <StatusDotIcon class="h-3 w-3" />
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
                                    <CogIcon class="h-4 w-4" />
                                    Configure
                                </Link>
                            {:else if guild.bot_install_url}
                                <a
                                    href={guild.bot_install_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#5865F2] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#4752C4]"
                                >
                                    <PlusIcon class="h-4 w-4" />
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
