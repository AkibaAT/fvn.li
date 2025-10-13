<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function login(): Response
    {
        if (Auth::check()) {
            return app(HomeController::class)->home();
        }

        $previousUrl = url()->previous();
        if (! session()->has('url.intended') && ! str_contains($previousUrl, route('login'))) {
            session()->put('url.intended', $previousUrl);
        }

        return Inertia::render('auth/login', [
            'metaTags' => [
                'title' => 'Log in - FVN.li',
                'description' => 'Log in to your FVN.li account to track your visual novel progress, create reading lists, and connect with the community.',
                'structuredData' => [
                    '@type' => 'WebPage',
                    'name' => 'Log in - FVN.li',
                    'description' => 'Log in to your FVN.li account to track your visual novel progress',
                    'url' => route('login'),
                ],
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $redirectTo = url()->previous();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($redirectTo ?: route('home'));
    }
}
