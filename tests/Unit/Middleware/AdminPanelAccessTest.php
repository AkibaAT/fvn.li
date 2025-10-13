<?php

declare(strict_types=1);

use App\Http\Middleware\AdminPanelAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->middleware = new AdminPanelAccess();
    $this->adminUser = User::factory()->create(['is_admin' => true]);
    $this->regularUser = User::factory()->create(['is_admin' => false]);
});

describe('admin panel access middleware', function () {
    test('allows admin users to access admin panel', function () {
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $this->adminUser);

        $response = $this->middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getContent())->toBe('OK');
    });

    test('blocks non-admin users from accessing admin panel', function () {
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $this->regularUser);

        expect(fn () => $this->middleware->handle($request, fn ($req) => response('OK')))
            ->toThrow(HttpException::class, 'You do not have permission to access the admin panel.');
    });

    test('blocks unauthenticated users from accessing admin panel', function () {
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => null);

        expect(fn () => $this->middleware->handle($request, fn ($req) => response('OK')))
            ->toThrow(HttpException::class);
    });

    test('throws 403 status code for unauthorized access', function () {
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $this->regularUser);

        try {
            $this->middleware->handle($request, fn ($req) => response('OK'));
            $this->fail('Expected HttpException to be thrown');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    });

    test('allows admin to access different admin routes', function () {
        $routes = ['/admin/users', '/admin/games', '/admin/settings'];

        foreach ($routes as $route) {
            $request = Request::create($route, 'GET');
            $request->setUserResolver(fn () => $this->adminUser);

            $response = $this->middleware->handle($request, fn ($req) => response('OK'));

            expect($response->getContent())->toBe('OK');
        }
    });

    test('blocks regular user from all admin routes', function () {
        $routes = ['/admin/users', '/admin/games', '/admin/settings'];

        foreach ($routes as $route) {
            $request = Request::create($route, 'GET');
            $request->setUserResolver(fn () => $this->regularUser);

            expect(fn () => $this->middleware->handle($request, fn ($req) => response('OK')))
                ->toThrow(HttpException::class);
        }
    });
});

describe('edge cases', function () {
    test('handles user with is_admin set to false explicitly', function () {
        $user = User::factory()->create(['is_admin' => false]);
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $user);

        expect(fn () => $this->middleware->handle($request, fn ($req) => response('OK')))
            ->toThrow(HttpException::class);
    });

    test('handles user with is_admin set to true explicitly', function () {
        $user = User::factory()->create(['is_admin' => true]);
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getContent())->toBe('OK');
    });

    test('handles POST requests to admin panel', function () {
        $request = Request::create('/admin/users', 'POST');
        $request->setUserResolver(fn () => $this->adminUser);

        $response = $this->middleware->handle($request, fn ($req) => response('Created', 201));

        expect($response->getStatusCode())->toBe(201);
    });

    test('blocks POST requests from non-admin users', function () {
        $request = Request::create('/admin/users', 'POST');
        $request->setUserResolver(fn () => $this->regularUser);

        expect(fn () => $this->middleware->handle($request, fn ($req) => response('Created')))
            ->toThrow(HttpException::class);
    });
});

