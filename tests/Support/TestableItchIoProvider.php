<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\ItchIoProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

final class TestableItchIoProvider extends ItchIoProvider
{
    public function exposedAuthUrl(string $state): string
    {
        return $this->getAuthUrl($state);
    }

    public function exposedTokenUrl(): string
    {
        return $this->getTokenUrl();
    }

    public function exposedUserByToken(string $token): array
    {
        return $this->getUserByToken($token);
    }

    public function exposedMapUser(array $user): SocialiteUser
    {
        return $this->mapUserToObject($user);
    }

    public function exposedTokenFields(string $code): array
    {
        return $this->getTokenFields($code);
    }

    public function exposedCodeFields(?string $state = null): array
    {
        return $this->getCodeFields($state);
    }
}
