<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AdditionRequestSubmitted;
use App\Models\NotificationQueue;
use Exception;
use Illuminate\Support\Facades\Log;

class SendAdditionRequestDiscordNotification
{
    /**
     * Handle the event.
     */
    public function handle(AdditionRequestSubmitted $event): void
    {
        // Only send notifications for new requests (not when users join existing requests)
        if (! $event->isNewRequest) {
            return;
        }

        try {
            // Create a notification queue entry for Discord
            NotificationQueue::create([
                'type' => 'addition_request',
                'data' => [
                    'addition_request_id' => $event->additionRequest->id,
                    'url' => $event->additionRequest->itch_url,
                    'user_id' => $event->user->id,
                    'user_name' => $event->user->name,
                    'submitted_at' => $event->additionRequest->created_at->toISOString(),
                    'admin_panel_url' => config('app.url') . '/admin/addition-requests',
                ],
                'priority' => 'normal',
                'scheduled_for' => now(),
            ]);

            Log::info('Discord notification queued for addition request', [
                'addition_request_id' => $event->additionRequest->id,
                'user_id' => $event->user->id,
                'url' => $event->additionRequest->itch_url,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to queue Discord notification for addition request', [
                'addition_request_id' => $event->additionRequest->id,
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
