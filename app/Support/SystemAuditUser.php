<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SystemAuditUser
{
    public const string EMAIL = 'system+anonymized@fvn.li';

    public const string LEGACY_EMAIL = 'system@fvn.li';

    public const string NAME = 'System';

    public static function id(): int
    {
        return DB::transaction(function (): int {
            $configuredId = config('audit.system_user_id');

            if (is_numeric($configuredId)) {
                $configuredUser = User::query()->find((int) $configuredId);
                if ($configuredUser && self::isSystemUser($configuredUser)) {
                    return $configuredUser->id;
                }
            }

            return self::ensure()->id;
        });
    }

    public static function ensure(): User
    {
        $existing = User::query()
            ->whereIn('email', [self::EMAIL, self::LEGACY_EMAIL])
            ->orderByRaw('email = ? DESC', [self::EMAIL])
            ->first();

        if ($existing) {
            return $existing;
        }

        return User::withoutEvents(fn () => User::query()->create([
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => '',
        ]));
    }

    public static function isSystemUser(User $user): bool
    {
        return in_array($user->email, [self::EMAIL, self::LEGACY_EMAIL], true);
    }
}
