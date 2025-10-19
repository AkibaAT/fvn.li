<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordNotificationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'discord_server_id',
        'game_id',
        'notification_type',
        'message_id',
        'channel_id',
        'sent_at',
        'delivery_status',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Get the Discord server.
     */
    public function discordServer(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    /**
     * Get the game.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Scope to sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('delivery_status', 'sent');
    }

    /**
     * Scope to failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('delivery_status', 'failed');
    }

    /**
     * Scope to pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('delivery_status', 'pending');
    }

    /**
     * Scope to recent notifications.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('sent_at', '>=', now()->subDays($days));
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(?string $messageId = null): void
    {
        $this->update([
            'delivery_status' => 'sent',
            'message_id' => $messageId,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'delivery_status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}

