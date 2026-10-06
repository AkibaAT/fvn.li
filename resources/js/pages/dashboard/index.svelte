<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import BugReports from '@/components/dashboard/BugReports.svelte';
    import ConnectedAccounts from '@/components/dashboard/ConnectedAccounts.svelte';
    import AdditionsTab from '@/components/dashboard/AdditionsTab.svelte';
    import MyGamesTab from '@/components/dashboard/MyGamesTab.svelte';
    import SearchPreferencesTab from '@/components/dashboard/SearchPreferencesTab.svelte';
    import NotificationSettings from '@/components/dashboard/NotificationSettings.svelte';
    import ApiTokens from '@/components/dashboard/ApiTokens.svelte';
    import PrivateTagsManager from '@/components/dashboard/PrivateTagsManager.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import type { NotificationPreferences } from '@/api/user-preferences';
    import { deleteAccount } from '@/api/dashboard';
    import { toast } from '@/utils/toast';
    import { getErrorMessage } from '@/utils/async-action.svelte';
    import { useUrlTab } from '@/hooks/useUrlTab.svelte';
    import { Link } from '@inertiajs/svelte';
    import { Button, Card, TabBar } from '@/components/ui';
    import type { User, SocialAccount } from '@/types';
    import type { GameClickStatsMap, GameSummary } from '@/types/my-games';
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
        myGamesClickStats: GameClickStatsMap | null;
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
        notificationPreferences,
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
    type Tab = 'account' | 'my-games' | 'additions' | 'search' | 'private-tags';
    const hasItchio = $derived(!!itchioData?.username);
    const allTabs: { id: Tab; label: string; condition?: boolean }[] = [
        { id: 'account', label: 'Account' },
        { id: 'my-games', label: 'My Games' },
        { id: 'additions', label: 'VN Additions' },
        { id: 'search', label: 'Search Preferences' },
        { id: 'private-tags', label: 'Private Tags' },
    ];
    const tabs = $derived(allTabs.filter((t) => t.condition !== false));
    const urlTab = useUrlTab<Tab>(
        allTabs.map((t) => t.id),
        'account',
        { readHash: true },
    );

    const handleExportData = () => {
        if (typeof window !== 'undefined') window.location.href = route('browser-api.user.export');
    };

    let deletingAccount = $state(false);

    async function handleDeleteAccount() {
        if (deletingAccount || !confirm('Are you sure you want to delete your account? This action cannot be undone.')) return;
        deletingAccount = true;
        try {
            await deleteAccount();
            window.location.assign(route('home'));
        } catch (error) {
            deletingAccount = false;
            toast.error(getErrorMessage(error, 'Failed to delete account.'));
        }
    }
</script>

<SeoHead {metaTags} title="Dashboard" />

<PageHeader title={metaTags?.title || 'Dashboard'} class="mb-6" />

<TabBar {tabs} active={urlTab.activeTab} onSelect={(tab) => urlTab.setTab(tab as Tab)} ariaLabel="Dashboard tabs" />

<BugReports initialReports={activeBugReports || []} openReportId={openBugReportId()} />

{#if urlTab.activeTab === 'account'}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <Card variant="flat" padding="lg">
                <h2 class="mb-4 text-title font-semibold text-fg">Profile Information</h2>
                <div class="flex items-center gap-4">
                    {#if user.avatar}
                        <img src={user.avatar} alt={user.name} class="h-16 w-16 rounded-full" />
                    {:else}
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-surface-alt text-xl font-bold text-fg-muted">
                            {user.name?.charAt(0)?.toUpperCase() || '?'}
                        </div>
                    {/if}
                    <div>
                        <div class="text-lg font-semibold text-fg">{user.name}</div>
                        {#if user.email}
                            <div class="text-sm text-fg-faint">{user.email}</div>
                        {/if}
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex gap-3">
                        <Button type="button" variant="solid" tone="primary" onclick={handleExportData}>Export My Data</Button>
                        <Link
                            href={route('users.reviews', user.id)}
                            class="inline-flex items-center rounded-md border border-border-strong bg-surface px-4 py-2 text-sm font-medium text-fg transition-colors hover:border-fg"
                        >
                            My Reviews
                        </Link>
                    </div>
                </div>
            </Card>

            <NotificationSettings preferences={notificationPreferences} hasDiscord={connectedProviders.includes('discord')} {vapidPublicKey} />
            <ApiTokens />
        </div>

        <div class="space-y-6 lg:col-span-2">
            <Card variant="flat" padding="lg">
                <h2 class="mb-4 text-title font-semibold text-fg">Connected Accounts</h2>
                <ConnectedAccounts {user} {connectedProviders} {socialAccounts} />
            </Card>

            <Card variant="flat" padding="lg">
                <h2 class="mb-4 text-title font-semibold text-red-600 dark:text-red-400">Delete Account</h2>
                <p class="mb-4 text-sm text-fg-muted">
                    Permanently delete your account, saved lists, reading progress, and reviews posted on FVN.li. This cannot be undone. You can
                    export your data first.
                </p>
                <Button type="button" tone="danger" loading={deletingAccount} onclick={handleDeleteAccount}>
                    {deletingAccount ? 'Deleting Account…' : 'Delete Account'}
                </Button>
            </Card>
        </div>
    </div>
{/if}

<!-- Tab components stay mounted and are toggled with `hidden` so their local
     state (drafts, saved preferences, ignored games) survives tab switches. -->

<div hidden={urlTab.activeTab !== 'my-games'}>
    <MyGamesTab {hasItchio} {itchioData} {myGames} {myGamesClickStats} />
</div>

<div hidden={urlTab.activeTab !== 'additions'}>
    <AdditionsTab recentRequests={recentRequestsInitial || []} />
</div>

<div hidden={urlTab.activeTab !== 'search'}>
    <SearchPreferencesTab
        languagePreferences={languagePreferencesInitial || []}
        {availableLanguages}
        excludedTagPreferences={excludedTagPreferencesInitial || []}
        {availableTags}
        {ignoredGamesInitial}
        {ignoredGamesCountInitial}
    />
</div>

<div hidden={urlTab.activeTab !== 'private-tags'}>
    <PrivateTagsManager />
</div>
