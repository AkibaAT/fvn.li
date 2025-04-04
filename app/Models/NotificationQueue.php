<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationQueue extends Model
{
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
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'payload' => 'array',
        'meta_data' => 'array',
    ];

    /**
     * Get the user this notification is for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the game this notification is for.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the game version this notification is for.
     */
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
}
