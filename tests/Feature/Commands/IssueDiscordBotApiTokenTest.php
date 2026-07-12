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
