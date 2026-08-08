import { render, screen } from '@testing-library/svelte';
import { beforeEach, describe, expect, test, vi } from 'vitest';

const notificationApi = vi.hoisted(() => ({
    fetchNotificationHealth: vi.fn(),
    testNotificationChannel: vi.fn(),
}));

const pushApi = vi.hoisted(() => ({
    localPushSubscription: vi.fn(),
    subscribeToPush: vi.fn(),
    unsubscribeFromPush: vi.fn(),
}));

vi.mock('@/api/notifications', () => notificationApi);
vi.mock('@/utils/push', () => pushApi);

import NotificationSettings from './NotificationSettings.svelte';

const initialPreferences = {
    browser_notifications_enabled: false,
    discord_notifications_enabled: false,
    notification_digest: 'daily',
};

const health = {
    browser: { configured: false, enabled: false },
    discord: { configured: true, enabled: false, linked: true, botOnline: true },
    digest: { frequency: 'daily' },
};

describe('NotificationSettings', () => {
    beforeEach(() => {
        notificationApi.fetchNotificationHealth.mockResolvedValue(health);
        pushApi.localPushSubscription.mockResolvedValue(null);
    });

    test('hides browser push settings and health when VAPID is not configured', async () => {
        render(NotificationSettings, { props: { initialPreferences, hasDiscord: true } });

        expect(await screen.findByText('Discord notification status')).toBeTruthy();
        expect(screen.queryByText('Browser Push Notifications')).toBeNull();
        expect(screen.queryByText('Browser notification status')).toBeNull();
        expect(screen.queryByRole('checkbox', { name: 'Enable browser notifications' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Send test DM' })).toBeNull();
        expect(pushApi.localPushSubscription).not.toHaveBeenCalled();
    });

    test('shows browser push settings and health when VAPID is configured', async () => {
        render(NotificationSettings, { props: { initialPreferences, hasDiscord: true, vapidPublicKey: 'public-key' } });

        expect(await screen.findByText('Browser notification status')).toBeTruthy();
        expect(screen.getByText('Browser Push Notifications')).toBeTruthy();
        expect(screen.getByRole('checkbox', { name: 'Enable browser notifications' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Send test notification' })).toBeNull();
        expect(pushApi.localPushSubscription).toHaveBeenCalledOnce();
    });

    test('places the Discord test action with an active Discord health state', async () => {
        notificationApi.fetchNotificationHealth.mockResolvedValue({
            ...health,
            discord: { ...health.discord, enabled: true, dmStatus: 'deliverable' },
        });

        render(NotificationSettings, {
            props: {
                initialPreferences: { ...initialPreferences, discord_notifications_enabled: true },
                hasDiscord: true,
            },
        });

        const testButton = await screen.findByRole('button', { name: 'Send test DM' });

        expect(testButton.className).toContain('bg-blue-600');
        expect(screen.getByText('The bot checks for queued notifications once a minute, so a test may take up to 75 seconds.')).toBeTruthy();
    });

    test('uses the primary action style for an active browser test', async () => {
        notificationApi.fetchNotificationHealth.mockResolvedValue({
            ...health,
            browser: { configured: true, enabled: true, subscriptionCount: 0 },
        });

        render(NotificationSettings, {
            props: {
                initialPreferences: { ...initialPreferences, browser_notifications_enabled: true },
                hasDiscord: true,
                vapidPublicKey: 'public-key',
            },
        });

        const testButton = await screen.findByRole('button', { name: 'Send test notification' });

        expect(testButton.className).toContain('bg-blue-600');
    });
});
