<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider_name',
        'provider_id',
        'token',
        'refresh_token',
        'token_expires_at',
        'provider_data',
        'itchio_game_ids',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'provider_data' => 'array',
        'itchio_game_ids' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (SocialAccount $account) {
            Rater::syncUserFromSocialAccount($account);
        });

        static::deleted(function (SocialAccount $account) {
            Rater::clearUserFromSocialAccount($account);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
