<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AccountMergeService;
use App\Support\SafeRedirectUrl;
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
        session()->put('auth.remember', request()->boolean('remember'));

        // Only store the URL if it hasn't been set already by the auth middleware
        if (! session()->has('url.intended')) {
            $previousUrl = $this->safeIntendedUrlFromRequest() ?? SafeRedirectUrl::intended(url()->previous(), request());
            // Don't store the login page as the intended URL
            if ($previousUrl && strpos($previousUrl, route('login')) === false) {
                session()->put('url.intended', $previousUrl);
                Log::info('Storing intended URL in redirectToProvider', [
                    'provider' => $provider,
                    'has_intended' => true,
                ]);
            } else {
                // If coming from login page, try to redirect to games index
                session()->put('url.intended', route('games.index'));
            }
        }

        Log::info('Current session intended URL before OAuth redirect', [
            'provider' => $provider,
            'has_intended' => session()->has('url.intended'),
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

    private function safeIntendedUrlFromRequest(): ?string
    {
        $intendedUrl = request('intended');
        if (! is_string($intendedUrl) || $intendedUrl === '') {
            return null;
        }

        return SafeRedirectUrl::intended($intendedUrl, request());
    }

    /**
     * Handle provider callback.
     */
    public function handleProviderCallback(string $provider)
    {
        try {
            if ($provider === 'telegram') {
                $socialiteUser = Socialite::driver($provider)->user();

                if (! $socialiteUser->getId()) {
                    throw new Exception('No authenticated Telegram user received');
                }
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
                    $returnedState = $hashParams['state'] ?? null;
                    $expectedState = session()->pull('state');

                    if (! $accessToken) {
                        throw new Exception('No access token found in hash fragment');
                    }

                    if (
                        ! is_string($returnedState)
                        || ! is_string($expectedState)
                        || ! hash_equals($expectedState, $returnedState)
                    ) {
                        throw new Exception('Invalid OAuth state for itch.io callback');
                    }

                    Log::info('Received itch.io OAuth callback', [
                        'provider' => $provider,
                        'has_access_token' => true,
                    ]);

                    // Create a SocialiteUser instance with the access token
                    $socialiteUser = Socialite::driver($provider)->userFromToken($accessToken);

                    Log::info('Received itch.io user profile', [
                        'provider' => $provider,
                        'has_provider_id' => $socialiteUser->getId() !== null,
                        'has_name' => $socialiteUser->getName() !== null,
                        'has_nickname' => $socialiteUser->getNickname() !== null,
                        'has_email' => $socialiteUser->getEmail() !== null,
                        'has_avatar' => $socialiteUser->getAvatar() !== null,
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
                'provider' => $provider,
            ]);

            $this->updateOrCreateSocialAccount($user, $socialiteUser, $provider);
            Auth::login($user, remember: (bool) session()->pull('auth.remember', false));

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
            ]);

            // Get the intended URL or fall back to games.index
            $redirectTo = SafeRedirectUrl::intendedOrDefault(
                session()->pull('url.intended'),
                route('games.index'),
                request()
            );

            return redirect($redirectTo);
        } catch (Exception $e) {
            // Log the error for debugging
            Log::error("Social auth error with {$provider}: ".$this->redactSensitiveText($e->getMessage()), [
                'exception_class' => $e::class,
                'exception_code' => $e->getCode(),
                'request_keys' => $this->requestInputKeys(),
                'has_oauth_hash' => request()->has('hash'),
            ]);

            // Flash error message to session
            session()->flash('error', 'Failed to authenticate with '.$provider);

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
                    ?? ($provider.' User '.substr($socialiteUser->getId(), 0, 8));

            case 'discord':
                return $userData['global_name']
                    ?? $socialiteUser->getName()
                    ?? $socialiteUser->getNickname()
                    ?? ($provider.' User '.substr($socialiteUser->getId(), 0, 8));

            default:
                return $socialiteUser->getName()
                    ?? $socialiteUser->getNickname()
                    ?? ($provider.' User '.substr($socialiteUser->getId(), 0, 8));
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
                'user_id' => $socialAccount->user_id,
            ]);

            return $socialAccount->user;
        }

        // For minimal scope authentication, we might not have name or email
        // Generate placeholder values as needed
        $name = $socialiteUser->getName()
            ?? $socialiteUser->getNickname()
            ?? ($provider.' User '.substr($socialiteUser->getId(), 0, 8));

        $email = $socialiteUser->getEmail();
        $avatar = $socialiteUser->getAvatar();

        Log::info('Creating/looking up user with data', [
            'provider' => $provider,
            'has_name' => $name !== '',
            'has_email' => ! empty($email),
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
                Log::error('itch.io API error when fetching games', [
                    'error_count' => is_array($data['errors']) ? count($data['errors']) : 1,
                ]);

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
            ]);

            return $gameIds;
        } catch (Exception $e) {
            Log::error('Failed to fetch itch.io games', [
                'exception_class' => $e::class,
                'exception_code' => $e->getCode(),
                'error' => $this->redactSensitiveText($e->getMessage()),
            ]);

            return [];
        }
    }

    private function redactSensitiveText(string $value): string
    {
        $patterns = [
            '/(access_token|refresh_token|id_token|token|code|state)=([^&\s]+)/i' => '$1=[redacted]',
            '/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i' => '$1[redacted]',
            '#(/api/1/)[^/\s]+(/my-games)#' => '$1[redacted]$2',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $value);
    }

    /**
     * @return list<string>
     */
    private function requestInputKeys(): array
    {
        return array_values(array_unique(array_merge(
            request()->query->keys(),
            request()->request->keys(),
        )));
    }
}
