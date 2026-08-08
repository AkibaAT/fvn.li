<?php

declare(strict_types=1);

use App\Models\PushSubscription;
use App\Models\User;

function pushSubscriptionPayload(string $endpoint = 'https://push.example/subscription'): array
{
    return [
        'subscription' => [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'p256dh-key',
                'auth' => 'auth-key',
            ],
        ],
    ];
}

it('requires authentication and validates push subscription storage', function () {
    $this->postJson(route('browser-api.push-subscriptions.store'), pushSubscriptionPayload())
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('browser-api.push-subscriptions.store'), ['subscription' => ['endpoint' => 'missing-keys']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subscription.keys']);
});

it('creates reuses and transfers push subscriptions by endpoint', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('browser-api.push-subscriptions.store'), pushSubscriptionPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Push subscription saved successfully');

    $subscription = PushSubscription::query()->first();
    expect($subscription->user_id)->toBe($user->id)
        ->and($subscription->subscription_data['endpoint'])->toBe('https://push.example/subscription');

    $this->actingAs($user)
        ->postJson(route('browser-api.push-subscriptions.store'), pushSubscriptionPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Push subscription already exists')
        ->assertJsonPath('id', $subscription->id);

    $this->actingAs($otherUser)
        ->postJson(route('browser-api.push-subscriptions.store'), pushSubscriptionPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Push subscription updated successfully')
        ->assertJsonPath('id', $subscription->id);

    expect($subscription->fresh()->user_id)->toBe($otherUser->id);
});

it('verifies and deletes the current users push subscription', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => 'https://push.example/current',
        'p256dh' => 'key',
        'auth' => 'auth',
        'subscription_data' => ['endpoint' => 'https://push.example/current'],
    ]);
    PushSubscription::create([
        'user_id' => $otherUser->id,
        'endpoint' => 'https://push.example/other',
        'p256dh' => 'key',
        'auth' => 'auth',
        'subscription_data' => ['endpoint' => 'https://push.example/other'],
    ]);

    $this->actingAs($user)
        ->postJson(route('browser-api.push-subscriptions.verify'), ['endpoint' => 'https://push.example/current'])
        ->assertOk()
        ->assertJsonPath('exists', true);

    $this->actingAs($user)
        ->postJson(route('browser-api.push-subscriptions.verify'), ['endpoint' => 'https://push.example/other'])
        ->assertOk()
        ->assertJsonPath('exists', false);

    PushSubscription::where('endpoint', 'https://push.example/current')->update(['delivery_status' => PushSubscription::STATUS_INVALID]);
    $this->actingAs($user)
        ->postJson(route('browser-api.push-subscriptions.verify'), ['endpoint' => 'https://push.example/current'])
        ->assertOk()
        ->assertJsonPath('exists', false);

    $this->actingAs($user)
        ->deleteJson(route('browser-api.push-subscriptions.destroy'), pushSubscriptionPayload('https://push.example/current'))
        ->assertOk()
        ->assertJsonPath('message', 'Push subscription removed successfully');

    $this->actingAs($user)
        ->deleteJson(route('browser-api.push-subscriptions.destroy'), pushSubscriptionPayload('https://push.example/current'))
        ->assertNotFound()
        ->assertJsonPath('message', 'Push subscription not found');
});

it('only reactivates a rejected subscription after an explicit setup action', function () {
    $user = User::factory()->create();
    $subscription = PushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => 'https://push.example/subscription',
        'p256dh' => 'p256dh-key',
        'auth' => 'auth-key',
        'subscription_data' => [],
        'delivery_status' => PushSubscription::STATUS_INVALID,
        'delivery_last_error' => 'VAPID credentials rejected',
    ]);

    $this->actingAs($user)->postJson(route('browser-api.push-subscriptions.store'), pushSubscriptionPayload())->assertOk();
    expect($subscription->fresh()->delivery_status)->toBe(PushSubscription::STATUS_INVALID);

    $this->actingAs($user)->postJson(route('browser-api.push-subscriptions.store'), pushSubscriptionPayload() + ['reactivate' => true])->assertOk();
    expect($subscription->fresh()->delivery_status)->toBe(PushSubscription::STATUS_UNKNOWN)
        ->and($subscription->fresh()->delivery_last_error)->toBeNull();
});
