import {Head, Link} from '@inertiajs/react';

interface NotificationHistory {
    id: number;
    type: string;
    title: string;
    message: string;
    created_at: string;
    read_at?: string;
    data?: Record<string, unknown>;
}

interface DigestNotificationsProps {
    date: string;
    formattedDate: string;
    notifications: NotificationHistory[];
    hasNotifications: boolean;
    hasAnyNotifications: boolean;
    metaTags?: {
        title?: string;
    };
}

export default function DigestNotifications({
                                                date, // eslint-disable-line @typescript-eslint/no-unused-vars
                                                formattedDate,
                                                notifications,
                                                hasNotifications,
                                                hasAnyNotifications,
                                                metaTags,
                                            }: DigestNotificationsProps) {
    const getNotificationIcon = (type: string) => {
        switch (type) {
            case 'game_update':
                return (
                    <svg
                        className="h-5 w-5 text-blue-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                        />
                    </svg>
                );
            case 'new_game':
                return (
                    <svg
                        className="h-5 w-5 text-green-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                        />
                    </svg>
                );
            case 'system':
                return (
                    <svg
                        className="h-5 w-5 text-yellow-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                );
            default:
                return (
                    <svg
                        className="h-5 w-5 text-gray-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                );
        }
    };

    const formatNotificationTime = (timestamp: string) => {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (<>

            <Head title={metaTags?.title || 'Notification Digest'}/>

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-blue-600">
                            Notification Digest
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Notifications for {formattedDate}
                        </p>
                    </div>
                    <div className="mt-4 sm:mt-0">
                        <Link
                            href={route('dashboard')}
                            className="inline-flex items-center space-x-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                        >
                            <svg
                                className="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                                />
                            </svg>
                            <span>Back to Dashboard</span>
                        </Link>
                    </div>
                </div>

                {/* Notifications Content */}
                <div
                    className="rounded-xl border border-gray-200/50 bg-white/70 p-6 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                    {hasNotifications ? (
                        <div className="space-y-4">
                            <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">
                                Your Notifications
                            </h2>
                            {notifications.map((notification) => (
                                <div
                                    key={notification.id}
                                    className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50"
                                >
                                    <div className="flex items-start space-x-3">
                                        <div className="mt-0.5 flex-shrink-0">
                                            {getNotificationIcon(
                                                notification.type,
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center justify-between">
                                                <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                                                    {notification.title}
                                                </h3>
                                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                                    {formatNotificationTime(
                                                        notification.created_at,
                                                    )}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-gray-600 dark:text-gray-400">
                                                {notification.message}
                                            </p>
                                            {notification.data && (
                                                <div className="mt-2 text-sm text-gray-500 dark:text-gray-500">
                                                    Additional details available
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-12 text-center">
                            <svg
                                className="mx-auto h-12 w-12 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                />
                            </svg>
                            <h3 className="mt-2 text-lg font-medium text-gray-900 dark:text-white">
                                No Notifications
                            </h3>
                            <p className="mt-1 text-gray-500 dark:text-gray-400">
                                {hasAnyNotifications
                                    ? "You don't have any notifications for this date."
                                    : 'There are no notifications available for this date.'}
                            </p>
                        </div>
                    )}
                </div>

                {/* Navigation */}
                <div className="flex items-center justify-between">
                    <Link
                        href={route('dashboard')}
                        className="inline-flex items-center space-x-2 text-blue-600 transition-colors hover:text-blue-700"
                    >
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            />
                        </svg>
                        <span>Back to Dashboard</span>
                    </Link>
                </div>
            </div>
        </>
    );
}
