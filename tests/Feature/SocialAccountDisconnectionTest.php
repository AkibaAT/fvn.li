<?php

declare(strict_types=1);

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
