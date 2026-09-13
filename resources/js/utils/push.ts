import { destroyPushSubscription, storePushSubscription } from '@/api/notifications';

export class PushPermissionDeniedError extends Error {
    constructor() {
        super('Browser notification permission was denied. Enable notifications in your browser settings, then try again.');
        this.name = 'PushPermissionDeniedError';
    }
}

function applicationServerKey(value: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));
    for (let index = 0; index < raw.length; index++) bytes[index] = raw.charCodeAt(index);
    return bytes;
}

function serialized(subscription: PushSubscription): PushSubscriptionJSON {
    return subscription.toJSON();
}

function usesApplicationServerKey(subscription: PushSubscription, publicKey: string): boolean {
    const currentKey = subscription.options.applicationServerKey;
    if (!currentKey) return true;

    const expected = applicationServerKey(publicKey);
    const current = new Uint8Array(currentKey);

    return current.length === expected.length && current.every((value, index) => value === expected[index]);
}

let pendingStore: Promise<void> = Promise.resolve();

async function store(subscription: PushSubscription, reactivate = false): Promise<void> {
    pendingStore = pendingStore.catch(() => {}).then(() => storePushSubscription(serialized(subscription), reactivate));
    await pendingStore;
}

export async function subscribeToPush(publicKey: string): Promise<PushSubscription> {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) throw new Error('Push notifications are not supported by this browser.');

    const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission();
    if (permission !== 'granted') throw new PushPermissionDeniedError();

    const registration = await navigator.serviceWorker.ready;
    let existing = await registration.pushManager.getSubscription();
    if (existing && !usesApplicationServerKey(existing, publicKey)) {
        await destroyPushSubscription(serialized(existing));
        await existing.unsubscribe();
        existing = null;
    }
    const subscription =
        existing ??
        (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey(publicKey),
        }));
    await store(subscription, true);

    return subscription;
}

export async function unsubscribeFromPush(): Promise<boolean> {
    if (!('serviceWorker' in navigator)) return false;
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) return false;

    await pendingStore.catch(() => {});
    await destroyPushSubscription(serialized(subscription));

    return subscription.unsubscribe();
}

export async function syncPushSubscription(): Promise<PushSubscription | null> {
    if (!('serviceWorker' in navigator) || Notification.permission !== 'granted') return null;
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) return null;
    await store(subscription);

    return subscription;
}

export async function localPushSubscription(): Promise<PushSubscription | null> {
    if (!('serviceWorker' in navigator)) return null;
    // Logout must detach the endpoint after any in-flight account association.
    await pendingStore.catch(() => {});
    const registration = await navigator.serviceWorker.getRegistration();
    return registration ? registration.pushManager.getSubscription() : null;
}
