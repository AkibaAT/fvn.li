<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\PushSubscription;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class NotificationService
{
    /**
     * Send a push notification directly to a user.
     */
    public function sendPushToUser(User $user, Game $game, GameVersion $gameVersion): bool
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return false;
        }

        $payload = [
            'title' => $game->name . ' - New Update Available',
            'body' => 'Version ' . $gameVersion->version . ' is now available.',
            'data' => [
                'url' => route('games.show', $game->slug),
                'game_id' => $game->id,
                'game_version_id' => $gameVersion->id,
                'version' => $gameVersion->version,
            ],
            'icon' => $game->getThumbnailUrl('small'),
        ];

        $success = $this->sendPushNotifications($subscriptions, $payload);

        if ($success) {
            // Record in notification history
            NotificationHistory::create([
                'user_id' => $user->id,
                'game_id' => $game->id,
                'game_version_id' => $gameVersion->id,
                'type' => 'browser',
                'success' => true,
                'meta_data' => [
                    'digest' => false,
                ],
            ]);
        }

        return $success;
    }

    /**
     * Send push notifications to a collection of subscriptions.
     */
    public function sendPushNotifications(Collection $subscriptions, array $payload): bool
    {
        try {
            if ($subscriptions->isEmpty()) {
                return false;
            }

            $auth = [
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject'),
                    'publicKey' => config('webpush.vapid.public_key'),
                    'privateKey' => config('webpush.vapid.private_key'),
                ],
            ];

            $webPush = new WebPush($auth);
            $successCount = 0;

            foreach ($subscriptions as $subscription) {
                $webPushSubscription = Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->p256dh,
                        'auth' => $subscription->auth,
                    ],
                ]);

                $webPush->queueNotification($webPushSubscription, json_encode($payload));
            }

            // Send all notifications
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $successCount++;
                } else {
                    Log::warning('Push notification failed', [
                        'endpoint' => $report->getRequest()->getUri(),
                        'reason' => $report->getReason(),
                    ]);

                    // If endpoint expired or invalid, remove it
                    if ($report->isSubscriptionExpired()) {
                        $endpoint = (string) $report->getRequest()->getUri();
                        PushSubscription::where('endpoint', $endpoint)->delete();
                    }
                }
            }

            return $successCount > 0;
        } catch (Exception $e) {
            Log::error('Error sending push notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send a digest notification to a user.
     */
    public function sendDigestToUser(User $user, Collection $games, string $digestType): bool
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return false;
        }

        $title = 'Game Updates Digest';
        if ($digestType === 'daily') {
            $title = 'Daily Game Updates';
        } elseif ($digestType === 'weekly') {
            $title = 'Weekly Game Updates';
        }

        $body = count($games) . ' games you follow have been updated.';

        $payload = [
            'title' => $title,
            'body' => $body,
            'data' => [
                'url' => route('dashboard'),
                'digest' => true,
                'games' => $games->map(function ($game) {
                    return [
                        'id' => $game['game']->id,
                        'name' => $game['game']->name,
                        'version' => $game['version']->version,
                        'url' => route('games.show', $game['game']->slug),
                    ];
                })->toArray(),
            ],
        ];

        $success = $this->sendPushNotifications($subscriptions, $payload);

        if ($success) {
            // Record in notification history for each game
            foreach ($games as $gameData) {
                NotificationHistory::create([
                    'user_id' => $user->id,
                    'game_id' => $gameData['game']->id,
                    'game_version_id' => $gameData['version']->id,
                    'type' => 'browser',
                    'success' => true,
                    'meta_data' => [
                        'digest' => true,
                        'digest_type' => $digestType,
                    ],
                ]);
            }
        }

        return $success;
    }
}
