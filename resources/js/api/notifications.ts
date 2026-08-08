import http from '@/utils/http';

import type { ChannelHealth } from '@/utils/notification-health';

export interface NotificationHealth {
    browser: ChannelHealth & { lastFailure?: { code: string; error: string } | null };
    discord: ChannelHealth & {
        dmStatus?: 'unverified' | 'deliverable' | 'undeliverable';
        dmStatusReason?: string | null;
        userInstallUrl?: string | null;
        userInstalledAt?: string | null;
        botOnline?: boolean;
        lastTest?: { id: number; status: string; error?: string | null } | null;
    };
    digest: { frequency: string; lastSentAt?: string | null; nextScheduledAt?: string | null };
}

export async function fetchNotificationHealth(): Promise<NotificationHealth> {
    const { data } = await http.get<{ success: boolean; health: NotificationHealth }>(route('browser-api.dashboard.notification-health.show'));
    if (!data.success) throw new Error('Failed to load notification health');

    return data.health;
}

export async function testNotificationChannel(channel: 'browser' | 'discord'): Promise<{ notificationId?: number }> {
    const { data } = await http.post<{ success: boolean; notificationId?: number }>(route('browser-api.dashboard.notification-health.test'), {
        channel,
    });
    if (!data.success) throw new Error(`The ${channel} test did not complete successfully.`);

    return { notificationId: data.notificationId };
}

export async function storePushSubscription(subscription: PushSubscriptionJSON, reactivate = false): Promise<void> {
    const { data } = await http.post<{ success: boolean; message?: string }>(route('browser-api.push-subscriptions.store'), {
        subscription,
        reactivate,
    });
    if (!data.success) throw new Error(data.message || 'Failed to save this browser subscription');
}

export async function destroyPushSubscription(subscription: PushSubscriptionJSON): Promise<void> {
    const { data } = await http.delete<{ success: boolean; message?: string }>(route('browser-api.push-subscriptions.destroy'), {
        data: { subscription },
    });
    if (!data.success) throw new Error(data.message || 'Failed to remove this browser subscription');
}
