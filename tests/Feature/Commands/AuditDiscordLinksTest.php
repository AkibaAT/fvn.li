<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports invalid Discord snowflakes without changing links in audit mode', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->discord()->for($user)->create(['provider_id' => 'not-a-snowflake']);

    $this->artisan('notifications:audit-discord-links')
        ->expectsOutputToContain('Found 1 invalid Discord link(s)')
        ->assertFailed();

    expect($account->fresh())->not->toBeNull();
});

it('unlinks invalid Discord IDs and marks their DM state while preserving valid IDs', function () {
    $nonnumericUser = User::factory()->create();
    $overflowUser = User::factory()->create();
    $validUser = User::factory()->create();
    SocialAccount::factory()->discord()->for($nonnumericUser)->create(['provider_id' => 'discord-invalid']);
    SocialAccount::factory()->discord()->for($overflowUser)->create(['provider_id' => '9223372036854775808']);
    $valid = SocialAccount::factory()->discord()->for($validUser)->create(['provider_id' => '123456789012345678']);

    $this->artisan('notifications:audit-discord-links --apply')
        ->expectsOutputToContain('Unlinked 2 invalid Discord account(s).')
        ->assertSuccessful();

    expect(SocialAccount::where('provider_name', 'discord')->pluck('id')->all())->toBe([$valid->id])
        ->and($nonnumericUser->notificationPreferences->discord_dm_status)->toBe('undeliverable')
        ->and($nonnumericUser->notificationPreferences->discord_dm_status_reason)->toBe('not_linked')
        ->and($overflowUser->notificationPreferences->discord_dm_status_reason)->toBe('not_linked');
});
