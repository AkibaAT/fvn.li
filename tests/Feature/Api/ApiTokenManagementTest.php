<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('keeps the developer guide, API explorer, and specification behind a browser login', function () {
    $this->get('/developers')->assertRedirect('/login');
    $this->get('/developers/swagger')->assertRedirect('/login');
    $this->get('/developers/openapi.yaml')->assertRedirect('/login');
    $this->get('/openapi.yaml')->assertNotFound();

    $this->actingAs(User::factory()->create());
    $this->get('/developers')->assertOk()->assertInertia(fn (Assert $page) => $page->component('developers/index'));
    $this->get('/developers/openapi.yaml')->assertOk()->assertHeader('Content-Type', 'application/yaml');
    $this->get('/developers/swagger')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page->component('developers/swagger')->where('metaTags.noindex', true));
});

it('issues one-time scoped tokens and only extends active tokens within a one-year cap', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $created = $this->postJson('/browser-api/dashboard/api-tokens', [
        'name' => 'My script', 'abilities' => ['catalog:read', 'tags:write'],
    ])->assertCreated();
    expect($created->headers->get('Cache-Control'))->toContain('no-store');
    $secret = $created->json('token');
    $id = $created->json('data.id');
    expect($secret)->toBeString();
    $this->getJson('/browser-api/dashboard/api-tokens')->assertOk()->assertDontSee($secret)
        ->assertJsonPath('abilities', fn (array $abilities) => in_array('catalog:read', $abilities, true) && ! in_array('user-api', $abilities, true));
    $this->postJson('/browser-api/dashboard/api-tokens', ['name' => 'Bad', 'abilities' => ['discord-admin']])->assertUnprocessable();

    $this->travel(80)->days();
    $this->getJson('/browser-api/dashboard/api-tokens')->assertJsonPath('data.0.is_extendable', true);
    $this->postJson("/browser-api/dashboard/api-tokens/{$id}/extend")->assertOk();
    $extended = $user->tokens()->findOrFail($id);
    expect($extended->created_at->diffInDays($extended->expires_at))->toBeGreaterThan(90);

    $this->travel(80)->days();
    $this->postJson("/browser-api/dashboard/api-tokens/{$id}/extend")->assertOk();
    $this->travel(80)->days();
    $this->postJson("/browser-api/dashboard/api-tokens/{$id}/extend")->assertOk();
    $this->travel(80)->days();
    $this->postJson("/browser-api/dashboard/api-tokens/{$id}/extend")->assertOk();
    $token = $user->tokens()->findOrFail($id);
    expect($token->expires_at->equalTo($token->created_at->copy()->addDays(365)))->toBeTrue();
    $this->getJson('/browser-api/dashboard/api-tokens')->assertJsonPath('data.0.is_extendable', false);
    $this->postJson("/browser-api/dashboard/api-tokens/{$id}/extend")->assertUnprocessable();

    $this->travel(46)->days();
    $this->postJson("/browser-api/dashboard/api-tokens/{$id}/extend")->assertUnprocessable();

    $this->deleteJson("/browser-api/dashboard/api-tokens/{$id}")->assertNoContent();
    $this->postJson("/browser-api/dashboard/api-tokens/{$id}/extend")->assertNotFound();
});

it('does not accept a bearer token for dashboard token management', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('script', ['user-api', 'catalog:read'], now()->addDays(90))->plainTextToken)
        ->getJson('/browser-api/dashboard/api-tokens')->assertUnauthorized();
});

it('limits token management to the owner\'s user API tokens', function () {
    $owner = User::factory()->create(['is_admin' => true]);
    $userToken = $owner->createToken('script', ['user-api', 'catalog:read'], now()->addDays(90))->accessToken;
    $serviceToken = $owner->createToken('fvn-discord-bot', ['discord-bot', 'discord-notifications'])->accessToken;

    $this->actingAs($owner)->getJson('/browser-api/dashboard/api-tokens')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $userToken->id);
    $this->deleteJson("/browser-api/dashboard/api-tokens/{$serviceToken->id}")->assertNotFound();
    $this->actingAs(User::factory()->create())
        ->deleteJson("/browser-api/dashboard/api-tokens/{$userToken->id}")->assertNotFound();

    expect($owner->tokens()->count())->toBe(2);
});
