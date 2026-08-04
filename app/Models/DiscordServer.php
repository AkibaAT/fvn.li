<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiscordServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'discord_server_id',
        'discord_server_name',
        'owner_user_id',
        'is_active',
        'bot_joined_at',
        'available_channels',
        'channels_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'bot_joined_at' => 'datetime',
        'available_channels' => 'array',
        'channels_synced_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function config(): HasOne
    {
        return $this->hasOne(DiscordServerConfig::class);
    }

    public function gameSubscriptions(): HasMany
    {
        return $this->hasMany(GameDiscordSubscription::class);
    }

    public function activeGameSubscriptions(): HasMany
    {
        return $this->gameSubscriptions()->where('is_active', true);
    }

    public function games()
    {
        return $this->belongsToMany(Game::class, 'game_discord_subscriptions')
            ->withPivot('subscribed_at', 'is_active')
            ->withTimestamps();
    }

    public function tagSubscriptions(): HasMany
    {
        return $this->hasMany(DiscordServerTag::class);
    }

    public function activeTagSubscriptions(): HasMany
    {
        return $this->tagSubscriptions()->where('is_subscribed', true);
    }

    public function members(): HasMany
    {
        return $this->hasMany(DiscordServerMember::class);
    }

    public function gameOverrides(): HasMany
    {
        return $this->hasMany(DiscordServerGameOverride::class);
    }

    public function notificationHistory(): HasMany
    {
        return $this->hasMany(DiscordNotificationHistory::class);
    }

    public function recentNotifications(): HasMany
    {
        return $this->notificationHistory()
            ->where('sent_at', '>=', now()->subDays(30))
            ->orderBy('sent_at', 'desc');
    }

    public function getNotificationStats()
    {
        return $this->notificationHistory()
            ->selectRaw('delivery_status, COUNT(*) as count')
            ->groupBy('delivery_status')
            ->get()
            ->keyBy('delivery_status');
    }

    public function isConfigured(): bool
    {
        return $this->config && $this->config->notification_channel_id !== null;
    }

    public function getSubscriptionCount(): int
    {
        return $this->activeGameSubscriptions()->count();
    }

    public function getTagSubscriptionCount(): int
    {
        return $this->activeTagSubscriptions()->count();
    }
}
