@vite(['resources/js/push-notifications.js'])

<div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Notification Settings</h2>

    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <label for="browserNotifications" class="flex-grow font-medium text-gray-700 dark:text-gray-300">
                Browser Push Notifications
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Receive notifications directly in your browser when games are updated.
                </p>
            </label>
            <div class="flex items-center" x-data="browserNotifications('{{ $vapidPublicKey }}')">
                <div class="relative group">
                    <button
                        type="button"
                        id="browserPermissionBtn"
                        class="mr-3 px-3 py-2 text-white text-sm font-medium rounded-md"
                        x-text="buttonText"
                        x-bind:disabled="buttonDisabled"
                        x-bind:class="buttonClass"
                        @click="requestPermission"
                    >
                        Request Permission
                    </button>
                    <div x-show="permissionGranted" class="absolute left-0 top-full mt-2 w-72 px-4 py-3 bg-gray-800 dark:bg-gray-700 text-white text-sm rounded-md shadow-lg hidden group-hover:block z-10">
                        To revoke notification permissions, you'll need to:
                        <ol class="mt-2 ml-4 list-decimal text-xs space-y-1">
                            <li>Click the lock/info icon in your browser's address bar</li>
                            <li>Find "Notifications" in the site permissions</li>
                            <li>Change the setting to "Block" or "Ask"</li>
                        </ol>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        id="browserNotifications"
                        wire:model="browserNotificationsEnabled"
                        class="sr-only peer"
                        x-bind:disabled="!permissionGranted"
                        x-bind:checked="browserNotificationsEnabled"
                        @change="handleToggleChange"
                    >
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:translate-x-[-100%] peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                </label>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <label for="discordNotifications" class="flex-grow font-medium text-gray-700 dark:text-gray-300">
                Discord Notifications
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Receive notifications via Discord when games are updated.
                </p>
            </label>
            <div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        id="discordNotifications"
                        wire:model.live="discordNotificationsEnabled"
                        wire:change="updateNotificationPreferences"
                        class="sr-only peer"
                    >
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:translate-x-[-100%] peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                </label>
            </div>
        </div>

        <div class="mt-6">
            <label class="block font-medium text-gray-700 dark:text-gray-300">
                Notification Frequency
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Choose how often you'd like to receive update notifications.
                </p>
            </label>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <label class="relative flex cursor-pointer rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm focus:outline-none" :class="{ 'border-indigo-500 ring-2 ring-indigo-500': $wire.notificationDigest === 'asap' }">
                    <input type="radio" wire:model.live="notificationDigest" wire:change="updateNotificationPreferences" value="asap" class="sr-only">
                    <div class="flex w-full items-center justify-between">
                        <div class="flex items-center">
                            <div class="text-sm">
                                <p class="font-medium text-gray-900 dark:text-gray-100">As soon as possible</p>
                                <p class="text-gray-500 dark:text-gray-400">Get notified immediately when games are updated</p>
                            </div>
                        </div>
                        <div class="shrink-0 text-indigo-600 dark:text-indigo-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                        </div>
                    </div>
                </label>

                <label class="relative flex cursor-pointer rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm focus:outline-none" :class="{ 'border-indigo-500 ring-2 ring-indigo-500': $wire.notificationDigest === 'daily' }">
                    <input type="radio" wire:model.live="notificationDigest" wire:change="updateNotificationPreferences" value="daily" class="sr-only">
                    <div class="flex w-full items-center justify-between">
                        <div class="flex items-center">
                            <div class="text-sm">
                                <p class="font-medium text-gray-900 dark:text-gray-100">Daily digest</p>
                                <p class="text-gray-500 dark:text-gray-400">Get a summary of all updates once per day</p>
                            </div>
                        </div>
                        <div class="shrink-0 text-indigo-600 dark:text-indigo-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                        </div>
                    </div>
                </label>

                <label class="relative flex cursor-pointer rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm focus:outline-none" :class="{ 'border-indigo-500 ring-2 ring-indigo-500': $wire.notificationDigest === 'weekly' }">
                    <input type="radio" wire:model.live="notificationDigest" wire:change="updateNotificationPreferences" value="weekly" class="sr-only">
                    <div class="flex w-full items-center justify-between">
                        <div class="flex items-center">
                            <div class="text-sm">
                                <p class="font-medium text-gray-900 dark:text-gray-100">Weekly digest</p>
                                <p class="text-gray-500 dark:text-gray-400">Get a summary of all updates once per week</p>
                            </div>
                        </div>
                        <div class="shrink-0 text-indigo-600 dark:text-indigo-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                            </svg>
                        </div>
                    </div>
                </label>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('browserNotifications', (vapidKey) => ({
            vapidKey,
            permissionGranted: false,
            browserNotificationsEnabled: @entangle('browserNotificationsEnabled'),
            buttonText: 'Request Permission',
            buttonDisabled: false,
            buttonClass: 'bg-indigo-600 hover:bg-indigo-700',

            init() {
                this.checkPermissionStatus();
            },

            checkPermissionStatus() {
                if (!('serviceWorker' in navigator && 'PushManager' in window)) {
                    this.buttonText = 'Not Supported';
                    this.buttonDisabled = true;
                    this.buttonClass = 'bg-gray-600 cursor-not-allowed';
                    return;
                }

                const permission = Notification.permission;

                if (permission === 'granted') {
                    this.permissionGranted = true;
                    this.buttonText = 'Permission Granted';
                    this.buttonDisabled = true;
                    this.buttonClass = 'bg-green-600 hover:bg-green-700 cursor-not-allowed';
                } else if (permission === 'denied') {
                    this.buttonText = 'Permission Blocked';
                    this.buttonDisabled = true;
                    this.buttonClass = 'bg-red-600 hover:bg-red-700 cursor-not-allowed';
                }
            },

            async requestPermission() {
                try {
                    const permission = await Notification.requestPermission();

                    if (permission === 'granted') {
                        this.permissionGranted = true;
                        this.buttonText = 'Permission Granted';
                        this.buttonDisabled = true;
                        this.buttonClass = 'bg-green-600 hover:bg-green-700 cursor-not-allowed';

                        // Load and initialize push notifications
                        try {
                            const pushNotifications = window.pushNotifications;
                            if (!pushNotifications) {
                                throw new Error('Push notifications module not loaded');
                            }

                            // First register the service worker
                            const registration = await pushNotifications.registerServiceWorker();
                            if (!registration) {
                                throw new Error('Failed to register service worker');
                            }

                            // Wait a moment for the service worker to become active
                            await new Promise(resolve => setTimeout(resolve, 1000));

                            // Check if we already have a subscription
                            let subscription = await pushNotifications.getSubscription();

                            // If no subscription exists, create a new one
                            if (!subscription) {
                                subscription = await pushNotifications.subscribe(this.vapidKey);
                            }

                            // Enable the checkbox if subscription is successful
                            if (subscription) {
                                this.browserNotificationsEnabled = true;
                                @this.updateNotificationPreferences();
                            } else {
                                throw new Error('Failed to create push subscription');
                            }
                        } catch (error) {
                            console.error('Error setting up push notifications:', error);
                            this.buttonText = 'Error';
                            this.buttonDisabled = false;
                            this.buttonClass = 'bg-red-600 text-white text-sm font-medium rounded-md';
                            this.permissionGranted = false;
                            this.browserNotificationsEnabled = false;
                            @this.updateNotificationPreferences();
                        }
                    } else {
                        this.buttonText = 'Permission Blocked';
                        this.buttonDisabled = true;
                        this.buttonClass = 'bg-red-600 hover:bg-red-700 cursor-not-allowed';
                        this.browserNotificationsEnabled = false;
                        @this.updateNotificationPreferences();
                    }
                } catch (error) {
                    console.error('Error requesting notification permission:', error);
                }
            },

            handleToggleChange(event) {
                if (event.target.checked && !this.permissionGranted) {
                    // If trying to enable but permission not granted, trigger the permission request
                    this.requestPermission();
                } else if (event.target.checked) {
                    // If toggling on and permission is granted, ensure we have a valid subscription
                    this.requestPermission();
                } else if (!event.target.checked) {
                    // If toggling off, update the server state
                    this.browserNotificationsEnabled = false;
                    @this.updateNotificationPreferences();
                }
            }
        }));
    });
</script>