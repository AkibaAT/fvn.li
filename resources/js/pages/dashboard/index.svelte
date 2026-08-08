<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import BugReports from '@/components/dashboard/BugReports.svelte';
    import ConnectedAccounts from '@/components/dashboard/ConnectedAccounts.svelte';
    import AdditionsTab from '@/components/dashboard/AdditionsTab.svelte';
    import MyGamesTab from '@/components/dashboard/MyGamesTab.svelte';
    import SearchPreferencesTab from '@/components/dashboard/SearchPreferencesTab.svelte';
    import NotificationSettings from '@/components/dashboard/NotificationSettings.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import type { NotificationPreferences } from '@/api/user-preferences';
    import { Link } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';
    import type { User, SocialAccount } from '@/types';
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
    interface DashboardProps {
        user: User;
        connectedProviders: string[];
        socialAccounts: Record<string, SocialAccount>;
        itchioData: { username?: string };
        myGames: GameSummary[];
        myGamesClickStats: { [gameId: string]: GameClickStats } | null;
        notificationPreferences: NotificationPreferences;
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

    const handleExportData = () => {
        if (typeof window !== 'undefined') window.location.href = route('browser-api.user.export');
    };
</script>

<SeoHead {metaTags} title="Dashboard" />

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
                        <Button type="button" variant="solid" tone="primary" onclick={handleExportData}>Export My Data</Button>
                        <Link
                            href={route('users.reviews', user.id)}
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            My Reviews
                        </Link>
                    </div>
                </div>
            </Card>

            <NotificationSettings
                initialPreferences={notificationPreferencesInitial}
                hasDiscord={connectedProviders.includes('discord')}
                {vapidPublicKey}
            />
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
