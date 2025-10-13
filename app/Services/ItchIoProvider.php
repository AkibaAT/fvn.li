<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use SocialiteProviders\Manager\Config;

class ItchIoProvider extends AbstractProvider
{
    protected array $config;

    protected $scopes = ['profile:me', 'profile:games'];

    public static function additionalConfigKeys(): array
    {
        return [
            'client_id',
            'client_secret',
            'redirect',
        ];
    }

    public function getAccessTokenResponse($code): array
    {
        // For implicit flow, the code is actually the access token
        return [
            'access_token' => $code,
            'token_type' => 'Bearer',
        ];
    }

    public function setConfig(Config $config): static
    {
        $this->config = $config->get();

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://itch.io/user/oauth', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://itch.io/api/v1/oauth/token';
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get('https://itch.io/api/1/' . $token . '/me');
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errors'])) {
            throw new Exception('itch.io API error: ' . implode(', ', $data['errors']));
        }

        return $data['user'];
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => $user['username'],
            'name' => $user['display_name'] ?? $user['username'],
            'email' => null, // itch.io doesn't provide email in the API
            'avatar' => $user['cover_url'] ?? null,
        ]);
    }

    protected function getTokenFields($code): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => route('auth.itchio.callback'),
            'grant_type' => 'authorization_code',
        ];
    }

    protected function getCodeFields($state = null): array
    {
        $fields = parent::getCodeFields($state);
        $fields['response_type'] = 'token';
        $fields['redirect_uri'] = route('auth.itchio.callback');
        $fields['scope'] = 'profile:me profile:games';

        return $fields;
    }
}
