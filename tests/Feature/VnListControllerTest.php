<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VnList;

test('authenticated user can create a custom vn list', function () {
    $user = User::withoutEvents(fn () => User::factory()->create());

    $response = $this->actingAs($user)->postJson(route('api.vn-lists.store'), [
        'name' => 'Summer Reading',
        'description' => 'VNs to read this summer.',
        'is_public' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'List created successfully.')
        ->assertJsonPath('list.name', 'Summer Reading')
        ->assertJsonPath('list.description', 'VNs to read this summer.')
        ->assertJsonPath('list.type', 'custom')
        ->assertJsonPath('list.is_public', true);

    $this->assertDatabaseHas('vn_lists', [
        'user_id' => $user->id,
        'name' => 'Summer Reading',
        'type' => 'custom',
        'is_default' => false,
        'is_public' => true,
    ]);
});

test('vn list names must be unique for the authenticated user', function () {
    $user = User::withoutEvents(fn () => User::factory()->create());
    VnList::factory()->for($user)->create(['name' => 'Already Exists']);

    $response = $this->actingAs($user)->postJson(route('api.vn-lists.store'), [
        'name' => 'Already Exists',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name'])
        ->assertJsonPath('errors.name.0', 'You already have a list with this name.');
});

test('vn list create endpoint returns json for unauthenticated requests', function () {
    $response = $this->postJson(route('api.vn-lists.store'), [
        'name' => 'Guest List',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});
