<?php

declare(strict_types=1);

use App\Http\Middleware\CanEditGame;
use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->middleware = new CanEditGame;
});

describe('can edit game middleware', function () {
    test('allows admin to edit any game', function () {
        $admin = User::factory()->create(['is_admin' => true]);
        $game = Game::factory()->create([
            'url' => ['itch_io' => 'https://somedev.itch.io/game'],
        ]);

        // Set the authenticated user
        Auth::setUser($admin);

        $request = Request::create('/api/games/'.$game->slug, 'POST');

        // Set route parameters manually
        $request->setRouteResolver(function () use ($game) {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', $game);

            return $route;
        });

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['success' => true]));

        expect($response->getStatusCode())->toBe(200)
            ->and(json_decode($response->getContent(), true)['success'])->toBeTrue();
    });

    test('allows game owner via itch.io account to edit their game', function () {
        $user = User::factory()->create(['is_admin' => false]);
        $game = Game::factory()->create([
            'url' => ['itch_io' => 'https://testdev.itch.io/test-game'],
        ]);

        // Create itch.io social account with matching game ID
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [$game->itch_id],
        ]);

        // Set the authenticated user
        Auth::setUser($user);

        $request = Request::create('/api/games/'.$game->slug, 'POST');

        // Set route parameters manually
        $request->setRouteResolver(function () use ($game) {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', $game);

            return $route;
        });

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['success' => true]));

        expect($response->getStatusCode())->toBe(200);
    });

    test('blocks non-owner from editing game', function () {
        $user = User::factory()->create(['is_admin' => false]);
        $game = Game::factory()->create([
            'url' => ['itch_io' => 'https://otherdev.itch.io/game'],
        ]);

        // User has itch.io account but doesn't own this game
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [999], // Different game ID
        ]);

        // Set the authenticated user
        Auth::setUser($user);

        $request = Request::create('/api/games/'.$game->slug, 'POST');

        // Set route parameters manually
        $request->setRouteResolver(function () use ($game) {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', $game);

            return $route;
        });

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['success' => true]));

        expect($response->getStatusCode())->toBe(403)
            ->and(json_decode($response->getContent(), true)['message'])
            ->toBe('You do not have permission to edit this game.');
    });

    test('blocks unauthenticated users from editing games', function () {
        $game = Game::factory()->create();

        // No authenticated user - don't set any user, Auth::user() will return null
        // (The test uses RefreshDatabase which resets auth state between tests)

        $request = Request::create('/api/games/'.$game->slug, 'POST');

        // Set route parameters manually
        $request->setRouteResolver(function () use ($game) {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', $game);

            return $route;
        });

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['success' => true]));

        expect($response->getStatusCode())->toBe(401)
            ->and(json_decode($response->getContent(), true)['message'])
            ->toBe('Authentication required.');
    });

    test('returns 404 when game not found', function () {
        $user = User::factory()->create();

        // Set the authenticated user
        Auth::setUser($user);

        $request = Request::create('/api/games/nonexistent', 'POST');

        // Set route parameters manually with null game
        $request->setRouteResolver(function () {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', null);

            return $route;
        });

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['success' => true]));

        expect($response->getStatusCode())->toBe(404)
            ->and(json_decode($response->getContent(), true)['message'])
            ->toBe('Game not found.');
    });
});

describe('ownership verification', function () {
    test('verifies ownership via URL matching fallback', function () {
        $user = User::factory()->create(['is_admin' => false]);
        $game = Game::factory()->create([
            'url' => ['itch_io' => 'https://testdev.itch.io/test-game'],
        ]);

        // Create itch.io account without game IDs (fallback to URL matching)
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testdev.itch.io'],
            'itchio_game_ids' => null,
        ]);

        // Set the authenticated user
        Auth::setUser($user);

        $request = Request::create('/api/games/'.$game->slug, 'POST');

        // Set route parameters manually
        $request->setRouteResolver(function () use ($game) {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', $game);

            return $route;
        });

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['success' => true]));

        expect($response->getStatusCode())->toBe(200);
    });

    test('blocks user without itch.io account', function () {
        $user = User::factory()->create(['is_admin' => false]);
        $game = Game::factory()->create();

        // Set the authenticated user
        Auth::setUser($user);

        $request = Request::create('/api/games/'.$game->slug, 'POST');

        // Set route parameters manually
        $request->setRouteResolver(function () use ($game) {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', $game);

            return $route;
        });

        $response = $this->middleware->handle($request, fn ($req) => response()->json(['success' => true]));

        expect($response->getStatusCode())->toBe(403);
    });

    test('handles multiple games owned by same user', function () {
        $user = User::factory()->create(['is_admin' => false]);
        $game1 = Game::factory()->create(['itch_id' => 100]);
        $game2 = Game::factory()->create(['itch_id' => 200]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'itchio',
            'itchio_game_ids' => [100, 200],
        ]);

        // Set the authenticated user
        Auth::setUser($user);

        // Test game1
        $request1 = Request::create('/api/games/'.$game1->slug, 'POST');
        $request1->setRouteResolver(function () use ($game1) {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', $game1);

            return $route;
        });

        $response1 = $this->middleware->handle($request1, fn ($req) => response()->json(['success' => true]));

        // Test game2
        $request2 = Request::create('/api/games/'.$game2->slug, 'POST');
        $request2->setRouteResolver(function () use ($game2) {
            $route = new Route('POST', '/api/games/{game}', []);
            $route->bind(Request::create('/'));
            $route->setParameter('game', $game2);

            return $route;
        });

        $response2 = $this->middleware->handle($request2, fn ($req) => response()->json(['success' => true]));

        expect($response1->getStatusCode())->toBe(200)
            ->and($response2->getStatusCode())->toBe(200);
    });
});
