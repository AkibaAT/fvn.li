<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'user_id',
        'endpoint',
        'p256dh',
        'auth',
        'subscription_data',
        'delivery_status',
        'delivery_verified_at',
        'delivery_last_failed_at',
        'delivery_last_error',
    ];

    protected $casts = [
        'subscription_data' => 'array',
        'delivery_verified_at' => 'datetime',
        'delivery_last_failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDeliverable(Builder $query): Builder
    {
        return $query->where('delivery_status', '!=', self::STATUS_INVALID);
    }

    public function markVerified(): void
    {
        $this->update([
            'delivery_status' => self::STATUS_VERIFIED,
            'delivery_verified_at' => now(),
            'delivery_last_failed_at' => null,
            'delivery_last_error' => null,
        ]);
    }

    public function markInvalid(string $error): void
    {
        $this->update([
            'delivery_status' => self::STATUS_INVALID,
            'delivery_last_failed_at' => now(),
            'delivery_last_error' => $error,
        ]);
    }

    public function recordFailure(string $error): void
    {
        $this->update([
            'delivery_last_failed_at' => now(),
            'delivery_last_error' => $error,
        ]);
    }
}
