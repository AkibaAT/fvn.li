<script lang="ts">
    import { untrack } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import BugReports from '@/components/dashboard/BugReports.svelte';
    import ConnectedAccounts from '@/components/dashboard/ConnectedAccounts.svelte';
    import { authenticatedFetch } from '@/utils/csrf';
    import { toast } from '@/utils/toast';
    import { Link } from '@inertiajs/svelte';
    import ItchioIcon from '@/components/icons/Itchio.svelte';
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
            await jsonPost(route('browser-api.dashboard.notifications.update'), { ...notifPrefs, notification_digest: value });
        } catch {
            notifPrefs = { ...notifPrefs, notification_digest: prev };
        } finally {
            savingPrefs = false;
        }
    };

    const handleExportData = () => {
        if (typeof window !== 'undefined') window.location.href = route('browser-api.user.export');
    };

    // --- VN Additions tab state ---
    let requestText = $state('');
    type SubmissionResult = {
        success_count: number;
        duplicate_count: number;
        invalid_count: number;
        already_exists_count?: number;
        errors: string[];
    };
    let requests = $state<AdditionRequest[]>(untrack(() => recentRequestsInitial || []));
    let _requestsLoading = $state(false);
    let _requestResults: SubmissionResult | null = $state(null);
    let _showRequestSuccess = $state(false);
    let requestSearch = $state('');
    let requestStatus = $state<'all' | 'pending' | 'processing' | 'approved' | 'rejected'>('all');
    let submittingRequest = $state(false);

    async function jsonGet<T>(url: string): Promise<T> {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`GET ${url} failed (${res.status})`);
        return res.json();
    }

    const loadRequests = async (opts?: { status?: string; search?: string }) => {
        _requestsLoading = true;
        try {
            const params = new SvelteURLSearchParams();
            params.set('status', (opts?.status ?? requestStatus) as string);
            if ((opts?.search ?? requestSearch).trim() !== '') params.set('search', (opts?.search ?? requestSearch).trim());
            const res = await jsonGet<{ success: boolean; requests: AdditionRequest[] }>(
                `${route('browser-api.dashboard.addition-requests.index')}?${params.toString()}`,
            );
            if (res.success) requests = res.requests;
        } catch {
            /* ignore */
        } finally {
            _requestsLoading = false;
        }
    };

    const submitRequest = async () => {
        const trimmed = requestText.trim();
        if (!trimmed) return;
        submittingRequest = true;
        try {
            const res = await authenticatedFetch(route('browser-api.dashboard.addition-requests.submit'), {
                method: 'POST',
                body: JSON.stringify({ urls: trimmed }),
            });
            const data = await res.json();
            if (res.ok && data?.success) {
                const result: SubmissionResult = data.result;
                _requestResults = result;
                _showRequestSuccess = result?.success_count > 0;
                if (result?.success_count > 0) {
                    toast.success(data.message || `Successfully submitted ${result.success_count} request(s)!`);
                    requestText = '';
                }
                await loadRequests({ status: requestStatus, search: requestSearch });
            } else {
                _requestResults = data?.result ?? { success_count: 0, duplicate_count: 0, invalid_count: 0, errors: [] };
                _showRequestSuccess = false;
            }
        } catch {
            toast.error('An error occurred while submitting requests.');
        } finally {
            submittingRequest = false;
        }
    };

    const cancelRequest = async (id: number) => {
        try {
            await jsonPost(route('browser-api.dashboard.addition-requests.cancel', { request: id }), {});
            await loadRequests({ status: requestStatus, search: requestSearch });
        } catch {
            /* noop */
        }
    };

    $effect(() => {
        void requestStatus;
        loadRequests({ status: requestStatus });
    });

    const filteredRequests = $derived(
        requests.filter((request) => {
            const search = requestSearch.trim().toLowerCase();

            if (!search) {
                return true;
            }

            return (
                request.game_url.toLowerCase().includes(search) ||
                request.status.toLowerCase().includes(search) ||
                request.status_label.toLowerCase().includes(search) ||
                (request.game?.name?.toLowerCase() ?? '').includes(search)
            );
        }),
    );

    const getStatusBadgeClasses = (color: string) => {
        switch (color) {
            case 'warning':
            case 'yellow':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
            case 'info':
            case 'blue':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
            case 'success':
            case 'green':
                return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
            case 'danger':
            case 'red':
                return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
        }
    };

    // --- Search Preferences tab state ---
    let selectedLanguages = $state<string[]>(untrack(() => languagePreferencesInitial || []));
    let savingLanguages = $state(false);
    let excludedTags = $state<number[]>(untrack(() => excludedTagPreferencesInitial || []));
    let savingExcludedTags = $state(false);
    let tagSearch = $state('');
    let ignoredGames = $state<IgnoredGame[]>(untrack(() => ignoredGamesInitial || []));
    let ignoredGamesCount = $state(untrack(() => ignoredGamesCountInitial || 0));

    const handleUnignoreGame = async (gameId: number) => {
        try {
            const response = await authenticatedFetch(route('user.ignored-games.destroy'), {
                method: 'DELETE',
                body: JSON.stringify({ game_id: gameId }),
            });
            const data = await response.json();
            if (data.success) {
                ignoredGames = ignoredGames.filter((g) => g.id !== gameId);
                ignoredGamesCount -= 1;
                toast.success('Game removed from ignore list');
            } else {
                toast.error(data.message || 'Failed to remove game from ignore list');
            }
        } catch (error) {
            console.error('Failed to unignore game:', error);
            toast.error('Failed to remove game from ignore list');
        }
    };

    const toggleLanguagePreference = (isoCode: string) => {
        selectedLanguages = selectedLanguages.includes(isoCode) ? selectedLanguages.filter((l) => l !== isoCode) : [...selectedLanguages, isoCode];
    };

    const saveLanguagePreferences = async () => {
        savingLanguages = true;
        try {
            const response = await authenticatedFetch(route('user.language-preferences.update'), {
                method: 'PUT',
                body: JSON.stringify({ preferred_languages: selectedLanguages }),
            });
            const data = await response.json();
            if (data.success) toast.success('Language preferences saved');
            else toast.error(data.message || 'Failed to save language preferences');
        } catch (error) {
            console.error('Failed to save language preferences:', error);
            toast.error('Failed to save language preferences');
        } finally {
            savingLanguages = false;
        }
    };

    const toggleExcludedTag = (tagId: number) => {
        excludedTags = excludedTags.includes(tagId) ? excludedTags.filter((id) => id !== tagId) : [...excludedTags, tagId];
    };

    const saveExcludedTags = async () => {
        savingExcludedTags = true;
        try {
            const response = await authenticatedFetch(route('user.excluded-tags.update'), {
                method: 'PUT',
                body: JSON.stringify({ excluded_tags: excludedTags }),
            });
            const data = await response.json();
            if (data.success) toast.success('Excluded tags saved');
            else toast.error(data.message || 'Failed to save excluded tags');
        } catch (error) {
            console.error('Failed to save excluded tags:', error);
            toast.error('Failed to save excluded tags');
        } finally {
            savingExcludedTags = false;
        }
    };

    const filteredTags = $derived(
        Object.entries(availableTags || {})
            .filter(([, label]) => !tagSearch || label.toLowerCase().includes(tagSearch.toLowerCase()))
            .sort(([, a], [, b]) => a.localeCompare(b, undefined, { sensitivity: 'base' })),
    );
</script>

<svelte:head>
    <title>{metaTags?.title || 'Dashboard'}</title>
</svelte:head>

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{metaTags?.title || 'Dashboard'}</h1>
</div>

<!-- Tab Navigation -->
<div class="mb-6 border-b border-gray-200 dark:border-gray-700">
    <nav class="-mb-px flex space-x-6" aria-label="Dashboard tabs">
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
    </nav>
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
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{freq.desc}</div>
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

<!-- ==================== My Games Tab ==================== -->
{#if activeTab === 'my-games'}
    <div class="space-y-6">
        {#if !hasItchio}
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                <div class="flex items-center space-x-3">
                    <ItchioIcon class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    <div class="flex-1">
                        <div class="font-medium text-yellow-800 dark:text-yellow-300">Connect your itch.io account to manage your games</div>
                        <div class="mt-1 text-xs text-yellow-700 dark:text-yellow-400">
                            After connecting, we'll show your owned games here for quick editing and analytics.
                        </div>
                    </div>
                    <a
                        href={route('auth.redirect', { provider: 'itchio', intended: `${route('dashboard')}#my-games` })}
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700">Connect itch.io</a
                    >
                </div>
            </div>
        {/if}

        {#if hasItchio}
            <div class="flex items-center gap-3">
                <ItchioIcon class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Connected: <span class="font-medium text-gray-900 dark:text-white">{itchioData.username}.itch.io</span> &middot; {myGames.length}
                    {myGames.length === 1 ? 'game' : 'games'}</span
                >
            </div>
        {/if}

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            {#each myGames as g (g.id)}
                {@const gameStats = myGamesClickStats?.[g.id.toString()]}
                {@const totalViews = gameStats?.page_views_unique || 0}
                {@const totalDownloads = gameStats?.custom_link_clicks_unique || 0}
                {@const itchioVisits = gameStats?.external_project_unique || 0}
                <Card variant="glass" padding="none" class="overflow-hidden shadow-none">
                    <Link href={route('games.show', g.slug)} class="block">
                        {#if g.thumb_url}
                            <img
                                src={g.thumb_url}
                                alt={g.name}
                                class="aspect-[4/3] w-full {g.platform === 'steam'
                                    ? 'object-contain'
                                    : 'object-cover'} transition-opacity hover:opacity-90"
                            />
                        {:else}
                            <div
                                class="flex h-36 w-full items-center justify-center bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600"
                            >
                                <div class="text-center">
                                    <svg class="mx-auto mb-1 h-8 w-8 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        ><path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        /></svg
                                    >
                                    <div class="text-sm font-medium">No Image</div>
                                </div>
                            </div>
                        {/if}
                    </Link>
                    <div class="space-y-2 p-4">
                        <div class="font-semibold text-gray-900 dark:text-white">{g.name}</div>
                        {#if g.has_additional_links}
                            <div class="text-xs text-green-600 dark:text-green-400">Has download links</div>
                        {:else}
                            <div class="text-xs text-gray-500 dark:text-gray-400">No download links</div>
                        {/if}

                        {#if gameStats && (totalViews > 0 || totalDownloads > 0 || itchioVisits > 0)}
                            <div class="space-y-1 rounded-lg bg-gray-50 p-2 dark:bg-gray-700/50">
                                <div class="text-xs font-medium text-gray-700 dark:text-gray-300">Last 30 days:</div>
                                <div class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-400">
                                    {#if totalViews > 0}
                                        <div class="flex items-center gap-1">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                ><path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                /><path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                /></svg
                                            >
                                            <span>{totalViews}</span>
                                        </div>
                                    {/if}
                                    {#if totalDownloads > 0}
                                        <div class="flex items-center gap-1">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                ><path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                /></svg
                                            >
                                            <span>{totalDownloads}</span>
                                        </div>
                                    {/if}
                                    {#if itchioVisits > 0}
                                        <div class="flex items-center gap-1">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                ><path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                                /></svg
                                            >
                                            <span>{itchioVisits}</span>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        {/if}

                        <div class="pt-2">
                            <Link
                                href={route('my-games.edit', { game: g.slug })}
                                class="inline-flex items-center space-x-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    ><path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                    /></svg
                                >
                                <span>Edit</span>
                            </Link>
                        </div>
                    </div>
                </Card>
            {/each}
        </div>

        {#if hasItchio && myGames.length === 0}
            <div class="text-center text-gray-600 dark:text-gray-400">No owned games were detected for your itch.io account.</div>
        {/if}
    </div>
{/if}

<!-- ==================== VN Additions Tab ==================== -->
{#if activeTab === 'additions'}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-2">
            <Card padding="lg">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Request VN Addition</h2>
                <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                    Submit URLs for visual novels you'd like to see added to the site. We support itch.io, Steam, and other platforms. You can submit
                    multiple URLs at once, one per line.
                </p>
                <div class="space-y-3">
                    <div>
                        <label for="game-urls" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Game URLs</label>
                        <textarea
                            id="game-urls"
                            bind:value={requestText}
                            rows={5}
                            placeholder="https://developer.itch.io/game-name&#10;https://store.steampowered.com/app/123456/game-name&#10;..."
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="solid"
                            tone="primary"
                            onclick={submitRequest}
                            disabled={submittingRequest || !requestText.trim()}
                            loading={submittingRequest}
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {submittingRequest ? 'Submitting...' : 'Submit Requests'}
                        </Button>
                        <Button
                            type="button"
                            variant="soft"
                            tone="neutral"
                            onclick={() => (requestText = '')}
                            class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500"
                        >
                            Clear
                        </Button>
                    </div>
                </div>
                <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-300">Guidelines:</h3>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-blue-700 dark:text-blue-400">
                        <li>Supported platforms: itch.io, Steam, and other game storefronts</li>
                        <li>Submit one URL per line for bulk requests</li>
                        <li>Maximum 50 URLs per submission</li>
                        <li>Games already on the site will be automatically filtered out</li>
                        <li>Duplicate requests are automatically handled</li>
                    </ul>
                </div>
            </Card>
        </div>

        <div class="space-y-6 lg:col-span-3">
            <Card padding="lg">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">My Requests</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{filteredRequests.length} request(s)</span>
                </div>
                <div class="mb-6 flex flex-col gap-4 sm:flex-row">
                    <div class="flex-1">
                        <input
                            placeholder="Search by URL or status..."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            type="text"
                            bind:value={requestSearch}
                        />
                    </div>
                    <div>
                        <select
                            bind:value={requestStatus}
                            class="rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="all">All Requests</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                {#if filteredRequests.length > 0}
                    <div class="space-y-2">
                        {#each filteredRequests as req (req.id)}
                            <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-700/50">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {req.game?.name || req.game_url}
                                    </div>
                                    {#if req.game}
                                        <a
                                            href={req.game_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-1 block truncate text-xs text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200"
                                        >
                                            {req.game_url}
                                        </a>
                                    {/if}
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {getStatusBadgeClasses(req.status_color)}"
                                        >{req.status_label}</span
                                    >
                                </div>
                                <div class="ml-3 flex items-center gap-3">
                                    {#if req.status === 'approved' && req.game}
                                        <Link
                                            href={route('games.show', req.game.slug)}
                                            class="text-xs text-blue-600 hover:underline dark:text-blue-400">View entry</Link
                                        >
                                    {/if}
                                    {#if req.status === 'pending' || req.status === 'processing'}
                                        <Button type="button" variant="link" tone="danger" onclick={() => cancelRequest(req.id)}>Cancel</Button>
                                    {/if}
                                </div>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <div class="py-8 text-center">
                        <div class="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                                ><path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                                /></svg
                            >
                        </div>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">No requests found</div>
                        <div class="text-xs text-gray-400 dark:text-gray-500">You haven't submitted any addition requests yet.</div>
                    </div>
                {/if}
            </Card>
        </div>
    </div>
{/if}

<!-- ==================== Search Preferences Tab ==================== -->
{#if activeTab === 'search'}
    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Customize how search results are filtered for you. These preferences apply across the site by default.
        </p>

        <!-- Language Preferences -->
        <Card padding="lg">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Language Preferences</h2>
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                Set your preferred languages to auto-filter the games list. When set, the games page will show only games available in these languages
                by default.
            </p>
            <div class="flex flex-wrap gap-2">
                {#each Object.entries(availableLanguages) as [iso, lang] (iso)}
                    <Button
                        type="button"
                        variant={selectedLanguages.includes(iso) ? 'solid' : 'soft'}
                        tone={selectedLanguages.includes(iso) ? 'primary' : 'neutral'}
                        onclick={() => toggleLanguagePreference(iso)}
                        class="rounded-full px-3 py-1 text-sm transition-colors {selectedLanguages.includes(iso)
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}"
                    >
                        <span class="fi fi-{lang.flag_code} mr-1 rounded-xs"></span>
                        {lang.ref_name}
                    </Button>
                {/each}
            </div>
            <div class="mt-4">
                <Button
                    type="button"
                    variant="solid"
                    tone="primary"
                    onclick={saveLanguagePreferences}
                    disabled={savingLanguages}
                    loading={savingLanguages}
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    {savingLanguages ? 'Saving...' : 'Save Preferences'}
                </Button>
            </div>
        </Card>

        <!-- Excluded Tags -->
        <Card padding="lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Excluded Tags</h2>
                {#if excludedTags.length > 0}
                    <span class="rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300"
                        >{excludedTags.length} excluded</span
                    >
                {/if}
            </div>
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                Select tags to exclude from game search results by default. Games with any of these tags will be hidden unless you explicitly include
                them.
            </p>
            <input
                type="text"
                bind:value={tagSearch}
                placeholder="Search tags..."
                class="mb-3 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            />
            <div class="flex max-h-64 flex-wrap gap-2 overflow-y-auto">
                {#each filteredTags as [tagId, label] (tagId)}
                    <Button
                        type="button"
                        variant={excludedTags.includes(Number(tagId)) ? 'solid' : 'soft'}
                        tone={excludedTags.includes(Number(tagId)) ? 'danger' : 'neutral'}
                        onclick={() => toggleExcludedTag(Number(tagId))}
                        class="rounded-full px-3 py-1 text-sm transition-colors {excludedTags.includes(Number(tagId))
                            ? 'bg-red-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}"
                    >
                        {label}
                    </Button>
                {/each}
            </div>
            <div class="mt-4 flex gap-2">
                <Button
                    type="button"
                    variant="solid"
                    tone="primary"
                    onclick={saveExcludedTags}
                    disabled={savingExcludedTags}
                    loading={savingExcludedTags}
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    {savingExcludedTags ? 'Saving...' : 'Save Preferences'}
                </Button>
                {#if excludedTags.length > 0}
                    <Button
                        type="button"
                        variant="soft"
                        tone="neutral"
                        onclick={() => {
                            excludedTags = [];
                            saveExcludedTags();
                        }}
                        class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500"
                    >
                        Clear All
                    </Button>
                {/if}
            </div>
        </Card>

        <!-- Ignored Games -->
        <Card padding="lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ignored Games</h2>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                    >{ignoredGamesCount} game{ignoredGamesCount !== 1 ? 's' : ''}</span
                >
            </div>
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                Games you've ignored won't appear in search results by default. You can manage your ignored games here.
            </p>
            {#if ignoredGames.length > 0}
                <div class="space-y-2">
                    {#each ignoredGames as game (game.id)}
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 p-2 dark:bg-gray-700/50">
                            <Link href={route('games.show', game.slug)} class="truncate text-sm text-blue-600 hover:underline dark:text-blue-400"
                                >{game.name}</Link
                            >
                            <Button type="button" variant="link" tone="danger" onclick={() => handleUnignoreGame(game.id)} class="ml-2">Remove</Button
                            >
                        </div>
                    {/each}
                </div>
            {:else}
                <div class="py-6 text-center">
                    <div class="mx-auto mb-2 h-10 w-10 text-gray-300 dark:text-gray-600">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                            ><path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                            /></svg
                        >
                    </div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">No ignored games</div>
                    <div class="text-xs text-gray-400 dark:text-gray-500">
                        You haven't ignored any games yet. Click the ignore button on any game card to hide it from search results.
                    </div>
                </div>
            {/if}
        </Card>
    </div>
{/if}
