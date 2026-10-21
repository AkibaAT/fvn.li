<?php

declare(strict_types=1);

namespace App\Support;

use Laravel\Sanctum\PersonalAccessToken;

enum UserApiAbility: string
{
    case CatalogRead = 'catalog:read';
    case TagsRead = 'tags:read';
    case TagsWrite = 'tags:write';
    case ListsRead = 'lists:read';
    case ListsWrite = 'lists:write';
    case TrackingRead = 'tracking:read';
    case TrackingWrite = 'tracking:write';
    case PreferencesRead = 'preferences:read';
    case PreferencesWrite = 'preferences:write';

    public const TOKEN_MARKER = 'user-api';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isUserApiToken(PersonalAccessToken $token): bool
    {
        return in_array(self::TOKEN_MARKER, $token->abilities, true);
    }

    public function middleware(): string
    {
        return 'sanctum.token:' . $this->value;
    }
}
