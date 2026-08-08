import { beforeEach, describe, expect, test, vi } from 'vitest';

const notificationApi = vi.hoisted(() => ({
    storePushSubscription: vi.fn(),
    destroyPushSubscription: vi.fn(),
}));

vi.mock('@/api/notifications', () => notificationApi);

import { PushPermissionDeniedError, subscribeToPush, syncPushSubscription, unsubscribeFromPush } from './push';

const json = { endpoint: 'https://push.example/subscription', keys: { p256dh: 'key', auth: 'auth' } };
const subscription = {
    toJSON: vi.fn(() => json),
    unsubscribe: vi.fn(async () => true),
    options: { applicationServerKey: null },
} as unknown as PushSubscription;
const pushManager = {
    getSubscription: vi.fn<() => Promise<PushSubscription | null>>(),
    subscribe: vi.fn<() => Promise<PushSubscription>>(),
};

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('Notification', { permission: 'granted', requestPermission: vi.fn(async () => 'granted') });
    vi.stubGlobal('PushManager', class {});
    Object.defineProperty(navigator, 'serviceWorker', {
        configurable: true,
        value: { ready: Promise.resolve({ pushManager }) },
    });
    pushManager.getSubscription.mockResolvedValue(null);
    pushManager.subscribe.mockResolvedValue(subscription);
    notificationApi.storePushSubscription.mockResolvedValue(undefined);
    notificationApi.destroyPushSubscription.mockResolvedValue(undefined);
});

describe('browser push subscription lifecycle', () => {
    test('subscribes with the VAPID key and stores the returned subscription', async () => {
        await expect(subscribeToPush('AQAB')).resolves.toBe(subscription);
        expect(pushManager.subscribe).toHaveBeenCalledWith(expect.objectContaining({ userVisibleOnly: true }));
        expect(notificationApi.storePushSubscription).toHaveBeenCalledWith(json, true);
    });

    test('throws a typed error when browser permission is denied', async () => {
        vi.stubGlobal('Notification', { permission: 'default', requestPermission: vi.fn(async () => 'denied') });
        await expect(subscribeToPush('AQAB')).rejects.toBeInstanceOf(PushPermissionDeniedError);
        expect(pushManager.subscribe).not.toHaveBeenCalled();
    });

    test('replaces an existing subscription created with a different VAPID key', async () => {
        const stale = {
            toJSON: vi.fn(() => ({ ...json, endpoint: 'https://push.example/stale' })),
            unsubscribe: vi.fn(async () => true),
            options: { applicationServerKey: new Uint8Array([9, 9, 9]).buffer },
        } as unknown as PushSubscription;
        pushManager.getSubscription.mockResolvedValue(stale);

        await expect(subscribeToPush('AQAB')).resolves.toBe(subscription);

        expect(notificationApi.destroyPushSubscription).toHaveBeenCalledWith(stale.toJSON());
        expect(stale.unsubscribe).toHaveBeenCalled();
        expect(pushManager.subscribe).toHaveBeenCalled();
        expect(notificationApi.storePushSubscription).toHaveBeenCalledWith(json, true);
    });

    test('re-stores an existing subscription during session sync', async () => {
        pushManager.getSubscription.mockResolvedValue(subscription);
        await expect(syncPushSubscription()).resolves.toBe(subscription);
        expect(notificationApi.storePushSubscription).toHaveBeenCalledWith(json, false);
    });

    test('deletes the current endpoint before unsubscribing the browser', async () => {
        pushManager.getSubscription.mockResolvedValue(subscription);
        await expect(unsubscribeFromPush()).resolves.toBe(true);
        expect(notificationApi.destroyPushSubscription).toHaveBeenCalledWith(json);
        expect(subscription.unsubscribe).toHaveBeenCalled();
    });
});
