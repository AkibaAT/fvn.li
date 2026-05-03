<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

it('does not register debug impersonation routes', function () {
    expect(Route::has('debug.login'))->toBeFalse()
        ->and(Route::has('debug.login-7'))->toBeFalse()
        ->and(Route::has('debug.logout'))->toBeFalse();
});

it('does not allow debug login or debug-cookie authentication in testing', function () {
    $user = User::factory()->create();

    $this->get("/__debug/login/{$user->id}")
        ->assertNotFound();

    $this->withCookie('fvn_e2e_user_id', (string) $user->id)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});
