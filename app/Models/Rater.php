<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rater extends Model
{
    use HasFactory;

    protected $fillable = [
        'itch_id',
        'name',
        'steam_id',
        'external_platform',
        'is_review_banned',
        'user_id',
    ];

    public static function syncUserFromSocialAccount(SocialAccount $account): void
    {
        $rater = static::matchingSocialAccount($account);
        if ($rater && $rater->user_id !== $account->user_id) {
            $rater->update(['user_id' => $account->user_id]);
        }
    }

    public static function clearUserFromSocialAccount(SocialAccount $account): void
    {
        $rater = static::matchingSocialAccount($account);
        if ($rater && $rater->user_id === $account->user_id) {
            $rater->update(['user_id' => null]);
        }
    }

    public static function matchingSocialAccount(SocialAccount $account): ?self
    {
        return match ($account->provider_name) {
            'itchio' => ctype_digit((string) $account->provider_id)
                ? static::query()->where('itch_id', (int) $account->provider_id)->first()
                : null,
            'steam' => $account->provider_id
                ? static::query()->where('steam_id', $account->provider_id)->first()
                : null,
            default => null,
        };
    }

    protected static function booted(): void
    {
        static::creating(function (Rater $rater) {
            if ($rater->user_id === null) {
                $rater->user_id = $rater->linkedUserId();
            }
        });

        static::updating(function (Rater $rater) {
            if ($rater->isDirty(['itch_id', 'steam_id']) && ! $rater->isDirty('user_id')) {
                $rater->user_id = $rater->linkedUserId();
            }
        });
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedUserId(): ?int
    {
        if ($this->itch_id) {
            $userId = SocialAccount::query()
                ->where('provider_name', 'itchio')
                ->where('provider_id', (string) $this->itch_id)
                ->value('user_id');
            if ($userId) {
                return (int) $userId;
            }
        }

        if ($this->steam_id) {
            $userId = SocialAccount::query()
                ->where('provider_name', 'steam')
                ->where('provider_id', $this->steam_id)
                ->value('user_id');
            if ($userId) {
                return (int) $userId;
            }
        }

        return null;
    }

    public function resolveRouteBinding($value, $field = null): Rater
    {
        $query = $this->where($field ?? 'id', $value);

        return $query->firstOrFail();
    }
}
