<script lang="ts">
    import { untrack } from 'svelte';
    import BugReports from '@/components/dashboard/BugReports.svelte';
    import ConnectedAccounts from '@/components/dashboard/ConnectedAccounts.svelte';
    import AdditionsTab from '@/components/dashboard/AdditionsTab.svelte';
    import MyGamesTab from '@/components/dashboard/MyGamesTab.svelte';
    import SearchPreferencesTab from '@/components/dashboard/SearchPreferencesTab.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { authenticatedFetch } from '@/utils/http';
    import { toast } from '@/utils/toast';
    import { Link } from '@inertiajs/svelte';
    import { Button, Card, Switch } from '@/components/ui';
    import type { User, SocialAccount } from '@/types';
    interface NotificationPreferences {
        browser_notifications_enabled: boolean;
        discord_notifications_enabled: boolean;
        notification_digest: string;
    }
    interface AdditionRequest {
        id: number;
        game_url: string;
        platform?: string;
        status: string;
        status_label: string;
        status_color: string;
        created_at: string;
        reviewed_at?: string;
        rejection_reason?: string;
        game?: { id: number; name: string; slug: string };
        reviewer?: { name: string };
    }
    interface GameSummary {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string | null;
        has_additional_links?: boolean;
        platform?: 'itch_io' | 'steam' | 'other';
    }
    interface GameClickStats {
        page_views_total: number;
        page_views_unique: number;
        external_project_total: number;
        external_project_unique: number;
        custom_link_clicks_total: number;
        custom_link_clicks_unique: number;
    }
    interface IgnoredGame {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string;
        optimized_thumbnails?: { default?: { path: string; width: number; height: number } };
        platform?: 'itch_io' | 'steam' | 'other';
    }
    interface DiscordNotificationStatus {
        status: 'pending' | 'processing' | 'sent' | 'failed';
        error: string | null;
        processedAt: string | null;
        createdAt: string;
    }
    interface DiscordInfo {
        hasAccount: boolean;
        botInstallUrl: string | null;
        lastNotification: DiscordNotificationStatus | null;
    }

    interface DashboardProps {
        features: { discordServerBot: boolean };
        user: User;
        connectedProviders: string[];
        socialAccounts: Record<string, SocialAccount>;
        itchioData: { username?: string };
        myGames: GameSummary[];
        myGamesClickStats: { [gameId: string]: GameClickStats } | null;
        notificationPreferences: NotificationPreferences;
        discordInfo?: DiscordInfo | null;
        recentRequests: AdditionRequest[];
        ignoredGames: IgnoredGame[];
        ignoredGamesCount: number;
        languagePreferences: string[];
        availableLanguages: Record<string, { ref_name: string; flag_code: string }>;
        excludedTagPreferences: number[];
        availableTags: Record<string, string>;
        activeBugReports?: Array<{
            id: number;
            page_title?: string;
            description: string;
            status: string;
            status_label: string;
            status_color: string;
            unread_count: number;
            created_at: string;
        }>;
        totalUnreadBugReportReplies?: number;
        metaTags?: { title?: string };
        vapidPublicKey?: string;
    }

    let {
        user,
        connectedProviders,
        socialAccounts,
        itchioData,
        myGames,
        myGamesClickStats,
        notificationPreferences: notificationPreferencesInitial,
        discordInfo,
        recentRequests: recentRequestsInitial,
        ignoredGames: ignoredGamesInitial,
        ignoredGamesCount: ignoredGamesCountInitial,
        languagePreferences: languagePreferencesInitial,
        availableLanguages,
        excludedTagPreferences: excludedTagPreferencesInitial,
        availableTags,
        activeBugReports,
        metaTags,
        vapidPublicKey,
    }: DashboardProps = $props();

    const openBugReportId = $derived(() => {
        if (typeof window === 'undefined') return null;
        const params = new URLSearchParams(window.location.search);
        const id = params.get('bug_report');
        return id ? parseInt(id, 10) : null;
    });

    // --- Tab state ---
    type Tab = 'account' | 'my-games' | 'additions' | 'search';
    const hasItchio = $derived(!!itchioData?.username);
    const allTabs: { id: Tab; label: string; condition?: boolean }[] = [
        { id: 'account', label: 'Account' },
        { id: 'my-games', label: 'My Games' },
        { id: 'additions', label: 'VN Additions' },
        { id: 'search', label: 'Search Preferences' },
    ];
    const tabs = $derived(allTabs.filter((t) => t.condition !== false));

    function tabFromHash(): Tab {
        if (typeof window !== 'undefined') {
            const hash = window.location.hash.slice(1);
            if (hash === 'my-games' || hash === 'additions' || hash === 'search') return hash;
        }
        return 'account';
    }
    let activeTab = $state<Tab>(tabFromHash());

    function setTab(tab: Tab) {
        if (tab === activeTab) return;
        activeTab = tab;
        if (typeof window !== 'undefined') {
            const url = tab === 'account' ? window.location.pathname : `${window.location.pathname}#${tab}`;
            window.history.pushState({ tab }, '', url);
        }
    }

    $effect(() => {
        const onPopState = () => {
            activeTab = tabFromHash();
        };
        window.addEventListener('popstate', onPopState);
        return () => window.removeEventListener('popstate', onPopState);
    });

    // --- Account tab state ---
    let notifPrefs = $state(untrack(() => notificationPreferencesInitial));
    let savingPrefs = $state(false);

    async function jsonPost<T>(url: string, payload: unknown): Promise<T> {
        const res = await authenticatedFetch(url, { method: 'POST', body: JSON.stringify(payload) });
        const data = await res.json();
        if (!res.ok || data?.success === false) {
            if (data?.errors && typeof data.errors === 'object') {
                Object.values<string | string[]>(data.errors)
                    .flat()
                    .forEach((m) => toast.error(String(m)));
            }
            if (data?.message) toast.error(String(data.message));
            throw new Error(data?.message || 'Request failed');
        }
        return data;
    }

    const toggleBrowser = async () => {
        const next = { ...notifPrefs, browser_notifications_enabled: !notifPrefs.browser_notifications_enabled };
        notifPrefs = next;
        savingPrefs = true;
        try {
            await jsonPost(route('browser-api.dashboard.notifications.update'), next);
        } catch {
            notifPrefs = { ...notifPrefs, browser_notifications_enabled: !next.browser_notifications_enabled };
        } finally {
            savingPrefs = false;
        }
    };

    const toggleDiscord = async () => {
        const next = { ...notifPrefs, discord_notifications_enabled: !notifPrefs.discord_notifications_enabled };
        notifPrefs = next;
        savingPrefs = true;
        try {
            await jsonPost(route('browser-api.dashboard.notifications.update'), next);
        } catch {
            notifPrefs = { ...notifPrefs, discord_notifications_enabled: !next.discord_notifications_enabled };
        } finally {
            savingPrefs = false;
        }
    };

    const updateDigest = async (value: string) => {
        if (value === notifPrefs.notification_digest) return;
        const prev = notifPrefs.notification_digest;
        notifPrefs = { ...notifPrefs, notification_digest: value };
        savingPrefs = true;
        try {
            await jsonPost(route('browser-api.dashboard.notifications.update'), {
                ...notifPrefs,
                notification_digest: value,
            });
        } catch {
            notifPrefs = { ...notifPrefs, notification_digest: prev };
        } finally {
            savingPrefs = false;
        }
    };

    const handleExportData = () => {
        if (typeof window !== 'undefined') window.location.href = route('browser-api.user.export');
    };
</script>

<svelte:head>
    <title>{metaTags?.title || 'Dashboard'}</title>
</svelte:head>

<PageHeader title={metaTags?.title || 'Dashboard'} class="mb-6" />

<div class="mb-6 border-b border-gray-200 dark:border-gray-700">
    <div class="-mb-px flex space-x-6" aria-label="Dashboard tabs" role="tablist">
        {#each tabs as tab (tab.id)}
            <Button
                type="button"
                variant="link"
                tone="info"
                onclick={() => setTab(tab.id)}
                class="border-b-2 px-1 py-3 text-sm font-medium transition-colors {activeTab === tab.id
                    ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300'}"
                aria-selected={activeTab === tab.id}
                role="tab"
            >
                {tab.label}
            </Button>
        {/each}
    </div>
</div>

<BugReports initialReports={activeBugReports || []} openReportId={openBugReportId()} />

{#if activeTab === 'account'}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <Card padding="lg">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Profile Information</h2>
                <div class="flex items-center gap-4">
                    {#if user.avatar}
                        <img src={user.avatar} alt={user.name} class="h-16 w-16 rounded-full" />
                    {:else}
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-xl font-bold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                        >
                            {user.name?.charAt(0)?.toUpperCase() || '?'}
                        </div>
                    {/if}
                    <div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{user.name}</div>
                        {#if user.email}
                            <div class="text-sm text-gray-500 dark:text-gray-400">{user.email}</div>
                        {/if}
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex gap-3">
                        <Button
                            type="button"
                            variant="solid"
                            tone="primary"
                            onclick={handleExportData}
                            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                        >
                            Export My Data
                        </Button>
                        <Link
                            href={route('users.reviews', user.id)}
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            My Reviews
                        </Link>
                    </div>
                </div>
            </Card>

            <Card padding="lg">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Notification Settings</h2>
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="flex-grow">
                            <div class="font-medium text-gray-700 dark:text-gray-300">Browser Push Notifications</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Receive notifications directly in your browser when games are updated.
                            </div>
                        </div>
                        <div class="flex items-center">
                            {#if typeof Notification !== 'undefined' && Notification.permission !== 'granted'}
                                <Button
                                    type="button"
                                    variant="solid"
                                    tone="info"
                                    onclick={async () => {
                                        const result = await Notification.requestPermission();
                                        if (result === 'granted' && vapidPublicKey) {
                                            // Permission granted
                                        }
                                    }}
                                    class="mr-3 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                >
                                    Request Permission
                                </Button>
                            {/if}
                            <Switch
                                checked={notifPrefs.browser_notifications_enabled}
                                onchange={toggleBrowser}
                                disabled={savingPrefs}
                                ariaLabel="Enable browser notifications"
                            />
                        </div>
                    </div>

                    {#if discordInfo?.hasAccount}
                        <div class="flex items-center gap-4">
                            <div class="flex-grow">
                                <div class="font-medium text-gray-700 dark:text-gray-300">Discord Notifications</div>
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Receive notifications via Discord when games are updated.
                                </div>
                            </div>
                            <div>
                                <Switch
                                    checked={notifPrefs.discord_notifications_enabled}
                                    onchange={toggleDiscord}
                                    disabled={savingPrefs}
                                    ariaLabel="Enable Discord notifications"
                                />
                            </div>
                        </div>
                        {#if discordInfo.lastNotification}
                            {@const lastNotif = discordInfo.lastNotification}
                            {#if lastNotif.status === 'sent'}
                                <div class="mt-3 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-900/20">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg
                                        >
                                        <p class="text-sm text-green-700 dark:text-green-400">
                                            Discord notifications are working! Last notification sent successfully{#if lastNotif.processedAt}<span
                                                    class="text-green-600 dark:text-green-500"
                                                >
                                                    on {new Date(lastNotif.processedAt).toLocaleDateString()}</span
                                                >{/if}
                                        </p>
                                    </div>
                                </div>
                            {:else if lastNotif.status === 'failed'}
                                <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg
                                        >
                                        <p class="text-sm text-red-700 dark:text-red-400">
                                            Last Discord notification failed.{#if lastNotif.error}
                                                Error: {lastNotif.error}{/if}
                                        </p>
                                    </div>
                                </div>
                            {/if}
                        {/if}
                    {/if}

                    <div>
                        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Notification Frequency</div>
                        <div class="mb-3 text-xs text-gray-500 dark:text-gray-400">Choose how often you'd like to receive update notifications.</div>
                        <div class="grid grid-cols-3 gap-3">
                            {#each [{ value: 'asap', label: 'As soon as possible', desc: 'Get notified immediately when games are updated' }, { value: 'daily', label: 'Daily digest', desc: 'Get a summary of all updates once per day' }, { value: 'weekly', label: 'Weekly digest', desc: 'Get a summary of all updates once per week' }] as freq (freq.value)}
                                <Button
                                    type="button"
                                    variant={notifPrefs.notification_digest === freq.value ? 'outline' : 'outline'}
                                    tone="info"
                                    onclick={() => updateDigest(freq.value)}
                                    disabled={savingPrefs}
                                    class="rounded-lg border-2 p-3 text-left text-sm transition-colors {notifPrefs.notification_digest === freq.value
                                        ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-900/20'
                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-600 dark:hover:border-gray-500'}"
                                >
                                    <div class="font-medium text-gray-900 dark:text-white">{freq.label}</div>
                                    <div class="mt-1 text-xs text-gray-700 dark:text-gray-300">{freq.desc}</div>
                                </Button>
                            {/each}
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <Card padding="lg">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Connected Accounts</h2>
                <ConnectedAccounts {user} {connectedProviders} {socialAccounts} />
            </Card>
        </div>
    </div>
{/if}

<!-- Tab components stay mounted and are toggled with `hidden` so their local
     state (drafts, saved preferences, ignored games) survives tab switches. -->

<div hidden={activeTab !== 'my-games'}>
    <MyGamesTab {hasItchio} {itchioData} {myGames} {myGamesClickStats} />
</div>

<div hidden={activeTab !== 'additions'}>
    <AdditionsTab recentRequests={recentRequestsInitial || []} />
</div>

<div hidden={activeTab !== 'search'}>
    <SearchPreferencesTab
        languagePreferences={languagePreferencesInitial || []}
        {availableLanguages}
        excludedTagPreferences={excludedTagPreferencesInitial || []}
        {availableTags}
        {ignoredGamesInitial}
        {ignoredGamesCountInitial}
    />
</div>
