<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Social Account Disconnection', function () {
    test('disconnects social account successfully', function () {
        $user = User::factory()->create();

        // Create two social accounts
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

        // Assert account was deleted
        expect($user->socialAccounts()->count())->toBe(1)
            ->and($user->socialAccounts()->where('provider_name', 'discord')->exists())->toBeFalse()
            ->and($user->socialAccounts()->where('provider_name', 'itchio')->exists())->toBeTrue();
    });

    test('prevents disconnecting last social account', function () {
        $user = User::factory()->create();

        // Create only one social account
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'discord',
            'provider_id' => 'discord123',
        ]);

        expect($user->socialAccounts()->count())->toBe(1);

        // Try to disconnect the only account
        $response = $this->actingAs($user)
            ->delete(route('user.disconnect', ['provider' => 'discord']));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Cannot disconnect your last social account. Delete your account instead if you wish to completely disconnect.');

        // Assert account was NOT deleted
        expect($user->socialAccounts()->count())->toBe(1)
            ->and($user->socialAccounts()->where('provider_name', 'discord')->exists())->toBeTrue();
    });

    test('returns JSON response for AJAX requests', function () {
        $user = User::factory()->create();

        // Create two social accounts
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

        // Assert account was deleted
        expect($user->socialAccounts()->count())->toBe(1);
    });

    test('returns redirect for regular requests', function () {
        $user = User::factory()->create();

        // Create two social accounts
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

        // Make regular request (not AJAX)
        $response = $this->actingAs($user)
            ->delete(route('user.disconnect', ['provider' => 'discord']));

        $response->assertRedirect(route('dashboard'));
    });

    test('handles disconnecting non-existent provider', function () {
        $user = User::factory()->create();

        // Create one social account
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'discord',
            'provider_id' => 'discord123',
        ]);

        // Try to disconnect a provider that doesn't exist
        $response = $this->actingAs($user)
            ->delete(route('user.disconnect', ['provider' => 'github']));

        // Should succeed (no error) but not delete anything
        $response->assertRedirect(route('dashboard'));

        // Assert original account still exists
        expect($user->socialAccounts()->count())->toBe(1)
            ->and($user->socialAccounts()->where('provider_name', 'discord')->exists())->toBeTrue();
    });

    test('handles disconnecting when user has multiple accounts', function () {
        $user = User::factory()->create();

        // Create three social accounts
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

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'telegram',
            'provider_id' => 'telegram789',
        ]);

        expect($user->socialAccounts()->count())->toBe(3);

        // Disconnect one account
        $response = $this->actingAs($user)
            ->delete(route('user.disconnect', ['provider' => 'itchio']));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        // Assert only the specified account was deleted
        expect($user->socialAccounts()->count())->toBe(2)
            ->and($user->socialAccounts()->where('provider_name', 'discord')->exists())->toBeTrue()
            ->and($user->socialAccounts()->where('provider_name', 'itchio')->exists())->toBeFalse()
            ->and($user->socialAccounts()->where('provider_name', 'telegram')->exists())->toBeTrue();
    });

    test('requires authentication', function () {
        $response = $this->delete(route('user.disconnect', ['provider' => 'discord']));

        $response->assertRedirect(route('login'));
    });
});

