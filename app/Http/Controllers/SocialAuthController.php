<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirectToProvider(string $provider)
    {
        // Only store the URL if it hasn't been set already by the auth middleware
        if (! session()->has('url.intended')) {
            $previousUrl = url()->previous();
            // Don't store the login page as the intended URL
            if (strpos($previousUrl, route('login')) === false) {
                session()->put('url.intended', $previousUrl);
                Log::info('Storing intended URL in redirectToProvider', ['url' => $previousUrl]);
            } else {
                // If coming from login page, try to redirect to lists
                session()->put('url.intended', route('vn-lists.index'));
            }
        }

        Log::info('Current session intended URL before OAuth redirect', [
            'has_intended' => session()->has('url.intended'),
            'url' => session('url.intended'),
        ]);

        // Provider-specific scope configuration
        switch ($provider) {
            case 'discord':
                return Socialite::driver($provider)
                    ->setScopes(['identify'])
                    ->redirect();

            case 'google':
                return Socialite::driver($provider)
                    ->setScopes(['openid', 'email', 'profile'])
                    ->redirect();

            default:
                return Socialite::driver($provider)->redirect();
        }
    }

    /**
     * Handle provider callback.
     */
    public function handleProviderCallback(string $provider)
    {
        try {
            // Special handling for Telegram widget data
            if ($provider === 'telegram') {
                $data = request()->all();
                if (empty($data)) {
                    throw new Exception('No data received from Telegram');
                }

                // Create a SocialiteUser instance from the Telegram data
                $socialiteUser = new \Laravel\Socialite\Two\User;
                $socialiteUser->id = $data['id'];
                $socialiteUser->name = $data['first_name'] . (isset($data['last_name']) ? ' ' . $data['last_name'] : '');
                $socialiteUser->nickname = $data['username'] ?? null;
                $socialiteUser->avatar = $data['photo_url'] ?? null;

                // Store the raw data for provider_data
                $socialiteUser->user = $data;
            } else {
                $socialiteUser = Socialite::driver($provider)->user();
            }

            $user = Auth::user() ?? $this->findOrCreateUser($socialiteUser, $provider);
            $this->updateOrCreateSocialAccount($user, $socialiteUser, $provider);
            Auth::login($user);

            // Log the session for debugging
            Log::info('Session data:', [
                'has_intended' => session()->has('url.intended'),
                'intended_url' => session('url.intended'),
                'previous_url' => url()->previous(),
            ]);

            // Get the intended URL or fall back to games.index
            $redirectTo = session()->pull('url.intended', route('games.index'));

            // If the redirectTo is the login page, redirect to lists instead
            if (strpos($redirectTo, route('login')) !== false) {
                $redirectTo = route('vn-lists.index');
            }

            return redirect($redirectTo);

        } catch (Exception $e) {
            // Log the error for debugging
            logger()->error("Social auth error with {$provider}: " . $e->getMessage());

            // Flash error message to session
            session()->flash('error', 'Failed to authenticate with ' . $provider);

            return redirect(route('games.index'));
        }
    }

    /**
     * Find or create a user based on the socialite user.
     */
    private function findOrCreateUser($socialiteUser, string $provider): User
    {
        // First try to find user by their social account
        $socialAccount = SocialAccount::where('provider_name', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($socialAccount) {
            return $socialAccount->user;
        }

        // For minimal scope authentication, we might not have name or email
        // Generate placeholder values as needed
        $name = $socialiteUser->getName()
            ?? $socialiteUser->getNickname()
            ?? ($provider . ' User ' . substr($socialiteUser->getId(), 0, 8));

        $email = $socialiteUser->getEmail();
        $avatar = $socialiteUser->getAvatar();

        // If user has email and we need to link with existing account
        $user = null;
        if ($email) {
            $user = User::where('email', $email)->first();
        }

        // If user doesn't exist, create a new one with minimal info
        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email, // This can be null with our updated schema
                'password' => Hash::make(Str::random(24)),
                'avatar' => $avatar,
            ]);
        }

        return $user;
    }

    /**
     * Get the appropriate name from the socialite user based on provider.
     */
    private function getProviderSpecificName($socialiteUser, string $provider): string
    {
        $userData = $socialiteUser->user ?? [];

        switch ($provider) {
            case 'google':
                return $userData['given_name']
                    ?? $socialiteUser->getName()
                    ?? $socialiteUser->getNickname()
                    ?? ($provider . ' User ' . substr($socialiteUser->getId(), 0, 8));

            case 'discord':
                return $userData['global_name']
                    ?? $socialiteUser->getName()
                    ?? $socialiteUser->getNickname()
                    ?? ($provider . ' User ' . substr($socialiteUser->getId(), 0, 8));

            default:
                return $socialiteUser->getName()
                    ?? $socialiteUser->getNickname()
                    ?? ($provider . ' User ' . substr($socialiteUser->getId(), 0, 8));
        }
    }

    /**
     * Update or create a social account for the user.
     */
    private function updateOrCreateSocialAccount($user, $socialiteUser, string $provider): void
    {
        // Default values for token-related fields
        $tokenData = [
            'token' => $socialiteUser->token ?? null,
            'refresh_token' => $socialiteUser->refreshToken ?? null,
            'token_expires_at' => null,
        ];

        // Only calculate expiry if we have both a token and an expiry time
        if (isset($socialiteUser->token) && isset($socialiteUser->expiresIn)) {
            $tokenData['token_expires_at'] = now()->addSeconds($socialiteUser->expiresIn);
        }

        // Extract provider data (some providers might not include this)
        $providerData = isset($socialiteUser->user) ? $socialiteUser->user : [];

        // For Telegram, we might need to construct provider data from available fields
        if ($provider === 'telegram' && empty($providerData)) {
            $providerData = [
                'id' => $socialiteUser->getId(),
                'name' => $socialiteUser->getName(),
                'nickname' => $socialiteUser->getNickname(),
                'avatar' => $socialiteUser->getAvatar(),
            ];
        }

        // Update the user's information
        $user->update([
            'name' => $this->getProviderSpecificName($socialiteUser, $provider),
            'avatar' => $socialiteUser->getAvatar() ?? $user->avatar,
        ]);

        $user->socialAccounts()->updateOrCreate(
            [
                'provider_name' => $provider,
                'provider_id' => $socialiteUser->getId(),
            ],
            array_merge($tokenData, [
                'provider_data' => $providerData,
            ])
        );
    }
}
