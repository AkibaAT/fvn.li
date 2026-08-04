<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SafeRedirectUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class AuthController extends Controller
{
    public function login(): Response
    {
        if (Auth::check()) {
            return app(HomeController::class)->home();
        }

        $previousUrl = SafeRedirectUrl::intended(url()->previous(), request());
        if ($previousUrl && ! session()->has('url.intended') && ! str_contains($previousUrl, route('login'))) {
            session()->put('url.intended', $previousUrl);
        }

        return $this->loginResponse();
    }

    public function logout(Request $request)
    {
        $redirectTo = SafeRedirectUrl::intended(url()->previous(), $request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($redirectTo ?: route('home'));
    }
}
