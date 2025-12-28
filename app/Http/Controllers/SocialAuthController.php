<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AccountMergeService;
use Exception;
use GuzzleHttp\Client;
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
                // If coming from login page, try to redirect to games index
                session()->put('url.intended', route('games.index'));
            }
        }

        Log::info('Current session intended URL before OAuth redirect', [
            'has_intended' => session()->has('url.intended'),
            'url' => session('url.intended'),
        ]);

        // Provider-specific scope configuration
        switch ($provider) {
            case 'discord':
                // Include applications.commands scope with integration_type=1 to prompt
                // users to install the bot for DM notifications during OAuth flow
                return Socialite::driver($provider)
                    ->setScopes(['identify', 'applications.commands'])
                    ->with(['integration_type' => '1'])
                    ->redirect();

            case 'google':
                return Socialite::driver($provider)
                    ->setScopes(['openid', 'email', 'profile'])
                    ->redirect();

            case 'itchio':
                // For itch.io, we need to handle the implicit flow
                return Socialite::driver($provider)
                    ->setScopes(['profile:me'])
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
                // For itch.io, we need to handle the implicit flow response
                if ($provider === 'itchio') {
                    // Get the hash fragment from the URL
                    $hash = request('hash');
                    if (! $hash) {
                        throw new Exception('No hash fragment received from itch.io');
                    }

                    // Parse the hash fragment
                    parse_str($hash, $hashParams);
                    $accessToken = $hashParams['access_token'] ?? null;

                    if (! $accessToken) {
                        throw new Exception('No access token found in hash fragment');
                    }

                    Log::info('Received itch.io access token', ['token' => substr($accessToken, 0, 10) . '...']);

                    // Create a SocialiteUser instance with the access token
                    $socialiteUser = Socialite::driver($provider)->userFromToken($accessToken);

                    // Log the user data we received
                    Log::info('Received itch.io user data', [
                        'id' => $socialiteUser->getId(),
                        'name' => $socialiteUser->getName(),
                        'nickname' => $socialiteUser->getNickname(),
                        'email' => $socialiteUser->getEmail(),
                        'avatar' => $socialiteUser->getAvatar(),
                        'raw' => $socialiteUser->user,
                    ]);
                } else {
                    $socialiteUser = Socialite::driver($provider)->user();
                }
            }

            // Check if we're in the process of merging accounts
            if (session()->has('merging_user_id')) {
                $mergingUserId = session()->pull('merging_user_id');
                $mergingUser = User::findOrFail($mergingUserId);

                // Check if the social account exists
                $existingSocialAccount = SocialAccount::where('provider_name', $provider)
                    ->where('provider_id', $socialiteUser->getId())
                    ->first();

                if ($existingSocialAccount) {
                    // If the account exists and belongs to another user, merge the accounts
                    if ($existingSocialAccount->user_id !== $mergingUser->id) {
                        $otherUser = $existingSocialAccount->user;

                        // Use the AccountMergeService to handle the merge
                        $mergeService = new AccountMergeService;
                        $mergeService->mergeAccounts($mergingUser, $otherUser);

                        return redirect()->route('dashboard')
                            ->with('success', 'Accounts successfully merged!');
                    }

                    return redirect()->route('dashboard')
                        ->with('error', 'This social account is already linked to your account.');
                }

                // If the social account doesn't exist, create it for the merging user
                $this->updateOrCreateSocialAccount($mergingUser, $socialiteUser, $provider);

                return redirect()->route('dashboard')
                    ->with('success', 'Social account successfully linked!');
            }

            $user = Auth::user() ?? $this->findOrCreateUser($socialiteUser, $provider);

            // Log the user creation/finding process
            Log::info('User lookup/creation result', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

            $this->updateOrCreateSocialAccount($user, $socialiteUser, $provider);
            Auth::login($user, remember: true);

            // Ensure user has default lists (fallback if UserObserver failed)
            if ($user->vnLists()->count() === 0) {
                try {
                    $user->initializeDefaultLists();
                    Log::info('SocialAuth: Initialized default lists as fallback', [
                        'user_id' => $user->id,
                        'lists_created' => $user->vnLists()->where('is_default', true)->count(),
                    ]);
                } catch (Exception $e) {
                    Log::error('SocialAuth: Failed to initialize default lists as fallback', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Log the session for debugging
            Log::info('Session data:', [
                'has_intended' => session()->has('url.intended'),
                'intended_url' => session('url.intended'),
                'previous_url' => url()->previous(),
            ]);

            // Get the intended URL or fall back to games.index
            $redirectTo = session()->pull('url.intended', route('games.index'));

            // If the redirectTo is the login page, redirect to dashboard instead
            if (strpos($redirectTo, route('login')) !== false) {
                $redirectTo = route('dashboard');
            }

            return redirect($redirectTo);
        } catch (Exception $e) {
            // Log the error for debugging
            Log::error("Social auth error with {$provider}: " . $e->getMessage(), [
                'exception' => $e,
                'request_data' => request()->all(),
            ]);

            // Flash error message to session
            session()->flash('error', 'Failed to authenticate with ' . $provider);

            return redirect(route('games.index'));
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

        // For itch.io, fetch the user's games using the profile:games scope
        $itchioGameIds = null;
        if ($provider === 'itchio' && isset($socialiteUser->token)) {
            $itchioGameIds = $this->fetchItchioGames($socialiteUser->token);
        }

        $accountData = array_merge($tokenData, [
            'provider_data' => $providerData,
        ]);

        // Add itch.io game IDs if available
        if ($itchioGameIds !== null) {
            $accountData['itchio_game_ids'] = $itchioGameIds;
        }

        $user->socialAccounts()->updateOrCreate(
            [
                'provider_name' => $provider,
                'provider_id' => $socialiteUser->getId(),
            ],
            $accountData
        );
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
     * Find or create a user based on the socialite user.
     */
    private function findOrCreateUser($socialiteUser, string $provider): User
    {
        // First try to find user by their social account
        $socialAccount = SocialAccount::where('provider_name', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($socialAccount) {
            Log::info('Found existing social account', [
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
                'user_id' => $socialAccount->user_id,
            ]);

            return $socialAccount->user;
        }

        // For minimal scope authentication, we might not have name or email
        // Generate placeholder values as needed
        $name = $socialiteUser->getName()
            ?? $socialiteUser->getNickname()
            ?? ($provider . ' User ' . substr($socialiteUser->getId(), 0, 8));

        $email = $socialiteUser->getEmail();
        $avatar = $socialiteUser->getAvatar();

        Log::info('Creating/looking up user with data', [
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
            'name' => $name,
            'email' => $email,
            'has_avatar' => ! empty($avatar),
        ]);

        // If user has email and we need to link with existing account
        $user = null;
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                Log::info('Found existing user by email', ['user_id' => $user->id]);
            }
        }

        // If user doesn't exist, create a new one with minimal info
        if (! $user) {
            Log::info('Creating new user');
            $user = User::create([
                'name' => $name,
                'email' => $email, // This can be null with our updated schema
                'password' => Hash::make(Str::random(24)),
                'avatar' => $avatar,
            ]);
            Log::info('Created new user', ['user_id' => $user->id]);
        }

        return $user;
    }

    /**
     * Fetch the user's games from itch.io using the profile:games scope
     *
     * @param  string  $accessToken  The itch.io access token
     * @return array Array of game IDs the user has edit permissions for
     */
    private function fetchItchioGames(string $accessToken): array
    {
        try {
            $client = new Client;
            $response = $client->get("https://itch.io/api/1/{$accessToken}/my-games");
            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errors'])) {
                Log::error('itch.io API error when fetching games', ['errors' => $data['errors']]);

                return [];
            }

            // Extract game IDs from the response
            $gameIds = [];
            if (isset($data['games']) && is_array($data['games'])) {
                foreach ($data['games'] as $game) {
                    if (isset($game['id'])) {
                        $gameIds[] = $game['id'];
                    }
                }
            }

            Log::info('Fetched itch.io games for user', [
                'game_count' => count($gameIds),
                'game_ids' => $gameIds,
            ]);

            return $gameIds;
        } catch (Exception $e) {
            Log::error('Failed to fetch itch.io games', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
