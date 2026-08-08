<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\WebPushConfigurationException;
use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class NotificationService
{
    public function assertConfigured(): void
    {
        foreach (['subject', 'public_key', 'private_key'] as $key) {
            if (blank(config("webpush.vapid.{$key}"))) {
                throw new WebPushConfigurationException("Missing VAPID {$key}");
            }
        }
    }

    /**
     * @return array{sent: int, failed: int, pruned: int, errors: list<string>}
     */
    public function sendPushNotifications(Collection $subscriptions, array $payload): array
    {
        $this->assertConfigured();

        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'pruned' => 0, 'errors' => ['no_push_subscriptions']];
        }

        $webPush = $this->createWebPush();

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => [
                    'p256dh' => $subscription->p256dh,
                    'auth' => $subscription->auth,
                ],
            ]), json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $result = ['sent' => 0, 'failed' => 0, 'pruned' => 0, 'errors' => []];

        try {
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $result['sent']++;
                    PushSubscription::where('endpoint', $report->getEndpoint())->first()?->markVerified();

                    continue;
                }

                $reason = $report->getReason();
                $result['failed']++;
                $result['errors'][] = $reason;

                Log::warning('Push notification failed', [
                    'endpoint' => $report->getRequest()->getUri(),
                    'reason' => $reason,
                ]);

                $endpoint = $report->getEndpoint();
                if ($report->isSubscriptionExpired()) {
                    $result['pruned'] += PushSubscription::where('endpoint', $endpoint)->delete();
                } else {
                    $storedSubscription = PushSubscription::where('endpoint', $endpoint)->first();
                    $status = $report->getResponse()?->getStatusCode();
                    in_array($status, [401, 403], true)
                        ? $storedSubscription?->markInvalid($reason)
                        : $storedSubscription?->recordFailure($reason);
                }
            }
        } catch (Throwable $exception) {
            Log::error('Error sending push notifications', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $result['failed'] += max(1, $subscriptions->count() - $result['sent']);
            $result['errors'][] = $exception->getMessage();
        }

        $result['errors'] = array_values(array_unique($result['errors']));

        return $result;
    }

    protected function createWebPush(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]);
    }
}
