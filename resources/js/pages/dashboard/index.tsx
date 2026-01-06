import {ConnectedAccounts} from '@/components/dashboard/connected-accounts';
import type {SocialAccount as TypedSocialAccount, User as TypedUser,} from '@/types';
import {authenticatedFetch} from '@/utils/csrf';
import {toast} from '@/utils/toast';
import {Head, Link} from '@inertiajs/react';
import {useEffect, useRef, useState} from 'react';
// Import the ItchioIcon component
import ItchioIcon from '@/components/icons/itchio';

interface User {
    id: number;
    name: string;
    email?: string;
    avatar?: string;
    created_at: string;
    updated_at?: string;
}

interface SocialAccount {
    display_name?: string;
    avatar?: string;
}

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
    game?: {
        id: number;
        name: string;
        slug: string;
    };
    reviewer?: {
        name: string;
    };
}

interface IgnoredGame {
    id: number;
    name: string;
    slug: string;
    thumb_url?: string;
    optimized_thumbnails?: {
        default?: { path: string; width: number; height: number };
    };
    platform?: 'itch_io' | 'steam' | 'other';
}

interface BugReport {
    id: number;
    page_title?: string;
    description: string;
    status: string;
    status_label: string;
    status_color: string;
    unread_count: number;
    created_at: string;
}

interface BugReportComment {
    id: number;
    message: string;
    is_from_admin: boolean;
    user: {
        id: number;
        name: string;
    };
    created_at: string;
}

interface BugReportDetail {
    id: number;
    page_url: string;
    page_title?: string;
    description: string;
    status: string;
    status_label: string;
    status_color: string;
    is_closed: boolean;
    admin_notes?: string;
    created_at: string;
    resolved_at?: string;
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
    itchioData: {
        username?: string;
        ownedGamesCount: number;
        gamesWithLinks: number;
    };
    notificationPreferences: NotificationPreferences;
    discordInfo?: DiscordInfo;
    recentRequests: AdditionRequest[];
    ignoredGames: IgnoredGame[];
    ignoredGamesCount: number;
    activeBugReports?: BugReport[];
    totalUnreadBugReportReplies?: number;
    metaTags?: {
        title?: string;
    };
    vapidPublicKey?: string;
}

// removed unused getProviderIcon

export default function Dashboard({
                                      user,
                                      connectedProviders,
                                      socialAccounts,
                                      itchioData: itchioDataInitial,
                                      notificationPreferences: notificationPreferencesInitial,
                                      discordInfo,
                                      recentRequests: recentRequestsInitial,
                                      ignoredGames: ignoredGamesInitial,
                                      ignoredGamesCount: ignoredGamesCountInitial,
                                      activeBugReports: activeBugReportsInitial,
                                      totalUnreadBugReportReplies: totalUnreadInitial,
                                      metaTags,
                                  }: DashboardProps) {
    // Local interactive state hydrated from server props
    const [notifPrefs, setNotifPrefs] = useState(
        notificationPreferencesInitial,
    );
    const [savingPrefs, setSavingPrefs] = useState(false);

    const [itchioData] = useState(itchioDataInitial);
    const [ignoredGames, setIgnoredGames] = useState<IgnoredGame[]>(ignoredGamesInitial || []);
    const [ignoredGamesCount, setIgnoredGamesCount] = useState(ignoredGamesCountInitial || 0);

    // Bug report state
    const [bugReports, setBugReports] = useState<BugReport[]>(activeBugReportsInitial || []);
    const [selectedBugReport, setSelectedBugReport] = useState<BugReportDetail | null>(null);
    const [bugReportComments, setBugReportComments] = useState<BugReportComment[]>([]);
    const bugReportDialogRef = useRef<HTMLDialogElement>(null);
    const [loadingBugReport, setLoadingBugReport] = useState(false);
    const [newComment, setNewComment] = useState('');
    const [submittingComment, setSubmittingComment] = useState(false);

    const [requestText, setRequestText] = useState('');
    type SubmissionResult = {
        success_count: number;
        duplicate_count: number;
        invalid_count: number;
        already_exists_count?: number;
        errors: string[];
    };
    const [showRequestSuccess, setShowRequestSuccess] = useState(false);
    const [requestResults, setRequestResults] =
        useState<SubmissionResult | null>(null);
    const [requests, setRequests] = useState<AdditionRequest[]>(
        recentRequestsInitial || [],
    );
    const [requestsLoading, setRequestsLoading] = useState(false);
    const [requestSearch, setRequestSearch] = useState('');
    const [requestStatus, setRequestStatus] = useState<
        'all' | 'pending' | 'processing' | 'completed' | 'rejected'
    >('all');
    const [submittingRequest, setSubmittingRequest] = useState(false);
    const [notificationPermission, setNotificationPermission] =
        useState<NotificationPermission>(
            typeof window !== 'undefined' && 'Notification' in window
                ? Notification.permission
                : 'default',
        );
    const [vapidKey] = useState<string | undefined>(undefined);

    // Helpers for API calls
    async function jsonGet<T>(url: string): Promise<T> {
        const res = await fetch(url, {credentials: 'same-origin'});
        if (!res.ok) throw new Error(`GET ${url} failed (${res.status})`);
        return res.json();
    }

    async function jsonPost<T>(url: string, payload: unknown): Promise<T> {
        const res = await authenticatedFetch(url, {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok || data?.success === false) {
            // Show user-friendly feedback when available
            if (data?.errors && typeof data.errors === 'object') {
                const messages = Object.values<string | string[]>(
                    data.errors,
                ).flat();
                messages.forEach((m) => toast.error(String(m)));
            }
            if (data?.message) {
                toast.error(String(data.message));
            }
            const message = data?.message || 'Request failed';
            const errors = data?.errors ? JSON.stringify(data.errors) : '';
            throw new Error(`${message}${errors ? `: ${errors}` : ''}`);
        }
        return data;
    }

    // Hydrate from SSR props to avoid extra round trips
    useEffect(() => {
        // Already provided: notificationPreferences and itchioData; nothing to fetch here
    }, [metaTags]);

    // Keep local permission state in sync
    useEffect(() => {
        if (typeof window === 'undefined' || !('Notification' in window))
            return;
        setNotificationPermission(Notification.permission);
    }, [metaTags]);

    // Handle bug_report query parameter from notification links
    useEffect(() => {
        if (typeof window === 'undefined') return;
        const params = new URLSearchParams(window.location.search);
        const bugReportId = params.get('bug_report');
        if (bugReportId) {
            // Open the bug report modal
            const reportId = parseInt(bugReportId, 10);
            if (!isNaN(reportId)) {
                // Delay slightly to ensure component is fully mounted
                setTimeout(() => {
                    setLoadingBugReport(true);
                    bugReportDialogRef.current?.showModal();
                    fetch(route('react-api.bug-reports.show', { bugReport: reportId }), {
                        credentials: 'same-origin',
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                setSelectedBugReport(data.report);
                                setBugReportComments(data.comments);
                                setBugReports(prev => prev.map(r =>
                                    r.id === reportId ? { ...r, unread_count: 0 } : r
                                ));
                            } else {
                                toast.error(data.message || 'Failed to load bug report');
                                bugReportDialogRef.current?.close();
                            }
                        })
                        .catch(() => {
                            toast.error('Failed to load bug report');
                            bugReportDialogRef.current?.close();
                        })
                        .finally(() => setLoadingBugReport(false));
                }, 100);
                // Clean up URL
                const newUrl = window.location.pathname;
                window.history.replaceState({}, '', newUrl);
            }
        }
    }, []);

    // Helper to convert VAPID base64 url key
    function base64UrlToUint8Array(base64String: string) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        const rawData = atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i)
            outputArray[i] = rawData.charCodeAt(i);
        return outputArray;
    }

    // Ensure a server-side push subscription exists if permission is granted
    useEffect(() => {
        (async () => {
            try {
                if (
                    !('Notification' in window) ||
                    !('serviceWorker' in navigator)
                )
                    return;
                if (notificationPermission !== 'granted') return;
                // Wait for service worker
                const reg = await navigator.serviceWorker.ready;
                let sub = await reg.pushManager.getSubscription();
                // If no local subscription, try to subscribe if VAPID key is available
                const win =
                    typeof window !== 'undefined'
                        ? (window as unknown as {
                            __VAPID_PUBLIC_KEY?: string;
                        })
                        : undefined;
                const effectiveVapid =
                    vapidKey || win?.__VAPID_PUBLIC_KEY || undefined;
                if (!sub && effectiveVapid) {
                    try {
                        sub = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey:
                                base64UrlToUint8Array(effectiveVapid),
                        });
                    } catch {
                        // silently ignore; user may have blocked
                    }
                }
                if (!sub) return;
                // Verify on server
                const verifyRes = await authenticatedFetch(
                    route('react-api.push-subscriptions.verify'),
                    {
                        method: 'POST',
                        body: JSON.stringify({endpoint: sub.endpoint}),
                    },
                );
                const verify: { exists?: boolean } = await verifyRes
                    .json()
                    .catch(() => ({exists: false}));
                if (!verifyRes.ok || verify?.exists !== true) {
                    // Re-store subscription on server
                    await fetch(route('react-api.push-subscriptions.store'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN':
                                (
                                    document.querySelector(
                                        'meta[name="csrf-token"]',
                                    ) as HTMLMetaElement
                                )?.content || '',
                        },
                        body: JSON.stringify({subscription: sub.toJSON()}),
                    }).catch(() => {
                    });
                }
            } catch {
                // noop
            }
        })();
    }, [notificationPermission, vapidKey]);

    // Requests list loader
    const loadRequests = async (opts?: {
        status?: string;
        search?: string;
    }) => {
        setRequestsLoading(true);
        try {
            const params = new URLSearchParams();
            params.set('status', (opts?.status ?? requestStatus) as string);
            if ((opts?.search ?? requestSearch).trim() !== '') {
                params.set('search', (opts?.search ?? requestSearch).trim());
            }
            const res = await jsonGet<{
                success: boolean;
                requests: AdditionRequest[];
            }>(
                `${route('react-api.dashboard.addition-requests.index')}?${params.toString()}`,
            );
            if (res.success) {
                setRequests(res.requests);
            }
        } catch {
            // Ignore errors; UI remains with previous state
        } finally {
            setRequestsLoading(false);
        }
    };

    // Note: We skip initial load; use SSR recentRequests to avoid extra request.

    // Toggle handlers for notification prefs
    const toggleBrowser = async () => {
        const next = {
            ...notifPrefs,
            browser_notifications_enabled:
                !notifPrefs.browser_notifications_enabled,
        };
        setNotifPrefs(next);
        setSavingPrefs(true);
        try {
            await jsonPost(
                route('react-api.dashboard.notifications.update'),
                next,
            );
        } catch {
            // Revert on error
            setNotifPrefs((prev) => ({
                ...prev,
                browser_notifications_enabled:
                    !next.browser_notifications_enabled,
            }));
        } finally {
            setSavingPrefs(false);
        }
    };

    const toggleDiscord = async () => {
        const next = {
            ...notifPrefs,
            discord_notifications_enabled:
                !notifPrefs.discord_notifications_enabled,
        };
        setNotifPrefs(next);
        setSavingPrefs(true);
        try {
            await jsonPost(
                route('react-api.dashboard.notifications.update'),
                next,
            );
        } catch {
            setNotifPrefs((prev) => ({
                ...prev,
                discord_notifications_enabled:
                    !next.discord_notifications_enabled,
            }));
        } finally {
            setSavingPrefs(false);
        }
    };

    const updateDigest = async (
        value: NotificationPreferences['notification_digest'],
    ) => {
        if (value === notifPrefs.notification_digest) return;
        const prev = notifPrefs.notification_digest;
        setNotifPrefs((p) => ({...p, notification_digest: value}));
        setSavingPrefs(true);
        try {
            await jsonPost(route('react-api.dashboard.notifications.update'), {
                ...notifPrefs,
                notification_digest: value,
            });
        } catch {
            setNotifPrefs((p) => ({...p, notification_digest: prev}));
        } finally {
            setSavingPrefs(false);
        }
    };

    // Addition request submit and cancel
    const submitRequest = async () => {
        const trimmed = requestText.trim();
        if (!trimmed) return;
        setSubmittingRequest(true);
        try {
            const res = await authenticatedFetch(
                route('react-api.dashboard.addition-requests.submit'),
                {
                    method: 'POST',
                    body: JSON.stringify({urls: trimmed}),
                },
            );
            const data = await res.json();

            if (res.ok && data?.success) {
                const result: SubmissionResult = data.result;
                setRequestResults(result);
                setShowRequestSuccess(result?.success_count > 0);
                if (result?.success_count > 0) {
                    const successMsg =
                        typeof data.message === 'string' &&
                        data.message.trim() !== ''
                            ? data.message
                            : `Successfully submitted ${result.success_count} request(s)!`;
                    toast.success(successMsg);
                    setRequestText('');
                }
                await loadRequests({
                    status: requestStatus,
                    search: requestSearch,
                });
            } else {
                const result: SubmissionResult | undefined = data?.result;
                setRequestResults(
                    result ?? {
                        success_count: 0,
                        duplicate_count: 0,
                        invalid_count: 0,
                        errors: [],
                    },
                );
                setShowRequestSuccess(false);
                // Toast field/general errors
                if (Array.isArray(data?.errors)) {
                    data.errors.forEach((m: string) => toast.error(String(m)));
                } else if (data?.errors && typeof data.errors === 'object') {
                    Object.values<string | string[]>(data.errors)
                        .flat()
                        .forEach((m) => toast.error(String(m)));
                }
                if (data?.message) toast.error(String(data.message));
            }
        } catch {
            toast.error('An error occurred while submitting requests.');
        } finally {
            setSubmittingRequest(false);
        }
    };

    const cancelRequest = async (id: number) => {
        try {
            await jsonPost(
                route('react-api.dashboard.addition-requests.cancel', {
                    request: id,
                }),
                {},
            );
            await loadRequests({
                status: requestStatus,
                search: requestSearch,
            });
        } catch {
            // noop
        }
    };

    const handleExportData = () => {
        if (typeof window === 'undefined') return;
        window.location.href = route('react-api.user.export');
    };

    const handleUnignoreGame = async (gameId: number) => {
        try {
            const response = await authenticatedFetch(route('user.ignored-games.destroy'), {
                method: 'DELETE',
                body: JSON.stringify({ game_id: gameId }),
            });

            const data = await response.json();

            if (data.success) {
                // Remove from local state
                setIgnoredGames(prev => prev.filter(g => g.id !== gameId));
                setIgnoredGamesCount(prev => prev - 1);
                toast.success('Game removed from ignore list');
            } else {
                toast.error(data.message || 'Failed to remove game from ignore list');
            }
        } catch (error) {
            console.error('Failed to unignore game:', error);
            toast.error('Failed to remove game from ignore list');
        }
    };

    // Bug report functions
    const openBugReport = async (reportId: number) => {
        setLoadingBugReport(true);
        bugReportDialogRef.current?.showModal();
        try {
            const response = await fetch(route('react-api.bug-reports.show', { bugReport: reportId }), {
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (data.success) {
                setSelectedBugReport(data.report);
                setBugReportComments(data.comments);
                // Update unread count in the list
                setBugReports(prev => prev.map(r =>
                    r.id === reportId ? { ...r, unread_count: 0 } : r
                ));
            } else {
                toast.error(data.message || 'Failed to load bug report');
                bugReportDialogRef.current?.close();
            }
        } catch (error) {
            console.error('Failed to load bug report:', error);
            toast.error('Failed to load bug report');
            bugReportDialogRef.current?.close();
        } finally {
            setLoadingBugReport(false);
        }
    };

    const closeBugReportModal = () => {
        bugReportDialogRef.current?.close();
        setSelectedBugReport(null);
        setBugReportComments([]);
        setNewComment('');
    };

    const submitBugReportComment = async () => {
        if (!selectedBugReport || !newComment.trim()) return;

        setSubmittingComment(true);
        try {
            const response = await authenticatedFetch(
                route('react-api.bug-reports.comments.store', { bugReport: selectedBugReport.id }),
                {
                    method: 'POST',
                    body: JSON.stringify({ message: newComment.trim() }),
                }
            );
            const data = await response.json();
            if (data.success) {
                setBugReportComments(prev => [...prev, data.comment]);
                setNewComment('');
                toast.success('Comment added');
            } else {
                toast.error(data.message || 'Failed to add comment');
            }
        } catch (error) {
            console.error('Failed to add comment:', error);
            toast.error('Failed to add comment');
        } finally {
            setSubmittingComment(false);
        }
    };

    const [closingTicket, setClosingTicket] = useState(false);

    const closeTicket = async () => {
        if (!selectedBugReport) return;

        setClosingTicket(true);
        try {
            const response = await authenticatedFetch(
                route('react-api.bug-reports.close', { bugReport: selectedBugReport.id }),
                { method: 'POST' }
            );
            const data = await response.json();
            if (data.success) {
                // Remove the bug report from the list (closed reports don't show on dashboard)
                setBugReports(prev => prev.filter(r => r.id !== selectedBugReport.id));
                // Close the modal
                closeBugReportModal();
                toast.success('Ticket closed');
            } else {
                toast.error(data.message || 'Failed to close ticket');
            }
        } catch (error) {
            console.error('Failed to close ticket:', error);
            toast.error('Failed to close ticket');
        } finally {
            setClosingTicket(false);
        }
    };

    const getStatusBadgeClasses = (color: string) => {
        switch (color) {
            case 'warning':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
            case 'info':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
            case 'success':
                return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
            case 'danger':
                return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
        }
    };

    return (<>
            <Head title={metaTags?.title || 'Dashboard'}/>

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                    {metaTags?.title || 'User Dashboard'}
                </h1>
            </div>

            {/* Flash Messages */}
            {/* Note: In a real implementation, these would be populated from session data or state */}
            <div className="mb-4">
                {/* Success message example - would be conditionally rendered */}
                {/* <div className="p-4 bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300">
                    Your changes have been saved successfully.
                </div> */}

                {/* Error message example - would be conditionally rendered */}
                {/* <div className="p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300">
                    There was an error saving your changes. Please try again.
                </div> */}
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* Left Column - User Settings */}
                <div className="space-y-6 lg:col-span-3">
                    {/* Bug Reports Section - only shown if user has active reports */}
                    {bugReports.length > 0 && (
                        <div className="rounded-lg border-2 border-amber-300 bg-amber-50 shadow-sm dark:border-amber-700 dark:bg-amber-900/20">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h2 className="flex items-center gap-2 text-lg font-semibold text-amber-800 dark:text-amber-300">
                                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        Your Bug Reports
                                        {bugReports.reduce((sum, r) => sum + r.unread_count, 0) > 0 && (
                                            <span className="ml-2 inline-flex items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-medium text-white">
                                                {bugReports.reduce((sum, r) => sum + r.unread_count, 0)} new
                                            </span>
                                        )}
                                    </h2>
                                    <span className="text-sm text-amber-600 dark:text-amber-400">
                                        {bugReports.length} active
                                    </span>
                                </div>

                                <div className="space-y-3">
                                    {bugReports.map((report) => (
                                        <div
                                            key={report.id}
                                            className="cursor-pointer rounded-lg border border-amber-200 bg-white p-4 transition-colors hover:border-amber-400 dark:border-amber-800 dark:bg-gray-800 dark:hover:border-amber-600"
                                            onClick={() => openBugReport(report.id)}
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="min-w-0 flex-1">
                                                    <div className="mb-2 flex items-center gap-2">
                                                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusBadgeClasses(report.status_color)}`}>
                                                            {report.status_label}
                                                        </span>
                                                        {report.unread_count > 0 && (
                                                            <span className="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                                {report.unread_count} new {report.unread_count === 1 ? 'reply' : 'replies'}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="line-clamp-2 text-sm text-gray-700 dark:text-gray-300">
                                                        {report.description}
                                                    </p>
                                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                        Reported {new Date(report.created_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                                <svg className="ml-2 h-5 w-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                </svg>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Profile Section */}
                    <div className="rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                Profile Information
                            </h2>
                            <div className="flex items-center gap-4">
                                {user.avatar ? (
                                    <img
                                        src={user.avatar}
                                        alt={user.name}
                                        className="h-16 w-16 rounded-full"
                                    />
                                ) : (
                                    <div
                                        className="flex h-16 w-16 items-center justify-center rounded-full bg-blue-500 text-2xl font-bold text-white">
                                        {user.name.charAt(0)}
                                    </div>
                                )}
                                <div>
                                    <div className="text-xl font-medium text-gray-900 dark:text-white">
                                        {user.name}
                                    </div>
                                    {user.email && (
                                        <div className="text-gray-500 dark:text-gray-400">
                                            {user.email}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="mt-4">
                                <button
                                    onClick={handleExportData}
                                    className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                        />
                                    </svg>
                                    <span>Export My Data</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Notification Settings Section */}
                    <div className="rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                Notification Settings
                            </h2>

                            <div className="space-y-6">
                                <div className="flex items-center gap-4">
                                    <div className="flex-grow">
                                        <div className="font-medium text-gray-700 dark:text-gray-300">
                                            Browser Push Notifications
                                        </div>
                                        <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Receive notifications directly in
                                            your browser when games are updated.
                                        </div>
                                    </div>
                                    <div className="flex items-center">
                                        <button
                                            className={`mr-3 rounded-md px-3 py-2 text-sm font-medium text-white ${notificationPermission === 'granted' ? 'cursor-default bg-green-600' : 'bg-indigo-600 hover:bg-indigo-700'}`}
                                            onClick={async () => {
                                                try {
                                                    if (
                                                        !(
                                                            'Notification' in
                                                            window
                                                        )
                                                    ) {
                                                        toast.error(
                                                            'Browser notifications are not supported.',
                                                        );
                                                        return;
                                                    }
                                                    if (
                                                        notificationPermission ===
                                                        'granted'
                                                    ) {
                                                        toast.info(
                                                            'Permission already granted.',
                                                        );
                                                        return;
                                                    }
                                                    const permission =
                                                        await Notification.requestPermission();
                                                    setNotificationPermission(
                                                        permission,
                                                    );
                                                    if (
                                                        permission !== 'granted'
                                                    ) {
                                                        toast.error(
                                                            'Permission denied.',
                                                        );
                                                        return;
                                                    }
                                                    // Enable in preferences once permission granted
                                                    const next = {
                                                        ...notifPrefs,
                                                        browser_notifications_enabled: true,
                                                    };
                                                    setNotifPrefs(next);
                                                    await jsonPost(
                                                        route(
                                                            'react-api.dashboard.notifications.update',
                                                        ),
                                                        next,
                                                    );
                                                    toast.success(
                                                        'Browser notifications enabled.',
                                                    );
                                                } catch {
                                                    toast.error(
                                                        'Failed to enable browser notifications.',
                                                    );
                                                }
                                            }}
                                            disabled={
                                                notificationPermission ===
                                                'granted'
                                            }
                                            title={
                                                notificationPermission ===
                                                'granted'
                                                    ? 'Permission already granted'
                                                    : undefined
                                            }
                                        >
                                            {notificationPermission ===
                                            'granted'
                                                ? 'Permission Granted'
                                                : 'Request Permission'}
                                        </button>
                                        <label className="relative inline-flex cursor-pointer items-center">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    notifPrefs.browser_notifications_enabled
                                                }
                                                onChange={toggleBrowser}
                                                disabled={savingPrefs}
                                                className="peer sr-only"
                                            />
                                            <div
                                                className="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-indigo-600 peer-focus:ring-4 peer-focus:ring-indigo-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:translate-x-[-100%] dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-indigo-800"></div>
                                        </label>
                                    </div>
                                </div>

                                <div className="flex items-center gap-4">
                                    <div className="flex-grow">
                                        <div className="font-medium text-gray-700 dark:text-gray-300">
                                            Discord Notifications
                                        </div>
                                        <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Receive notifications via Discord
                                            when games are updated.
                                        </div>
                                    </div>
                                    <div>
                                        <label className="relative inline-flex cursor-pointer items-center">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    notifPrefs.discord_notifications_enabled
                                                }
                                                onChange={toggleDiscord}
                                                disabled={savingPrefs}
                                                className="peer sr-only"
                                            />
                                            <div
                                                className="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-indigo-600 peer-focus:ring-4 peer-focus:ring-indigo-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white rtl:peer-checked:after:translate-x-[-100%] dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-indigo-800"></div>
                                        </label>
                                    </div>
                                </div>

                                {/* Discord Bot Installation Status */}
                                {discordInfo && !discordInfo.hasAccount ? (
                                    <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                                        <p className="text-sm text-amber-700 dark:text-amber-400">
                                            Please connect your Discord account first to enable Discord notifications.
                                        </p>
                                    </div>
                                ) : discordInfo?.lastNotification?.status === 'sent' ? (
                                    <div className="mt-3 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-900/20">
                                        <div className="flex items-center gap-2">
                                            <svg className="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <p className="text-sm text-green-700 dark:text-green-400">
                                                Discord notifications are working! Last notification sent successfully
                                                {discordInfo.lastNotification.processedAt && (
                                                    <span className="text-green-600 dark:text-green-500">
                                                        {' '}on {new Date(discordInfo.lastNotification.processedAt).toLocaleDateString()}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                ) : discordInfo?.lastNotification?.status === 'failed' ? (
                                    <div className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                        <div className="flex items-start gap-2">
                                            <svg className="mt-0.5 h-4 w-4 flex-shrink-0 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>
                                                <p className="text-sm font-medium text-red-700 dark:text-red-400">
                                                    Last Discord notification failed
                                                    {(discordInfo.lastNotification.processedAt || discordInfo.lastNotification.createdAt) && (
                                                        <span className="ml-1 font-normal text-red-600 dark:text-red-500">
                                                            ({new Date(discordInfo.lastNotification.processedAt || discordInfo.lastNotification.createdAt).toLocaleDateString()})
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="mt-1 text-sm text-red-600 dark:text-red-500">
                                                    {discordInfo.lastNotification.error?.toLowerCase().includes('unknown user')
                                                        ? 'Your Discord account could not be found. Please disconnect and re-link your Discord account.'
                                                        : discordInfo.lastNotification.error?.toLowerCase().includes('cannot send messages')
                                                            ? 'Unable to send DMs to your account. Please add the fvn.li bot to enable direct messages.'
                                                            : 'Unable to deliver notification. Please ensure the bot is added to your Discord account.'}
                                                </p>
                                                {discordInfo.botInstallUrl && !discordInfo.lastNotification.error?.toLowerCase().includes('unknown user') && (
                                                    <div className="mt-2">
                                                        <a
                                                            href={discordInfo.botInstallUrl}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-2 rounded-md bg-[#5865F2] px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-[#4752C4]"
                                                        >
                                                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                                                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                                                            </svg>
                                                            Add fvn.li Bot to Discord
                                                        </a>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ) : discordInfo?.lastNotification?.status === 'pending' || discordInfo?.lastNotification?.status === 'processing' ? (
                                    <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                                        <div className="flex items-center gap-2">
                                            <svg className="h-4 w-4 animate-spin text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <p className="text-sm text-amber-700 dark:text-amber-400">
                                                A Discord notification is {discordInfo.lastNotification.status === 'pending' ? 'pending' : 'being processed'}...
                                            </p>
                                        </div>
                                    </div>
                                ) : discordInfo?.botInstallUrl ? (
                                    <div className="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                                        <p className="mb-2 text-sm text-blue-700 dark:text-blue-400">
                                            To receive Discord DM notifications, you need to add the fvn.li bot to your Discord account.
                                            This allows the bot to send you direct messages about game updates.
                                        </p>
                                        <a
                                            href={discordInfo.botInstallUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-2 rounded-md bg-[#5865F2] px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-[#4752C4]"
                                        >
                                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                                            </svg>
                                            Add fvn.li Bot to Discord
                                        </a>
                                    </div>
                                ) : null}

                                <div className="mt-6">
                                    <div className="font-medium text-gray-700 dark:text-gray-300">
                                        Notification Frequency
                                    </div>
                                    <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Choose how often you'd like to receive
                                        update notifications.
                                    </div>

                                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <label
                                            className={`relative flex cursor-pointer rounded-lg border border-gray-200 p-4 shadow-sm focus:outline-none dark:border-gray-700 ${notifPrefs.notification_digest === 'asap' ? 'border-indigo-500 ring-2 ring-indigo-500' : ''}`}
                                        >
                                            <input
                                                type="radio"
                                                name="notification_digest"
                                                value="asap"
                                                checked={
                                                    notifPrefs.notification_digest ===
                                                    'asap'
                                                }
                                                onChange={(e) =>
                                                    updateDigest(
                                                        e.target
                                                            .value as NotificationPreferences['notification_digest'],
                                                    )
                                                }
                                                disabled={savingPrefs}
                                                className="sr-only"
                                            />
                                            <div className="flex w-full items-center justify-between">
                                                <div className="flex items-center">
                                                    <div className="text-sm">
                                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                                            As soon as possible
                                                        </p>
                                                        <p className="text-gray-500 dark:text-gray-400">
                                                            Get notified
                                                            immediately when
                                                            games are updated
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="shrink-0 text-indigo-600 dark:text-indigo-400">
                                                    <svg
                                                        className="h-6 w-6"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        strokeWidth="1.5"
                                                        stroke="currentColor"
                                                    >
                                                        <path
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                                                        />
                                                    </svg>
                                                </div>
                                            </div>
                                        </label>

                                        <label
                                            className={`relative flex cursor-pointer rounded-lg border border-gray-200 p-4 shadow-sm focus:outline-none dark:border-gray-700 ${notifPrefs.notification_digest === 'daily' ? 'border-indigo-500 ring-2 ring-indigo-500' : ''}`}
                                        >
                                            <input
                                                type="radio"
                                                name="notification_digest"
                                                value="daily"
                                                checked={
                                                    notifPrefs.notification_digest ===
                                                    'daily'
                                                }
                                                onChange={(e) =>
                                                    updateDigest(
                                                        e.target
                                                            .value as NotificationPreferences['notification_digest'],
                                                    )
                                                }
                                                disabled={savingPrefs}
                                                className="sr-only"
                                            />
                                            <div className="flex w-full items-center justify-between">
                                                <div className="flex items-center">
                                                    <div className="text-sm">
                                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                                            Daily digest
                                                        </p>
                                                        <p className="text-gray-500 dark:text-gray-400">
                                                            Get a summary of all
                                                            updates once per day
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="shrink-0 text-indigo-600 dark:text-indigo-400">
                                                    <svg
                                                        className="h-6 w-6"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        strokeWidth="1.5"
                                                        stroke="currentColor"
                                                    >
                                                        <path
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"
                                                        />
                                                    </svg>
                                                </div>
                                            </div>
                                        </label>

                                        <label
                                            className={`relative flex cursor-pointer rounded-lg border border-gray-200 p-4 shadow-sm focus:outline-none dark:border-gray-700 ${notifPrefs.notification_digest === 'weekly' ? 'border-indigo-500 ring-2 ring-indigo-500' : ''}`}
                                        >
                                            <input
                                                type="radio"
                                                name="notification_digest"
                                                value="weekly"
                                                checked={
                                                    notifPrefs.notification_digest ===
                                                    'weekly'
                                                }
                                                onChange={(e) =>
                                                    updateDigest(
                                                        e.target
                                                            .value as NotificationPreferences['notification_digest'],
                                                    )
                                                }
                                                disabled={savingPrefs}
                                                className="sr-only"
                                            />
                                            <div className="flex w-full items-center justify-between">
                                                <div className="flex items-center">
                                                    <div className="text-sm">
                                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                                            Weekly digest
                                                        </p>
                                                        <p className="text-gray-500 dark:text-gray-400">
                                                            Get a summary of all
                                                            updates once per
                                                            week
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="shrink-0 text-indigo-600 dark:text-indigo-400">
                                                    <svg
                                                        className="h-6 w-6"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        strokeWidth="1.5"
                                                        stroke="currentColor"
                                                    >
                                                        <path
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z"
                                                        />
                                                    </svg>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Addition Request Form Section */}
                    <div className="rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                Request VN Addition
                            </h2>
                            <p className="mb-6 text-gray-600 dark:text-gray-400">
                                Submit URLs for visual novels you'd like to see
                                added to the site. We support itch.io, Steam,
                                and other platforms. You can submit multiple
                                URLs at once, one per line.
                            </p>

                            <div className="space-y-4">
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Game URLs
                                    </label>
                                    <textarea
                                        value={requestText}
                                        onChange={(e) =>
                                            setRequestText(e.target.value)
                                        }
                                        rows={6}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="https://developer.itch.io/game-name&#10;https://store.steampowered.com/app/123456/game-name&#10;..."
                                    />
                                </div>
                                <div className="flex gap-3">
                                    <button
                                        onClick={submitRequest}
                                        disabled={
                                            submittingRequest ||
                                            requestText.trim() === ''
                                        }
                                        className={`rounded-lg px-4 py-2 text-white transition-colors focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${submittingRequest ? 'bg-blue-400' : 'bg-blue-600 hover:bg-blue-700'}`}
                                    >
                                        {submittingRequest
                                            ? 'Submitting...'
                                            : 'Submit Requests'}
                                    </button>
                                    <button
                                        onClick={() => setRequestText('')}
                                        className="rounded-lg bg-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-400 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            {/* Success panel mirroring Livewire */}
                            {showRequestSuccess && requestResults && (
                                <div
                                    className="mt-6 rounded-lg border border-green-200 bg-green-100 p-4 dark:border-green-800 dark:bg-green-900/20">
                                    <div className="flex items-start">
                                        <svg
                                            className="mt-0.5 mr-3 h-5 w-5 text-green-600 dark:text-green-400"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clipRule="evenodd"
                                            ></path>
                                        </svg>
                                        <div>
                                            <h3 className="font-medium text-green-800 dark:text-green-400">
                                                Requests Submitted Successfully!
                                            </h3>
                                            <div className="mt-2 text-sm text-green-700 dark:text-green-300">
                                                {requestResults.success_count >
                                                    0 && (
                                                        <p>
                                                            ✓{' '}
                                                            {
                                                                requestResults.success_count
                                                            }{' '}
                                                            new request(s) submitted
                                                        </p>
                                                    )}
                                                {requestResults.duplicate_count >
                                                    0 && (
                                                        <p>
                                                            ℹ{' '}
                                                            {
                                                                requestResults.duplicate_count
                                                            }{' '}
                                                            URL(s) already requested
                                                            by you
                                                        </p>
                                                    )}
                                                {(requestResults.already_exists_count ??
                                                    0) > 0 && (
                                                    <p>
                                                        ℹ{' '}
                                                        {
                                                            requestResults.already_exists_count
                                                        }{' '}
                                                        game(s) already exist on
                                                        the site
                                                    </p>
                                                )}
                                                {requestResults.invalid_count >
                                                    0 && (
                                                        <p>
                                                            ⚠{' '}
                                                            {
                                                                requestResults.invalid_count
                                                            }{' '}
                                                            invalid URL(s) skipped
                                                        </p>
                                                    )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Error panel mirroring Livewire */}
                            {requestResults?.errors &&
                                requestResults.errors.length > 0 && (
                                    <div
                                        className="mt-6 rounded-lg border border-red-200 bg-red-100 p-4 dark:border-red-800 dark:bg-red-900/20">
                                        <h3 className="mb-2 font-medium text-red-800 dark:text-red-400">
                                            Some errors occurred:
                                        </h3>
                                        <ul className="space-y-1 text-sm text-red-700 dark:text-red-300">
                                            {requestResults.errors.map(
                                                (error, idx) => (
                                                    <li key={idx}>• {error}</li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}

                            {/* Guidelines Section */}
                            <div
                                className="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                <h3 className="mb-2 font-medium text-blue-800 dark:text-blue-400">
                                    Guidelines:
                                </h3>
                                <ul className="space-y-1 text-sm text-blue-700 dark:text-blue-300">
                                    <li>
                                        • Supported platforms: itch.io, Steam,
                                        and other game storefronts
                                    </li>
                                    <li>
                                        • Submit one URL per line for bulk
                                        requests
                                    </li>
                                    <li>• Maximum 50 URLs per submission</li>
                                    <li>
                                        • Games already on the site will be
                                        automatically filtered out
                                    </li>
                                    <li>
                                        • Duplicate requests are automatically
                                        handled
                                    </li>
                                    <li>
                                        • You'll be able to track the status of
                                        your requests in your dashboard
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* User Addition Requests Section */}
                    <div className="rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    My Addition Requests
                                </h2>
                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                    {requests.length} request(s)
                                </span>
                            </div>

                            {/* Filters */}
                            <div className="mb-6 flex flex-col gap-4 sm:flex-row">
                                <div className="flex-1">
                                    <input
                                        type="text"
                                        value={requestSearch}
                                        onChange={(e) =>
                                            setRequestSearch(e.target.value)
                                        }
                                        placeholder="Search by URL or status..."
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <select
                                        value={requestStatus}
                                        onChange={(e) => {
                                            const next = e.target.value as
                                                | 'all'
                                                | 'pending'
                                                | 'processing'
                                                | 'completed'
                                                | 'rejected';
                                            setRequestStatus(next);
                                            loadRequests({status: next});
                                        }}
                                        className="rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="all">
                                            All Requests
                                        </option>
                                        <option value="pending">Pending</option>
                                        <option value="processing">
                                            Processing
                                        </option>
                                        <option value="completed">
                                            Completed
                                        </option>
                                        <option value="rejected">
                                            Rejected
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div className="space-y-4">
                                {(requests.length
                                        ? requests
                                        : recentRequestsInitial.slice(0, 5)
                                ).map((request) => (
                                    <div
                                        key={request.id}
                                        className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="min-w-0 flex-1">
                                                <div className="mb-2 flex items-center gap-2">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                            request.status_color ===
                                                            'yellow'
                                                                ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400'
                                                                : request.status_color ===
                                                                'green'
                                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
                                                                    : request.status_color ===
                                                                    'red'
                                                                        ? 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400'
                                                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'
                                                        }`}
                                                    >
                                                        {request.status_label}
                                                    </span>
                                                    <span className="text-xs text-gray-500 dark:text-gray-400">
                                                        Requested{' '}
                                                        {new Date(
                                                            request.created_at,
                                                        ).toLocaleDateString()}
                                                    </span>
                                                </div>

                                                <div className="mb-2">
                                                    <a
                                                        href={request.game_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="break-all text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        {request.game_url}
                                                        <svg
                                                            className="ml-1 inline h-3 w-3"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                strokeWidth="2"
                                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                                            />
                                                        </svg>
                                                    </a>
                                                </div>

                                                {request.game && (
                                                    <div className="mb-2">
                                                        <span className="text-sm text-green-600 dark:text-green-400">
                                                            ✓ Added to site:
                                                            <a
                                                                href={route(
                                                                    'games.show',
                                                                    request.game
                                                                        .slug,
                                                                )}
                                                                className="ml-1 hover:underline"
                                                            >
                                                                {
                                                                    request.game
                                                                        .name
                                                                }
                                                            </a>
                                                        </span>
                                                    </div>
                                                )}

                                                {request.rejection_reason && (
                                                    <div className="mb-2">
                                                        <div className="text-sm text-red-600 dark:text-red-400">
                                                            <strong>
                                                                Rejection
                                                                reason:
                                                            </strong>{' '}
                                                            {
                                                                request.rejection_reason
                                                            }
                                                        </div>
                                                    </div>
                                                )}

                                                {request.reviewed_at && (
                                                    <div className="text-xs text-gray-500 dark:text-gray-400">
                                                        Reviewed{' '}
                                                        {new Date(
                                                            request.reviewed_at,
                                                        ).toLocaleDateString()}
                                                        {request.reviewer && (
                                                            <>
                                                                {' '}
                                                                by{' '}
                                                                {
                                                                    request
                                                                        .reviewer
                                                                        .name
                                                                }
                                                            </>
                                                        )}
                                                    </div>
                                                )}
                                            </div>

                                            <div className="ml-4 flex flex-shrink-0 flex-col items-end gap-2">
                                                {(request.status ===
                                                    'pending' ||
                                                    request.status ===
                                                    'processing') && (
                                                    <button
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    'Are you sure you want to cancel this request? This action cannot be undone.',
                                                                )
                                                            ) {
                                                                cancelRequest(
                                                                    request.id,
                                                                );
                                                            }
                                                        }}
                                                        className="inline-flex items-center rounded border border-red-300 bg-white px-2.5 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                                    >
                                                        <svg
                                                            className="mr-1 h-3 w-3"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                strokeWidth="2"
                                                                d="M6 18L18 6M6 6l12 12"
                                                            />
                                                        </svg>
                                                        Cancel
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                {requestsLoading && (
                                    <div className="text-center text-sm text-gray-500 dark:text-gray-400">
                                        Loading...
                                    </div>
                                )}
                                {!requestsLoading &&
                                    requests.length === 0 &&
                                    recentRequestsInitial.length === 0 && (
                                        <div className="py-8 text-center">
                                            <svg
                                                className="mx-auto h-12 w-12 text-gray-400"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                                />
                                            </svg>
                                            <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                                                No requests found
                                            </h3>
                                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                You haven't submitted any
                                                addition requests yet.
                                            </p>
                                        </div>
                                    )}
                            </div>
                        </div>
                    </div>

                    {/* Game Management Section */}
                    <div className="rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                Game Management
                            </h2>
                            <p className="mb-4 text-gray-600 dark:text-gray-400">
                                Manage your games published on itch.io. Add
                                additional download links and update game
                                information.
                            </p>

                            {itchioData.username ? (
                                <div
                                    className="mb-4 flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                                    <div className="flex items-center gap-3">
                                        <ItchioIcon className="h-5 w-5 text-blue-600 dark:text-blue-400"/>
                                        <div>
                                            <div className="text-sm font-medium text-blue-800 dark:text-blue-300">
                                                Connected: {itchioData.username}
                                                .itch.io
                                            </div>
                                            <div className="text-xs text-blue-600 dark:text-blue-400">
                                                {itchioData.ownedGamesCount}{' '}
                                                {itchioData.ownedGamesCount ===
                                                1
                                                    ? 'game'
                                                    : 'games'}{' '}
                                                found
                                                {itchioData.gamesWithLinks >
                                                    0 && (
                                                        <>
                                                            {' '}
                                                            •{' '}
                                                            {
                                                                itchioData.gamesWithLinks
                                                            }{' '}
                                                            with download links
                                                        </>
                                                    )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div
                                    className="mb-4 flex items-center justify-between rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-900/20">
                                    <div className="flex items-center gap-3">
                                        <svg
                                            className="h-5 w-5 text-yellow-600 dark:text-yellow-400"
                                            viewBox="0 0 245.371 220.736"
                                            fill="currentColor"
                                        >
                                            <path
                                                d="M31.99 1.365C21.287 7.72 13.498 18.838 8.617 30.819 3.937 42.3 1.172 55.632 1.172 70.221c0 14.391 2.774 27.523 7.546 38.803 4.971 11.68 12.569 22.401 23.07 28.747 10.501 6.345 23.806 9.519 39.993 9.519h103.507c16.286 0 29.59-3.174 40.091-9.52 10.502-6.345 18.1-17.066 23.071-28.746 4.772-11.28 7.546-24.412 7.546-38.803 0-14.589-2.765-27.92-7.445-39.402-4.881-11.98-12.67-23.098-23.373-29.454C204.081-5.091 190.677-8.265 174.39-8.265H70.883c-16.287 0-29.59 3.174-40.091 9.52l1.198 2.065zm45.893 13.58c-6.345 0-12.491 1.198-18.24 3.562-5.55 2.364-10.501 5.948-14.784 10.43-4.284 4.483-7.669 10.032-9.934 16.377-2.265 6.146-3.462 13.09-3.462 20.433 0 7.144 1.197 14.088 3.462 20.234 2.265 6.345 5.65 11.894 9.934 16.377 4.283 4.482 9.235 8.066 14.784 10.43 5.749 2.364 11.895 3.562 18.24 3.562h89.304c6.345 0 12.491-1.198 18.24-3.562 5.55-2.364 10.501-5.948 14.785-10.43 4.283-4.483 7.668-10.032 9.933-16.377 2.265-6.146 3.463-13.09 3.463-20.234 0-7.343-1.198-14.287-3.463-20.433-2.265-6.345-5.65-11.894-9.933-16.377-4.284-4.482-9.235-8.066-14.785-10.43-5.749-2.364-11.895-3.562-18.24-3.562H77.883z"/>
                                        </svg>
                                        <div className="text-sm text-yellow-800 dark:text-yellow-300">
                                            Connect your itch.io account to
                                            manage your games
                                        </div>
                                    </div>
                                </div>
                            )}

                            <Link
                                href={route('my-games.index')}
                                className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    className="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                    />
                                </svg>
                                <span>Manage My Games</span>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Right Column - Connected Accounts */}
                <div className="space-y-6 lg:col-span-2">
                    <ConnectedAccounts
                        user={user as unknown as TypedUser}
                        connectedProviders={connectedProviders}
                        socialAccounts={
                            socialAccounts as unknown as Record<
                                string,
                                TypedSocialAccount
                            >
                        }
                    />

                    {/* Ignored Games Section */}
                    <div className="rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    Ignored Games
                                </h2>
                                <span className="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    {ignoredGamesCount} {ignoredGamesCount === 1 ? 'game' : 'games'}
                                </span>
                            </div>

                            <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                Games you've ignored won't appear in search results by default. You can manage your ignored games here.
                            </p>

                            {ignoredGames.length > 0 ? (
                                <>
                                    <div className="space-y-3">
                                        {ignoredGames.map((game) => {
                                            const thumbnailUrl = game.optimized_thumbnails?.default?.path || game.thumb_url;

                                            return (
                                                <div
                                                    key={game.id}
                                                    className="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 transition-colors hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800/50 dark:hover:bg-gray-800"
                                                >
                                                    <Link
                                                        href={route('games.show', game.slug)}
                                                        className="flex-shrink-0"
                                                    >
                                                        {thumbnailUrl ? (
                                                            <img
                                                                src={thumbnailUrl}
                                                                alt={game.name}
                                                                className="h-16 w-16 rounded object-cover"
                                                            />
                                                        ) : (
                                                            <div className="flex h-16 w-16 items-center justify-center rounded bg-gray-200 text-2xl dark:bg-gray-700">
                                                                🎮
                                                            </div>
                                                        )}
                                                    </Link>

                                                    <div className="min-w-0 flex-1">
                                                        <Link
                                                            href={route('games.show', game.slug)}
                                                            className="block font-medium text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                                        >
                                                            <div className="truncate">{game.name}</div>
                                                        </Link>
                                                        {game.platform && (
                                                            <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                                {game.platform === 'itch_io' ? 'itch.io' : game.platform === 'steam' ? 'Steam' : 'Other'}
                                                            </div>
                                                        )}
                                                    </div>

                                                    <button
                                                        onClick={() => handleUnignoreGame(game.id)}
                                                        className="flex-shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                                                        title="Remove from ignore list"
                                                    >
                                                        Unignore
                                                    </button>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {ignoredGamesCount > 10 && (
                                        <div className="mt-4 text-center">
                                            <Link
                                                href={route('games.index', { showIgnored: true })}
                                                className="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                View all {ignoredGamesCount} ignored games
                                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                </svg>
                                            </Link>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="py-8 text-center">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                                        No ignored games
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        You haven't ignored any games yet. Click the ignore button on any game card to hide it from search results.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Danger Zone */}
                    <div className="rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-red-600 dark:text-red-500">
                                Danger Zone
                            </h2>
                            <div
                                className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <h3 className="mb-2 font-medium text-red-800 dark:text-red-400">
                                    Delete Account
                                </h3>
                                <p className="mb-4 text-sm text-red-700 dark:text-red-300">
                                    Once you delete your account, there is no
                                    going back. Please be certain.
                                </p>
                                <button
                                    onClick={async () => {
                                        try {
                                            if (
                                                !confirm(
                                                    'Are you sure you want to delete your account? This action cannot be undone.',
                                                )
                                            ) {
                                                return;
                                            }
                                            const res = await authenticatedFetch(
                                                route('user.account.delete'),
                                                {
                                                    method: 'POST',
                                                    body: JSON.stringify({
                                                        _method: 'DELETE',
                                                        password: '',
                                                    }),
                                                },
                                            );
                                            if (res.ok) {
                                                // Redirect to home after deletion
                                                window.location.href =
                                                    route('home');
                                            } else {
                                                const data = await res
                                                    .json()
                                                    .catch(() => ({}));
                                                alert(
                                                    data?.message ||
                                                    'Failed to delete account. Please verify your password.',
                                                );
                                            }
                                        } catch {
                                            alert(
                                                'An error occurred while deleting the account.',
                                            );
                                        }
                                    }}
                                    className="rounded-lg bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700"
                                >
                                    Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Bug Report Detail Modal */}
            <dialog
                ref={bugReportDialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby="bug-report-modal-title"
                className="m-auto max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-lg border border-gray-200 bg-white p-0 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800"
                onClick={(e) => {
                    if (e.target === e.currentTarget) closeBugReportModal();
                }}
            >
                <div className="relative">
                        {/* Header */}
                        <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 id="bug-report-modal-title" className="text-lg font-semibold text-gray-900 dark:text-white">
                                Bug Report #{selectedBugReport?.id}
                            </h3>
                            <button
                                onClick={closeBugReportModal}
                                className="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            >
                                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {/* Content */}
                        <div className="max-h-[calc(90vh-180px)] overflow-y-auto p-6">
                            {loadingBugReport ? (
                                <div className="flex items-center justify-center py-8">
                                    <svg className="h-8 w-8 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                </div>
                            ) : selectedBugReport ? (
                                <>
                                    {/* Report Details */}
                                    <div className="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                        <div className="mb-3 flex items-center gap-2">
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusBadgeClasses(selectedBugReport.status_color)}`}>
                                                {selectedBugReport.status_label}
                                            </span>
                                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                                Submitted {new Date(selectedBugReport.created_at).toLocaleDateString()}
                                            </span>
                                        </div>

                                        <p className="mb-3 text-sm text-gray-700 dark:text-gray-300">
                                            {selectedBugReport.description}
                                        </p>

                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            <strong>Page:</strong>{' '}
                                            <a href={selectedBugReport.page_url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline dark:text-blue-400">
                                                {selectedBugReport.page_title || selectedBugReport.page_url}
                                            </a>
                                        </div>

                                        {selectedBugReport.admin_notes && (
                                            <div className="mt-3 rounded border-l-4 border-blue-500 bg-blue-50 p-3 dark:bg-blue-900/20">
                                                <div className="text-xs font-medium text-blue-700 dark:text-blue-400">Admin Notes:</div>
                                                <p className="mt-1 text-sm text-blue-600 dark:text-blue-300">{selectedBugReport.admin_notes}</p>
                                            </div>
                                        )}
                                    </div>

                                    {/* Comments Section */}
                                    <div className="mb-6">
                                        <h4 className="mb-3 font-medium text-gray-900 dark:text-white">
                                            Conversation ({bugReportComments.length})
                                        </h4>

                                        {bugReportComments.length === 0 ? (
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                No comments yet. Add additional information below.
                                            </p>
                                        ) : (
                                            <div className="space-y-3">
                                                {bugReportComments.map((comment) => (
                                                    <div
                                                        key={comment.id}
                                                        className={`rounded-lg p-3 ${
                                                            comment.is_from_admin
                                                                ? 'border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                                                : 'border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'
                                                        }`}
                                                    >
                                                        <div className="mb-1 flex items-center gap-2">
                                                            <span className={`text-sm font-medium ${comment.is_from_admin ? 'text-blue-700 dark:text-blue-400' : 'text-gray-900 dark:text-white'}`}>
                                                                {comment.user.name}
                                                            </span>
                                                            {comment.is_from_admin && (
                                                                <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                                                                    Staff
                                                                </span>
                                                            )}
                                                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                                                {new Date(comment.created_at).toLocaleDateString()} at{' '}
                                                                {new Date(comment.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                            </span>
                                                        </div>
                                                        <p className="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">
                                                            {comment.message}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Add Comment Form - only for open/in_progress reports */}
                                    {!selectedBugReport.is_closed && (
                                        <div>
                                            <label htmlFor="new-comment" className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Add Information
                                            </label>
                                            <textarea
                                                id="new-comment"
                                                value={newComment}
                                                onChange={(e) => setNewComment(e.target.value)}
                                                rows={3}
                                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                placeholder="Provide additional details or respond to staff..."
                                            />
                                            <div className="mt-2 flex justify-end">
                                                <button
                                                    onClick={submitBugReportComment}
                                                    disabled={submittingComment || newComment.trim().length < 5}
                                                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {submittingComment ? 'Sending...' : 'Send'}
                                                </button>
                                            </div>
                                        </div>
                                    )}

                                    {selectedBugReport.is_closed && (
                                        <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
                                            You have closed this report.
                                        </div>
                                    )}
                                </>
                            ) : null}
                        </div>

                        {/* Footer */}
                        <div className="flex gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                            {selectedBugReport && !selectedBugReport.is_closed && (
                                <button
                                    onClick={closeTicket}
                                    disabled={closingTicket}
                                    className="flex-1 cursor-pointer rounded-lg bg-red-100 px-4 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-200 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/40"
                                >
                                    {closingTicket ? 'Closing...' : 'Close Ticket'}
                                </button>
                            )}
                            <button
                                onClick={closeBugReportModal}
                                className="flex-1 cursor-pointer rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                {selectedBugReport?.is_closed ? 'Close' : 'Keep Open'}
                            </button>
                        </div>
                </div>
            </dialog>
        </>
    );
}
