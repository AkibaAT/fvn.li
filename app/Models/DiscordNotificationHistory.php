<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordNotificationHistory extends Model
{
    use HasFactory;

    protected $table = 'discord_notification_history';

    protected $fillable = [
        'discord_server_id',
        'game_id',
        'notification_type',
        'message_id',
        'channel_id',
        'sent_at',
        'delivery_status',
        'error_message',
        'payload',
        'batch_key',
        'delivery_mode',
        'payload_hash',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];

    public function discordServer(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function scopeSent($query)
    {
        return $query->where('delivery_status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('delivery_status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('delivery_status', 'pending');
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('sent_at', '>=', now()->subDays($days));
    }

    public function markAsSent(?string $messageId = null): void
    {
        $this->update([
            'delivery_status' => 'sent',
            'message_id' => $messageId,
        ]);
    }

    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'delivery_status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
