<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a scoped Discord bot API token', function () {
    $user = User::factory()->create(['email' => 'bot@example.com']);

    $this->artisan('discord:issue-api-token', ['email' => $user->email])
        ->expectsOutput('Discord bot API token created.')
        ->expectsOutput('Abilities: discord-bot, discord-notifications')
        ->assertSuccessful();

    $token = $user->tokens()->sole();

    expect($token->name)->toBe('fvn-discord-bot')
        ->and($token->abilities)->toBe(['discord-bot', 'discord-notifications']);
});

it('can replace a Discord bot API token by name', function () {
    $user = User::factory()->create(['email' => 'bot@example.com']);
    $user->createToken('production-bot', ['profile']);

    $this->artisan('discord:issue-api-token', [
        'email' => $user->email,
        '--name' => 'production-bot',
        '--replace' => true,
    ])->assertSuccessful();

    expect($user->tokens()->where('name', 'production-bot')->count())->toBe(1)
        ->and($user->tokens()->where('name', 'production-bot')->sole()->abilities)
        ->toBe(['discord-bot', 'discord-notifications']);
});

it('grants the Discord admin ability to an admin-owned service token', function () {
    $user = User::factory()->create(['is_admin' => true]);

    $this->artisan('discord:issue-api-token', ['email' => $user->email])
        ->expectsOutput('Abilities: discord-bot, discord-notifications, discord-admin')
        ->assertSuccessful();

    expect($user->tokens()->sole()->abilities)->toBe(['discord-bot', 'discord-notifications', 'discord-admin']);
});

it('grants the Discord admin ability to existing admin service tokens but not to user API tokens', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $service = $admin->createToken('fvn-discord-bot', ['discord-bot', 'discord-notifications'])->accessToken;
    $userToken = $admin->createToken('script', ['user-api', 'catalog:read'])->accessToken;

    $migration = require database_path('migrations/2026_10_01_000002_grant_discord_admin_ability_to_existing_bot_tokens.php');
    $migration->up();

    expect($service->fresh()->abilities)->toContain('discord-admin')
        ->and($userToken->fresh()->abilities)->not->toContain('discord-admin');
});
