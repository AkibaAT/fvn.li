<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\UserNotificationPreferences;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class NotificationSettings extends Component
{
    public bool $browserNotificationsEnabled = false;
    public bool $discordNotificationsEnabled = false;
    public string $notificationDigest = 'asap';

    /**
     * Initialize component properties.
     */
    public function mount(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $preferences = $user->notificationPreferences;

        if (! $preferences) {
            // Create default preferences if they don't exist
            $preferences = UserNotificationPreferences::create([
                'user_id' => $user->id,
                'browser_notifications_enabled' => false,
                'discord_notifications_enabled' => false,
                'notification_digest' => 'asap',
            ]);
        }

        $this->browserNotificationsEnabled = (bool) $preferences->browser_notifications_enabled;
        $this->discordNotificationsEnabled = (bool) $preferences->discord_notifications_enabled;
        $this->notificationDigest = $preferences->notification_digest;
    }

    /**
     * Update the user's notification preferences.
     */
    public function updateNotificationPreferences(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->addError('auth', 'You must be logged in to update notification preferences.');

            return;
        }

        Log::info('Updating notification preferences from Livewire', [
            'user_id' => $user->id,
            'browser_notifications' => $this->browserNotificationsEnabled,
            'discord_notifications' => $this->discordNotificationsEnabled,
            'notification_digest' => $this->notificationDigest,
        ]);

        // Create or update preferences
        $user->notificationPreferences()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'browser_notifications_enabled' => $this->browserNotificationsEnabled,
                'discord_notifications_enabled' => $this->discordNotificationsEnabled,
                'notification_digest' => $this->notificationDigest,
            ]
        );

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Notification preferences updated successfully.',
        ]);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('users.dashboard.notification-settings', [
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }
}
