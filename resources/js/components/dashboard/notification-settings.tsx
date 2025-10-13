import {toast} from '@/utils/toast';
import {useEffect, useState} from 'react';

interface NotificationPreferences {
    browser_notifications_enabled: boolean;
    discord_notifications_enabled: boolean;
    notification_digest: string;
}

export function NotificationSettings() {
    const [preferences, setPreferences] = useState<NotificationPreferences>({
        browser_notifications_enabled: false,
        discord_notifications_enabled: false,
        notification_digest: 'asap',
    });
    const [loading, setLoading] = useState(false);
    const [, setVapidPublicKey] = useState<string>('');

    useEffect(() => {
        // Fetch current preferences
        fetch(route('react-api.dashboard.notifications.get'))
            .then((response) => response.json())
            .then((data) => {
                if (data.preferences) {
                    setPreferences(data.preferences);
                }
                if (data.vapidPublicKey) {
                    setVapidPublicKey(data.vapidPublicKey);
                    void data.vapidPublicKey;
                }
            })
            .catch((error) => {
                console.error(
                    'Error fetching notification preferences:',
                    error,
                );
            });
    }, []);

    const updatePreferences = async () => {
        setLoading(true);

        try {
            const response = await fetch(
                route('react-api.dashboard.notifications.update'),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    body: JSON.stringify(preferences),
                },
            );

            const data = await response.json();

            if (data.success) {
                toast.success('Notification preferences updated successfully.');
            } else {
                toast.error(data.message || 'Failed to update preferences.');
            }
        } catch (error) {
            console.error('Error updating notification preferences:', error);
            toast.error('An error occurred while updating preferences.');
        } finally {
            setLoading(false);
        }
    };

    const handleBrowserNotificationToggle = async () => {
        if (!preferences.browser_notifications_enabled) {
            // Request permission for browser notifications
            if ('Notification' in window) {
                const permission = await Notification.requestPermission();

                if (permission === 'granted') {
                    setPreferences((prev) => ({
                        ...prev,
                        browser_notifications_enabled: true,
                    }));
                } else {
                    toast.error('Browser notification permission denied.');
                    return;
                }
            } else {
                toast.error('Browser notifications are not supported.');
                return;
            }
        } else {
            setPreferences((prev) => ({
                ...prev,
                browser_notifications_enabled: false,
            }));
        }
    };

    return (
        <div
            className="rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
            <div className="p-6">
                <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    Notification Settings
                </h2>

                <div className="space-y-6">
                    {/* Browser Notifications */}
                    <div className="flex items-center justify-between">
                        <div>
                            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Browser Notifications
                            </label>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Receive notifications directly in your browser
                            </p>
                        </div>
                        <label className="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={
                                    preferences.browser_notifications_enabled
                                }
                                onChange={handleBrowserNotificationToggle}
                                className="peer sr-only"
                            />
                            <div
                                className="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"></div>
                        </label>
                    </div>

                    {/* Discord Notifications */}
                    <div className="flex items-center justify-between">
                        <div>
                            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Discord Notifications
                            </label>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Receive notifications via Discord DM
                            </p>
                        </div>
                        <label className="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={
                                    preferences.discord_notifications_enabled
                                }
                                onChange={(e) =>
                                    setPreferences((prev) => ({
                                        ...prev,
                                        discord_notifications_enabled:
                                        e.target.checked,
                                    }))
                                }
                                className="peer sr-only"
                            />
                            <div
                                className="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"></div>
                        </label>
                    </div>

                    {/* Notification Digest */}
                    <div>
                        <label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Notification Digest
                        </label>
                        <select
                            value={preferences.notification_digest}
                            onChange={(e) =>
                                setPreferences((prev) => ({
                                    ...prev,
                                    notification_digest: e.target.value,
                                }))
                            }
                            className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="asap">As soon as possible</option>
                            <option value="hourly">Hourly digest</option>
                            <option value="daily">Daily digest</option>
                            <option value="weekly">Weekly digest</option>
                            <option value="never">Never</option>
                        </select>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            How often you want to receive notification summaries
                        </p>
                    </div>

                    {/* Save Button */}
                    <div className="border-t border-gray-200 pt-4 dark:border-gray-700">
                        <button
                            onClick={updatePreferences}
                            disabled={loading}
                            className="w-full rounded-lg bg-blue-600 px-4 py-2 text-white shadow-md transition-all duration-200 hover:bg-blue-700 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {loading ? 'Saving...' : 'Save Preferences'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
