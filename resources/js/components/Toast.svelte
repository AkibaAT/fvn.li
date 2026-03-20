<script lang="ts" module>
    interface Toast {
        id: string;
        message: string;
        type: 'success' | 'error' | 'info' | 'warning';
    }

    // Global notification manager - singleton pattern
    class NotificationManager {
        private static instance: NotificationManager;
        private listeners: Set<(notifications: Toast[]) => void> = new Set();
        private notifications: Toast[] = [];

        static getInstance(): NotificationManager {
            if (!NotificationManager.instance) {
                NotificationManager.instance = new NotificationManager();
            }
            return NotificationManager.instance;
        }

        subscribe(listener: (notifications: Toast[]) => void): () => void {
            this.listeners.add(listener);
            listener(this.notifications);
            return () => {
                this.listeners.delete(listener);
            };
        }

        show(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
            const id = Math.random().toString(36).substr(2, 9);
            const newNotification: Toast = { id, message, type };
            this.notifications.push(newNotification);
            this.notifyListeners();
            setTimeout(() => {
                this.remove(id);
            }, 5000);
        }

        remove(id: string) {
            this.notifications = this.notifications.filter((n) => n.id !== id);
            this.notifyListeners();
        }

        private notifyListeners() {
            this.listeners.forEach((listener) => listener([...this.notifications]));
        }
    }

    const notificationManager = NotificationManager.getInstance();

    export function notify(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
        notificationManager.show(message, type);
    }

    export function useNotifications() {
        return notificationManager;
    }
</script>

<script lang="ts">
    let notifications = $state<Toast[]>([]);

    $effect(() => {
        return notificationManager.subscribe((n) => {
            notifications = n;
        });
    });

    const getNotificationClasses = (type: 'success' | 'error' | 'info' | 'warning') => {
        switch (type) {
            case 'success':
                return 'border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20 text-green-900 dark:text-green-200';
            case 'error':
                return 'border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 text-red-900 dark:text-red-200';
            case 'warning':
                return 'border-l-4 border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-900 dark:text-yellow-200';
            case 'info':
            default:
                return 'border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-900 dark:text-blue-200';
        }
    };

    const getTextClasses = (type: string) => {
        switch (type) {
            case 'success':
                return 'text-green-800 dark:text-green-200';
            case 'error':
                return 'text-red-800 dark:text-red-200';
            case 'warning':
                return 'text-yellow-800 dark:text-yellow-200';
            default:
                return 'text-blue-800 dark:text-blue-200';
        }
    };
</script>

<div class="fixed right-4 bottom-4 z-50 space-y-2">
    {#each notifications as notification (notification.id)}
        <div
            class="ring-opacity-5 ring-opacity-5 pointer-events-auto w-96 max-w-sm overflow-hidden rounded-lg shadow-lg ring-1 ring-black {getNotificationClasses(
                notification.type,
            )}"
            role="alert"
            aria-label="{notification.type} notification: {notification.message}"
        >
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        {#if notification.type === 'success'}
                            <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        {:else if notification.type === 'error'}
                            <svg class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        {:else if notification.type === 'warning'}
                            <svg class="h-6 w-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
                                />
                            </svg>
                        {:else}
                            <svg class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        {/if}
                    </div>
                    <div class="ml-3 flex-1 pt-0.5">
                        <p class="text-sm font-medium {getTextClasses(notification.type)}">
                            {notification.message}
                        </p>
                    </div>
                    <div class="ml-4 flex flex-shrink-0">
                        <button
                            onclick={() => notificationManager.remove(notification.id)}
                            class="inline-flex rounded-md bg-white text-gray-400 hover:text-gray-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none dark:bg-gray-800"
                            aria-label="Close {notification.type} notification"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    {/each}
</div>
