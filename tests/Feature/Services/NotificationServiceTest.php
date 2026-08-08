<?php

declare(strict_types=1);

use App\Exceptions\WebPushConfigurationException;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\NotificationService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;

uses(RefreshDatabase::class);

function configuredWebPush(): void
{
    config([
        'webpush.vapid.subject' => 'mailto:ops@example.test',
        'webpush.vapid.public_key' => 'public',
        'webpush.vapid.private_key' => 'private',
    ]);
}

it('throws a dedicated configuration error before attempting delivery', function () {
    config(['webpush.vapid.private_key' => null]);

    expect(fn () => app(NotificationService::class)->assertConfigured())
        ->toThrow(WebPushConfigurationException::class, 'Missing VAPID private_key');
});

it('returns a structured no-subscription result', function () {
    configuredWebPush();

    expect(app(NotificationService::class)->sendPushNotifications(collect(), ['title' => 'Test']))->toBe([
        'sent' => 0,
        'failed' => 0,
        'pruned' => 0,
        'errors' => ['no_push_subscriptions'],
    ]);
});

it('reports failures and prunes expired push endpoints', function () {
    configuredWebPush();
    $user = User::factory()->create();
    $subscription = PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => 'https://push.example/expired',
        'p256dh' => 'key',
        'auth' => 'auth',
        'subscription_data' => [],
    ]);
    $report = new MessageSentReport(
        new Request('POST', $subscription->endpoint),
        new Response(410),
        false,
        'Subscription expired',
    );
    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->once();
    $webPush->shouldReceive('flush')->once()->andReturn((function () use ($report) {
        yield $report;
    })());
    $service = new class($webPush) extends NotificationService
    {
        public function __construct(private readonly WebPush $webPush) {}

        protected function createWebPush(): WebPush
        {
            return $this->webPush;
        }
    };

    expect($service->sendPushNotifications(collect([$subscription]), ['title' => 'Test']))->toBe([
        'sent' => 0,
        'failed' => 1,
        'pruned' => 1,
        'errors' => ['Subscription expired'],
    ])->and(PushSubscription::whereKey($subscription->id)->exists())->toBeFalse();
});

it('verifies subscriptions after a successful delivery', function () {
    configuredWebPush();
    $subscription = PushSubscription::create([
        'user_id' => User::factory()->create()->id,
        'endpoint' => 'https://push.example/working',
        'p256dh' => 'key',
        'auth' => 'auth',
        'subscription_data' => [],
    ]);
    $report = new MessageSentReport(new Request('POST', $subscription->endpoint), new Response(201));
    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->once();
    $webPush->shouldReceive('flush')->once()->andReturn((function () use ($report) {
        yield $report;
    })());
    $service = new class($webPush) extends NotificationService
    {
        public function __construct(private readonly WebPush $webPush) {}

        protected function createWebPush(): WebPush
        {
            return $this->webPush;
        }
    };

    expect($service->sendPushNotifications(collect([$subscription]), ['title' => 'Test'])['sent'])->toBe(1)
        ->and($subscription->fresh()->delivery_status)->toBe(PushSubscription::STATUS_VERIFIED)
        ->and($subscription->fresh()->delivery_verified_at)->not->toBeNull();
});

it('invalidates endpoints rejected for the current VAPID identity', function () {
    configuredWebPush();
    $subscription = PushSubscription::create([
        'user_id' => User::factory()->create()->id,
        'endpoint' => 'https://push.example/wrong-vapid',
        'p256dh' => 'key',
        'auth' => 'auth',
        'subscription_data' => [],
    ]);
    $report = new MessageSentReport(new Request('POST', $subscription->endpoint), new Response(403), false, 'VAPID credentials rejected');
    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->once();
    $webPush->shouldReceive('flush')->once()->andReturn((function () use ($report) {
        yield $report;
    })());
    $service = new class($webPush) extends NotificationService
    {
        public function __construct(private readonly WebPush $webPush) {}

        protected function createWebPush(): WebPush
        {
            return $this->webPush;
        }
    };

    $service->sendPushNotifications(collect([$subscription]), ['title' => 'Test']);

    expect($subscription->fresh()->delivery_status)->toBe(PushSubscription::STATUS_INVALID)
        ->and($subscription->fresh()->delivery_last_error)->toBe('VAPID credentials rejected');
});
