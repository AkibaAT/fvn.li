<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">User Dashboard</h1>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Left Column - User Settings -->
        <div class="lg:col-span-3 space-y-6">
            <!-- Profile Section -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                <div class="p-6">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Profile Information</h2>
                    <div class="flex items-center gap-4">
                        @if ($user->avatar)
                            <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full">
                        @else
                            <div class="w-16 h-16 rounded-full bg-blue-500 text-white flex items-center justify-center text-2xl font-bold">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <div class="text-xl font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                            @if ($user->email)
                                <div class="text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('user.export') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Export My Data</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Notification Settings Section -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                <div class="p-6">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Notification Settings</h2>
                    <form action="{{ route('user.dashboard.notifications.update') }}" method="POST" id="notification-preferences-form">
                        @csrf
                        @method('PUT')

                        <!-- Discord Notifications -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">Discord Notifications</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Receive notifications via Discord DMs</p>
                                </div>
                                <div class="flex items-center">
                                    @if (in_array('discord', $connectedProviders))
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="discord_notifications_enabled" class="sr-only peer"
                                                {{ $user->notificationPreferences?->discord_notifications_enabled ? 'checked' : '' }}>
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                        </label>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Connect Discord to enable</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Browser Notifications -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">Browser Notifications</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Receive notifications in your browser</p>
                                </div>
                                <div class="flex items-center">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="browser_notifications_enabled" class="sr-only peer"
                                            {{ $user->notificationPreferences?->browser_notifications_enabled ? 'checked' : '' }}>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Digest -->
                        <div class="mb-6">
                            <div>
                                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">Notification Frequency</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Choose how often you want to receive notifications</p>
                            </div>
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <input type="radio" name="notification_digest" value="asap" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        {{ $user->notificationPreferences?->notification_digest === 'asap' ? 'checked' : '' }}
                                        {{ !$user->notificationPreferences || !$user->notificationPreferences->notification_digest ? 'checked' : '' }}>
                                    <label class="ml-2 block text-sm font-medium text-gray-900 dark:text-gray-100">
                                        As Soon As Possible
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">Receive notifications immediately when updates are available</span>
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" name="notification_digest" value="daily" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        {{ $user->notificationPreferences?->notification_digest === 'daily' ? 'checked' : '' }}>
                                    <label class="ml-2 block text-sm font-medium text-gray-900 dark:text-gray-100">
                                        Daily Digest
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">Receive one notification per day with all updates</span>
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" name="notification_digest" value="weekly" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        {{ $user->notificationPreferences?->notification_digest === 'weekly' ? 'checked' : '' }}>
                                    <label class="ml-2 block text-sm font-medium text-gray-900 dark:text-gray-100">
                                        Weekly Digest
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">Receive one notification per week with all updates</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column - Account Connections -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Connected Accounts Section -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                <div class="p-6">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Connected Accounts</h2>
                    <div class="grid grid-cols-1 gap-4">
                        @php
                            $providers = [
                                'discord' => [
                                    'icon' => '<i class="icon-discord h-6 w-6 text-indigo-500 text-xl"></i>',
                                    'name' => 'Discord'
                                ],
                                'google' => [
                                    'icon' => '<svg class="h-6 w-6" viewBox="0 0 24 24"><path fill="lightgray" d="M21.35 11.1h-9.17v2.73h6.5c-.33 3.8-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27c3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10c5.35 0 9.25-3.67 9.25-9.09c0-1.15-.15-1.81-.15-1.81Z" /></svg>',
                                    'name' => 'Google'
                                ],
                                'steam' => [
                                    'icon' => '<svg class="h-6 w-6" viewBox="0 0 65 65"><path d="M31.959 64c17.673 0 32-14.327 32-32s-14.327-32-32-32C15.001 0 1.124 13.193.028 29.874c2.074 3.477 2.879 5.628 1.275 11.328C5.259 54.386 17.488 64 31.959 64z" fill="#144b7e"/><path d="M30.31 23.985l.003.158-7.83 11.375c-1.268-.058-2.54.165-3.748.662a8.14 8.14 0 0 0-1.498.8L.042 29.893s-.398 6.546 1.26 11.424l12.156 5.016c.6 2.728 2.48 5.12 5.242 6.27a8.88 8.88 0 0 0 11.603-4.782 8.89 8.89 0 0 0 .684-3.656L42.18 36.16l.275.005c6.705 0 12.155-5.466 12.155-12.18s-5.44-12.16-12.155-12.174c-6.702 0-12.155 5.46-12.155 12.174zm-1.88 23.05c-1.454 3.5-5.466 5.147-8.953 3.694a6.84 6.84 0 0 1-3.524-3.362l3.957 1.64a5.04 5.04 0 0 0 6.591-2.719 5.05 5.05 0 0 0-2.715-6.601l-4.1-1.695c1.578-.6 3.372-.62 5.05.077 1.7.703 3 2.027 3.696 3.72s.692 3.56-.01 5.246M42.466 32.1a8.12 8.12 0 0 1-8.098-8.113a8.12 8.12 0 0 1 8.098-8.111a8.12 8.12 0 0 1 8.1 8.111a8.12 8.12 0 0 1-8.1 8.113m-6.068-8.126a6.09 6.09 0 0 1 6.08-6.095c3.355 0 6.084 2.73 6.084 6.095a6.09 6.09 0 0 1-6.084 6.093a6.09 6.09 0 0 1-6.081-6.093z" fill="#fff"/></svg>',
                                    'name' => 'Steam'
                                ],
                                'telegram' => [
                                    'icon' => '<i class="icon-telegram h-6 w-6 text-blue-500 text-2xl"></i>',
                                    'name' => 'Telegram'
                                ]
                            ];
                        @endphp

                        @foreach($providers as $provider => $config)
                            <div class="p-4 border dark:border-gray-700 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        {!! $config['icon'] !!}
                                        <span class="text-gray-900 dark:text-white">{{ $config['name'] }}</span>
                                    </div>
                                    @if (in_array($provider, $connectedProviders))
                                        <div class="flex items-center gap-2">
                                            @if(isset($socialAccounts[$provider]))
                                                <div class="flex items-center gap-2">
                                                    @if($socialAccounts[$provider]['avatar'])
                                                        <img src="{{ $socialAccounts[$provider]['avatar'] }}" alt="{{ $config['name'] }} avatar" class="w-6 h-6 rounded-full">
                                                    @endif
                                                    @if($socialAccounts[$provider]['display_name'])
                                                        <span class="text-gray-600 dark:text-gray-400">{{ $socialAccounts[$provider]['display_name'] }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                            <span class="text-green-500">Connected</span>
                                            <form action="{{ route('user.disconnect', ['provider' => $provider]) }}" method="POST" class="flex">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ml-2 text-red-500 hover:text-red-600" onclick="return confirm('Are you sure you want to disconnect your {{ $config['name'] }} account?')" title="Unlink {{ $config['name'] }} account">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <form action="{{ route('user.merge', ['provider' => $provider]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-blue-500 hover:text-blue-600" onclick="return confirm('If an account already exists with this {{ $config['name'] }} login, it will be merged into your current account. This action cannot be undone. Continue?')">Connect</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg lg:mt-9">
                <div class="p-6">
                    <h2 class="text-lg font-semibold mb-4 text-red-600 dark:text-red-500">Danger Zone</h2>
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <h3 class="text-red-800 dark:text-red-400 font-medium mb-2">Delete Account</h3>
                        <p class="text-red-700 dark:text-red-300 text-sm mb-4">
                            Once you delete your account, there is no going back. Please be certain.
                        </p>
                        <form action="{{ route('user.delete') }}" method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                                Delete Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('notification-preferences-form');
    const browserToggle = form.querySelector('input[name="browser_notifications_enabled"]');
    const discordToggle = form.querySelector('input[name="discord_notifications_enabled"]');
    const digestRadios = form.querySelectorAll('input[name="notification_digest"]');
    const originalBrowserState = browserToggle.checked;
    const originalDiscordState = discordToggle?.checked;
    const originalDigestValue = form.querySelector('input[name="notification_digest"]:checked')?.value;

    // Helper function to show success messages
    function showSuccessMessage(message) {
        const successMessage = document.createElement('div');
        successMessage.className = 'fixed bottom-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-lg z-50';
        successMessage.innerHTML = `<p>${message}</p>`;
        document.body.appendChild(successMessage);

        // Remove the message after 3 seconds
        setTimeout(() => {
            successMessage.remove();
        }, 3000);
    }

    // Helper function to show error messages
    function showErrorMessage(message) {
        const errorMessage = document.createElement('div');
        errorMessage.className = 'fixed bottom-4 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-lg z-50';
        errorMessage.innerHTML = `<p>${message}</p>`;
        document.body.appendChild(errorMessage);

        // Remove the message after 5 seconds
        setTimeout(() => {
            errorMessage.remove();
        }, 5000);
    }

    // Function to show feedback message
    const showFeedback = (message, isSuccess = true) => {
        if (isSuccess) {
            showSuccessMessage(message);
            return;
        }

        showErrorMessage(message);
    };

    // Handle browser notification toggle
    browserToggle.addEventListener('change', async function(e) {
        if (this.checked && !originalBrowserState) {
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.checked = false;
                    showFeedback('Browser notifications permission denied', false);
                    return;
                }
            } catch (error) {
                console.error('Error requesting notification permission:', error);
                this.checked = false;
                showFeedback('Failed to request notification permission', false);
                return;
            }
        }

        // Save the preference immediately when toggled
        savePreferences();
    });

    // Handle Discord notification toggle
    if (discordToggle) {
        discordToggle.addEventListener('change', function(e) {
            savePreferences();
        });
    }

    // Handle notification digest radio buttons
    digestRadios.forEach(radio => {
        radio.addEventListener('change', function(e) {
            savePreferences();
        });
    });

    // Function to save preferences via AJAX
    const savePreferences = async () => {
        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': formData.get('_token'),
                    'X-HTTP-Method-Override': 'PUT',
                    'Accept': 'application/json'
                },
                body: formData
            });

            if (response.ok) {
                showFeedback('Notification preferences saved successfully');
            } else {
                const errorData = await response.json();
                showFeedback(errorData.message || 'Failed to save preferences', false);
                // Revert toggles to their previous state on error
                browserToggle.checked = originalBrowserState;
                if (discordToggle) {
                    discordToggle.checked = originalDiscordState;
                }
                // Revert digest radio to original value
                const originalRadio = form.querySelector(`input[name="notification_digest"][value="${originalDigestValue}"]`);
                if (originalRadio) {
                    originalRadio.checked = true;
                }
            }
        } catch (error) {
            console.error('Error saving preferences:', error);
            showFeedback('Failed to save preferences', false);
            // Revert toggles to their previous state on error
            browserToggle.checked = originalBrowserState;
            if (discordToggle) {
                discordToggle.checked = originalDiscordState;
            }
            // Revert digest radio to original value
            const originalRadio = form.querySelector(`input[name="notification_digest"][value="${originalDigestValue}"]`);
            if (originalRadio) {
                originalRadio.checked = true;
            }
        }
    };

    // Remove the form submit handler since we're handling changes immediately
    form.addEventListener('submit', function(e) {
        e.preventDefault();
    });
});
</script>
