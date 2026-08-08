<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationQueue extends Model
{
    use MassPrunable;

    public const MAX_ATTEMPTS = 3;

    public const BACKOFF_MINUTES = [15, 60, 240];

    protected $table = 'notification_queue';

    protected $fillable = [
        'user_id',
        'game_id',
        'game_version_id',
        'channel',
        'status',
        'scheduled_at',
        'processed_at',
        'payload',
        'error',
        'meta_data',
        'attempts',
        'batch_key',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'payload' => 'array',
        'meta_data' => 'array',
        'attempts' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /**
     * Scope a query to only include pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include notifications for a specific channel.
     */
    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope a query to only include notifications due for processing.
     */
    public function scopeDue($query)
    {
        return $query->where('scheduled_at', '<=', now());
    }

    public function scopeClaimable(Builder $query, string $channel): Builder
    {
        return $query
            ->where('channel', $channel)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('status', 'pending')->where('scheduled_at', '<=', now());
                })->orWhere(function (Builder $query): void {
                    $query->where('status', 'processing')->where('updated_at', '<', now()->subMinutes(15));
                });
            })
            ->orderBy('scheduled_at')
            ->orderBy('id');
    }

    public function prunable(): Builder
    {
        return static::query()->whereIn('status', ['sent', 'failed'])->where('updated_at', '<=', now()->subDays(30));
    }
}
