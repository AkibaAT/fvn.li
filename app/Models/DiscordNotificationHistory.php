<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordNotificationHistory extends Model
{
    use HasFactory, MassPrunable;

    public const MAX_ATTEMPTS = 3;

    public const BACKOFF_MINUTES = [15, 60, 240];

    protected $table = 'discord_notification_history';

    protected $fillable = [
        'discord_server_id',
        'game_id',
        'game_version_id',
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
        'attempts',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'payload' => 'array',
        'attempts' => 'integer',
    ];

    public function discordServer(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
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

    public function scopeClaimable(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->where('delivery_status', 'pending')
                    ->orWhere(function (Builder $query): void {
                        $query->where('delivery_status', 'processing')->where('updated_at', '<', now()->subMinutes(15));
                    });
            })
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function markAsSent(?string $messageId = null): void
    {
        $attributes = [
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ];
        if ($messageId !== null) {
            $attributes['message_id'] = $messageId;
        }
        $this->update($attributes);
    }

    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'delivery_status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function prunable(): Builder
    {
        return static::query()->whereIn('delivery_status', ['sent', 'failed'])->where('updated_at', '<=', now()->subDays(90));
    }
}
