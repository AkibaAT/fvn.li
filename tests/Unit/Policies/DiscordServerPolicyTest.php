<?php

declare(strict_types=1);

use App\Models\DiscordServer;
use App\Models\DiscordServerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::unsetEventDispatcher();

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->server = DiscordServer::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    DiscordServerMember::create([
        'discord_server_id' => $this->server->id,
        'user_id' => $this->admin->id,
        'discord_user_id' => (string) fake()->randomNumber(8),
        'discord_username' => $this->admin->name,
        'is_admin' => true,
    ]);
});

describe('DiscordServerPolicy', function () {
    test('owner can view server', function () {
        expect($this->owner->can('view', $this->server))->toBeTrue();
    });

    test('admin member can view server', function () {
        expect($this->admin->can('view', $this->server))->toBeTrue();
    });

    test('stranger cannot view server', function () {
        expect($this->stranger->can('view', $this->server))->toBeFalse();
    });

    test('owner can update server', function () {
        expect($this->owner->can('update', $this->server))->toBeTrue();
    });

    test('admin member can update server', function () {
        expect($this->admin->can('update', $this->server))->toBeTrue();
    });

    test('stranger cannot update server', function () {
        expect($this->stranger->can('update', $this->server))->toBeFalse();
    });

    test('owner can delete server', function () {
        expect($this->owner->can('delete', $this->server))->toBeTrue();
    });

    test('admin member cannot delete server', function () {
        expect($this->admin->can('delete', $this->server))->toBeFalse();
    });

    test('stranger cannot delete server', function () {
        expect($this->stranger->can('delete', $this->server))->toBeFalse();
    });

    test('regular member cannot manage server', function () {
        $regularMember = User::factory()->create();
        DiscordServerMember::create([
            'discord_server_id' => $this->server->id,
            'user_id' => $regularMember->id,
            'discord_user_id' => (string) fake()->randomNumber(8),
            'discord_username' => $regularMember->name,
            'is_admin' => false,
        ]);

        expect($regularMember->can('view', $this->server))->toBeFalse()
            ->and($regularMember->can('update', $this->server))->toBeFalse();
    });
});
