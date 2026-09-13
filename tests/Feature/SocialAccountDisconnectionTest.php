<?php

declare(strict_types=1);

use App\Models\DiscordServer;
use App\Models\DiscordServerMember;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Social Account Disconnection', function () {
    test('disconnects social account successfully', function () {
        $user = User::factory()->create();

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'discord',
            'provider_id' => 'discord123',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'itchio',
            'provider_id' => 'itchio456',
        ]);

        expect($user->socialAccounts()->count())->toBe(2);

        // Disconnect one account
        $response = $this->actingAs($user)
            ->delete(route('user.disconnect', ['provider' => 'discord']));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Successfully disconnected Discord account.');

        expect($user->socialAccounts()->count())->toBe(1)
            ->and($user->socialAccounts()->where('provider_name', 'discord')->exists())->toBeFalse()
            ->and($user->socialAccounts()->where('provider_name', 'itchio')->exists())->toBeTrue();
    });

    test('prevents disconnecting last social account', function () {
        $user = User::factory()->create();

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'discord',
            'provider_id' => 'discord123',
        ]);

        expect($user->socialAccounts()->count())->toBe(1);

        $response = $this->actingAs($user)
            ->delete(route('user.disconnect', ['provider' => 'discord']));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Cannot disconnect your last social account. Delete your account instead if you wish to completely disconnect.');

        expect($user->socialAccounts()->count())->toBe(1)
            ->and($user->socialAccounts()->where('provider_name', 'discord')->exists())->toBeTrue();
    });

    test('explains why the last social account cannot be disconnected over JSON', function () {
        $user = User::factory()->create();
        $account = SocialAccount::factory()->create(['user_id' => $user->id, 'provider_name' => 'discord']);

        $this->actingAs($user)->deleteJson(route('user.disconnect', ['provider' => 'discord']))
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Cannot disconnect your last social account. Delete your account instead if you wish to completely disconnect.',
            ]);

        expect($account->fresh())->not->toBeNull();
    });

    test('returns JSON response for AJAX requests', function () {
        $user = User::factory()->create();

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'discord',
            'provider_id' => 'discord123',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'itchio',
            'provider_id' => 'itchio456',
        ]);

        // Make AJAX request
        $response = $this->actingAs($user)
            ->deleteJson(route('user.disconnect', ['provider' => 'discord']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully disconnected Discord account.',
                'provider' => 'discord',
            ]);

        expect($user->socialAccounts()->count())->toBe(1);
    });

    test('handles disconnecting non-existent provider', function () {
        $user = User::factory()->create();

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'discord',
            'provider_id' => 'discord123',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('user.disconnect', ['provider' => 'github']));

        // Should succeed (no error) but not delete anything
        $response->assertRedirect(route('dashboard'));

        expect($user->socialAccounts()->count())->toBe(1)
            ->and($user->socialAccounts()->where('provider_name', 'discord')->exists())->toBeTrue();
    });

    test('requires authentication', function () {
        $response = $this->delete(route('user.disconnect', ['provider' => 'discord']));

        $response->assertRedirect(route('login'));
    });
});

test('disconnecting discord revokes derived server access and user install state', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $discord = SocialAccount::factory()->discord()->for($user)->create();
    SocialAccount::factory()->itchio()->for($user)->create();
    $server = DiscordServer::factory()->create(['owner_user_id' => $user->id]);
    $member = DiscordServerMember::create([
        'discord_server_id' => $server->id, 'user_id' => $user->id, 'discord_user_id' => $discord->provider_id,
        'discord_username' => 'Linked account', 'is_admin' => true,
    ]);
    $preferences = $user->notificationPreferences()->create(['discord_user_installed_at' => now()]);
    $this->actingAs($user)->deleteJson(route('user.disconnect', 'discord'))->assertOk();
    expect($server->fresh()->owner_user_id)->toBeNull()
        ->and($member->fresh()->user_id)->toBeNull()
        ->and($member->fresh()->is_admin)->toBeFalse()
        ->and($preferences->fresh()->discord_user_installed_at)->toBeNull()
        ->and($user->can('update', $server->fresh()))->toBeFalse();
});

test('disconnecting a merged provider cannot remove every sign-in identity', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->itchio()->for($user)->count(2)->create();
    $this->actingAs($user)->deleteJson(route('user.disconnect', 'itchio'))->assertStatus(422);
    expect($user->socialAccounts()->count())->toBe(2);
});
