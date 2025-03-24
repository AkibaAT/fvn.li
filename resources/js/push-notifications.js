/**
 * Push Notification Handler
 * This file handles service worker registration and push notification subscription
 */

// Convert URL base64 to Uint8Array
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Main class to handle push notifications
class PushNotifications {
    constructor() {
        this.serviceWorkerRegistration = null;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    }

    // Check if Push notifications are supported
    isSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window;
    }

    // Initialize Sanctum
    async initializeSanctum() {
        try {
            // Get the CSRF cookie
            const response = await fetch('/sanctum/csrf-cookie', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to initialize Sanctum');
            }

            // Get the CSRF token from the cookie
            const cookies = document.cookie.split(';');
            for (const cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === 'XSRF-TOKEN') {
                    this.csrfToken = decodeURIComponent(value);
                    break;
                }
            }

            // If we couldn't find the token in cookies, try the meta tag
            if (!this.csrfToken) {
                this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            }

            if (!this.csrfToken) {
                throw new Error('CSRF token not found');
            }
        } catch (error) {
            // Silent error in production
            throw error;
        }
    }

    // Register the service worker
    async registerServiceWorker() {
        if (!this.isSupported()) {
            // Browser doesn't support push notifications
            return false;
        }

        try {
            this.serviceWorkerRegistration = await navigator.serviceWorker.register('/service-worker.js');
            return this.serviceWorkerRegistration;
        } catch (error) {
            // Service worker registration failed
            return false;
        }
    }

    // Subscribe to push notifications
    async subscribe(publicVapidKey) {
        if (!this.serviceWorkerRegistration) {
            await this.registerServiceWorker();
        }

        if (!this.serviceWorkerRegistration) {
            return false;
        }

        try {
            // Initialize Sanctum before making API calls
            await this.initializeSanctum();

            const subscription = await this.serviceWorkerRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
            });

            // Send the subscription to the server
            await this.sendSubscriptionToServer(subscription);

            return subscription;
        } catch (error) {
            // Could not subscribe to push notifications
            return false;
        }
    }

    // Send subscription info to the server
    async sendSubscriptionToServer(subscription) {
        try {
            // Make sure we have a CSRF token
            if (!this.csrfToken) {
                await this.initializeSanctum();
            }

            const response = await fetch('/api/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include',
                body: JSON.stringify({
                    subscription: {
                        endpoint: subscription.endpoint,
                        keys: {
                            p256dh: subscription.getKey('p256dh') ? btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))) : null,
                            auth: subscription.getKey('auth') ? btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))) : null,
                        }
                    }
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to store subscription on server');
            }

            const result = await response.json();

            // If the subscription already exists, we can still consider it a success
            if (result.message === 'Push subscription already exists') {
                return result;
            }

            return result;
        } catch (error) {
            // Error saving subscription
            throw error;
        }
    }

    // Unsubscribe from push notifications
    async unsubscribe() {
        if (!this.serviceWorkerRegistration) {
            await this.registerServiceWorker();
        }

        try {
            const subscription = await this.serviceWorkerRegistration.pushManager.getSubscription();

            if (subscription) {
                // Notify the server about unsubscription
                await this.removeSubscriptionFromServer(subscription);

                // Unsubscribe from the browser
                await subscription.unsubscribe();
                return true;
            }

            return false;
        } catch (error) {
            // Error unsubscribing
            return false;
        }
    }

    // Remove subscription from server
    async removeSubscriptionFromServer(subscription) {
        try {
            const response = await fetch('/api/push-subscriptions', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include',
                body: JSON.stringify({
                    subscription: subscription
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to remove subscription from server');
            }

            return await response.json();
        } catch (error) {
            // Error removing subscription
            throw error;
        }
    }

    // Get the current subscription if available
    async getSubscription() {
        if (!this.serviceWorkerRegistration) {
            await this.registerServiceWorker();
        }

        if (!this.serviceWorkerRegistration) {
            return null;
        }

        return await this.serviceWorkerRegistration.pushManager.getSubscription();
    }
}

// Export as a singleton instance
const pushNotifications = new PushNotifications();
export default pushNotifications;
