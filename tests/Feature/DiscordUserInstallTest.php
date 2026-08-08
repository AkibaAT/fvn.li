<?php

declare(strict_types=1);

use App\Models\NotificationQueue;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.discord.client_id' => '123456789',
        'services.discord.client_secret' => 'secret',
    ]);
});

function linkedDiscordUser(string $discordId = '4242424242'): User
{
    $user = User::factory()->create();
    SocialAccount::factory()->for($user)->discord()->create(['provider_id' => $discordId]);
    $user->notificationPreferences()->create([
        'browser_notifications_enabled' => false,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'asap',
    ]);

    return $user;
}

it('sends a linked user to Discord with the user-install context', function () {
    $user = linkedDiscordUser();

    $response = $this->actingAs($user)->get(route('dashboard.discord.user-install'));

    $response->assertRedirectContains('discord.com/oauth2/authorize');
    $response->assertRedirectContains('integration_type=1');
});

it('refuses the install redirect when no Discord account is linked', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.discord.user-install'))
        ->assertNotFound();
});

it('records the install when the authorizing account matches the linked one', function () {
    $user = linkedDiscordUser('999000111');
    Http::fake([
        'discord.com/api/oauth2/token' => Http::response(['access_token' => 'token']),
        'discord.com/api/oauth2/@me' => Http::response(['user' => ['id' => '999000111']]),
    ]);

    $this->actingAs($user)->get(route('dashboard.discord.user-install'));
    $state = session('discord_user_install')['state'];

    $this->actingAs($user)
        ->get(route('dashboard.discord.user-install.callback', ['code' => 'abc', 'state' => $state]))
        ->assertRedirect(route('dashboard'));

    expect($user->notificationPreferences()->first()->discord_user_installed_at)->not->toBeNull();
});

it('rejects an install authorized by a different Discord account', function () {
    $user = linkedDiscordUser('111111111');
    Http::fake([
        'discord.com/api/oauth2/token' => Http::response(['access_token' => 'token']),
        'discord.com/api/oauth2/@me' => Http::response(['user' => ['id' => '222222222']]),
    ]);

    $this->actingAs($user)->get(route('dashboard.discord.user-install'));
    $state = session('discord_user_install')['state'];

    $this->actingAs($user)
        ->get(route('dashboard.discord.user-install.callback', ['code' => 'abc', 'state' => $state]))
        ->assertSessionHas('error');

    expect($user->notificationPreferences()->first()->discord_user_installed_at)->toBeNull();
});

it('rejects a callback whose state does not match', function () {
    $user = linkedDiscordUser();
    $this->actingAs($user)->get(route('dashboard.discord.user-install'));

    $this->actingAs($user)
        ->get(route('dashboard.discord.user-install.callback', ['code' => 'abc', 'state' => 'forged']))
        ->assertSessionHas('error');

    expect($user->notificationPreferences()->first()->discord_user_installed_at)->toBeNull();
});

it('rejects webhook events that are not signed by Discord', function () {
    config(['services.discord.public_key' => sodium_bin2hex(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()))]);

    $this->postJson(route('api.discord.webhook-events'), ['type' => 0])->assertUnauthorized();
});

it('acknowledges a signed ping', function () {
    $keypair = sodium_crypto_sign_keypair();
    config(['services.discord.public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair))]);

    $body = json_encode(['type' => 0]);
    $this->call('POST', route('api.discord.webhook-events'), [], [], [], discordSignature($keypair, $body), $body)
        ->assertNoContent();
});

it('records an install and an uninstall from signed webhook events', function () {
    $keypair = sodium_crypto_sign_keypair();
    config(['services.discord.public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair))]);
    $user = linkedDiscordUser('550055005');

    $authorized = [
        'type' => 1,
        'event' => [
            'type' => 'APPLICATION_AUTHORIZED',
            'data' => ['integration_type' => 1, 'user' => ['id' => '550055005']],
        ],
    ];
    $authorizedBody = json_encode($authorized);
    $this->call('POST', route('api.discord.webhook-events'), [], [], [], discordSignature($keypair, $authorizedBody), $authorizedBody)
        ->assertNoContent();

    expect($user->notificationPreferences()->first()->discord_user_installed_at)->not->toBeNull();

    NotificationQueue::create([
        'user_id' => $user->id, 'game_id' => null, 'game_version_id' => null,
        'channel' => 'discord', 'status' => 'pending', 'scheduled_at' => now(),
        'payload' => ['type' => 'test'],
    ]);

    $deauthorized = [
        'type' => 1,
        'event' => ['type' => 'APPLICATION_DEAUTHORIZED', 'data' => ['user' => ['id' => '550055005']]],
    ];
    $deauthorizedBody = json_encode($deauthorized);
    $this->call('POST', route('api.discord.webhook-events'), [], [], [], discordSignature($keypair, $deauthorizedBody), $deauthorizedBody)
        ->assertNoContent();

    $preferences = $user->notificationPreferences()->first();

    expect($preferences->discord_user_installed_at)->toBeNull()
        ->and($preferences->discord_dm_status)->toBe('undeliverable')
        ->and($preferences->discord_dm_status_reason)->toBe('not_authorized')
        ->and(NotificationQueue::where('user_id', $user->id)->first()->status)->toBe('failed');
});

/**
 * Signed webhook requests are sent through call() with a raw body, which reads
 * headers from server variables rather than withHeaders().
 */
function discordSignature(string $keypair, string $body): array
{
    $timestamp = (string) now()->timestamp;

    return [
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
        'HTTP_X_SIGNATURE_ED25519' => sodium_bin2hex(
            sodium_crypto_sign_detached($timestamp.$body, sodium_crypto_sign_secretkey($keypair)),
        ),
        'CONTENT_TYPE' => 'application/json',
    ];
}
