<script lang="ts">
    import { untrack } from 'svelte';
    import BugReports from '@/components/dashboard/BugReports.svelte';
    import ConnectedAccounts from '@/components/dashboard/ConnectedAccounts.svelte';
    import AdditionsTab from '@/components/dashboard/AdditionsTab.svelte';
    import MyGamesTab from '@/components/dashboard/MyGamesTab.svelte';
    import SearchPreferencesTab from '@/components/dashboard/SearchPreferencesTab.svelte';
    import { authenticatedFetch } from '@/utils/csrf';
    import { toast } from '@/utils/toast';
    import { Link } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';
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
        user: User;
        connectedProviders: string[];
        socialAccounts: Record<string, SocialAccount>;
        itchioData: { username?: string };
        myGames: GameSummary[];
        myGamesClickStats: { [gameId: string]: GameClickStats } | null;
        notificationPreferences: NotificationPreferences;
        discordInfo?: DiscordInfo;
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

    // Check for bug_report query parameter from notification links
    const openBugReportId = $derived(() => {
        if (typeof window === 'undefined') return null;
        const params = new URLSearchParams(window.location.search);
        const id = params.get('bug_report');
        return id ? parseInt(id, 10) : null;
    });

    // --- Tab state ---
    type Tab = 'account' | 'my-games' | 'additions' | 'search';
    const hasItchio = $derived(!!itchioData?.username);
    const allTabs: { id: Tab; label: string; icon: string; condition?: boolean }[] = [
        { id: 'account', label: 'Account', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
        {
            id: 'my-games',
            label: 'My Games',
            icon: 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.657-.663 47.703 47.703 0 00-.31-4.82 48.1 48.1 0 00-5.202.24.64.64 0 01-.657-.643v0z',
        },
        { id: 'additions', label: 'VN Additions', icon: 'M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z' },
        {
            id: 'search',
            label: 'Search Preferences',
            icon: 'M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z',
        },
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

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{metaTags?.title || 'Dashboard'}</h1>
</div>

<!-- Tab Navigation -->
<div class="mb-6 border-b border-gray-200 dark:border-gray-700">
    <div class="-mb-px flex space-x-6" aria-label="Dashboard tabs" role="tablist">
        {#each tabs as tab (tab.id)}
            <Button
                type="button"
                variant="link"
                tone="info"
                onclick={() => setTab(tab.id)}
                class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition-colors {activeTab === tab.id
                    ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300'}"
                aria-selected={activeTab === tab.id}
                role="tab"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    ><path stroke-linecap="round" stroke-linejoin="round" d={tab.icon} /></svg
                >
                {tab.label}
            </Button>
        {/each}
    </div>
</div>

<!-- Bug Reports (shown above all tabs) -->
<BugReports initialReports={activeBugReports || []} openReportId={openBugReportId()} />

<!-- ==================== Account Tab ==================== -->
{#if activeTab === 'account'}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <!-- Profile Information -->
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
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                ><path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                /></svg
                            >
                            Export My Data
                        </Button>
                        <Link
                            href={route('users.reviews', user.id)}
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                ><path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                /></svg
                            >
                            My Reviews
                        </Link>
                    </div>
                </div>
            </Card>

            <!-- Notification Settings -->
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
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input
                                    class="peer sr-only"
                                    type="checkbox"
                                    aria-label="Enable browser notifications"
                                    checked={notifPrefs.browser_notifications_enabled}
                                    onchange={toggleBrowser}
                                    disabled={savingPrefs}
                                />
                                <div
                                    class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-indigo-600 peer-focus:ring-4 peer-focus:ring-indigo-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-indigo-800"
                                ></div>
                            </label>
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
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input
                                        class="peer sr-only"
                                        type="checkbox"
                                        aria-label="Enable Discord notifications"
                                        checked={notifPrefs.discord_notifications_enabled}
                                        onchange={toggleDiscord}
                                        disabled={savingPrefs}
                                    />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-indigo-600 peer-focus:ring-4 peer-focus:ring-indigo-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-indigo-800"
                                    ></div>
                                </label>
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
                            {#each [{ value: 'asap', label: 'As soon as possible', desc: 'Get notified immediately when games are updated', icon: 'icon-bell' }, { value: 'daily', label: 'Daily digest', desc: 'Get a summary of all updates once per day', icon: 'icon-paste' }, { value: 'weekly', label: 'Weekly digest', desc: 'Get a summary of all updates once per week', icon: 'icon-paste' }] as freq (freq.value)}
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
                                    <div class="flex w-full items-center justify-between">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">{freq.label}</div>
                                            <div class="mt-1 text-xs text-gray-700 dark:text-gray-300">{freq.desc}</div>
                                        </div>
                                        <i
                                            class="shrink-0 text-xl {freq.icon} {notifPrefs.notification_digest === freq.value
                                                ? 'text-indigo-600 dark:text-indigo-400'
                                                : 'text-gray-400 dark:text-gray-500'}"
                                            aria-hidden="true"
                                        ></i>
                                    </div>
                                </Button>
                            {/each}
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <!-- Connected Accounts -->
            <Card padding="lg">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Connected Accounts</h2>
                <ConnectedAccounts {user} {connectedProviders} {socialAccounts} />
            </Card>

            <!-- Danger Zone -->
            <Card padding="lg">
                <h2 class="mb-4 text-lg font-semibold text-red-600 dark:text-red-400">Danger Zone</h2>
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">Delete Account</h3>
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                        Once you delete your account, there is no going back. Please be certain.
                    </p>
                    <Button
                        type="button"
                        variant="solid"
                        tone="danger"
                        onclick={() => {
                            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                                // TODO: implement account deletion
                            }
                        }}
                        class="mt-3 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                    >
                        Delete Account
                    </Button>
                </div>
            </Card>
        </div>
    </div>
{/if}

<!-- Tab components stay mounted and are toggled with `hidden` so their local
     state (drafts, saved preferences, ignored games) survives tab switches. -->

<!-- ==================== My Games Tab ==================== -->
<div hidden={activeTab !== 'my-games'}>
    <MyGamesTab {hasItchio} {itchioData} {myGames} {myGamesClickStats} />
</div>

<!-- ==================== VN Additions Tab ==================== -->
<div hidden={activeTab !== 'additions'}>
    <AdditionsTab recentRequests={recentRequestsInitial || []} />
</div>

<!-- ==================== Search Preferences Tab ==================== -->
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
