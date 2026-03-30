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

    /**
     * Get the owner of the Discord server.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Get the server configuration.
     */
    public function config(): HasOne
    {
        return $this->hasOne(DiscordServerConfig::class);
    }

    /**
     * Get all game subscriptions for this server.
     */
    public function gameSubscriptions(): HasMany
    {
        return $this->hasMany(GameDiscordSubscription::class);
    }

    /**
     * Get all active game subscriptions.
     */
    public function activeGameSubscriptions(): HasMany
    {
        return $this->gameSubscriptions()->where('is_active', true);
    }

    /**
     * Get all games subscribed to by this server.
     */
    public function games()
    {
        return $this->belongsToMany(Game::class, 'game_discord_subscriptions')
            ->withPivot('subscribed_at', 'is_active')
            ->withTimestamps();
    }

    /**
     * Get all tag subscriptions for this server.
     */
    public function tagSubscriptions(): HasMany
    {
        return $this->hasMany(DiscordServerTag::class);
    }

    /**
     * Get all active tag subscriptions.
     */
    public function activeTagSubscriptions(): HasMany
    {
        return $this->tagSubscriptions()->where('is_subscribed', true);
    }

    /**
     * Get all members of this server.
     */
    public function members(): HasMany
    {
        return $this->hasMany(DiscordServerMember::class);
    }

    public function gameOverrides(): HasMany
    {
        return $this->hasMany(DiscordServerGameOverride::class);
    }

    /**
     * Get all notification history for this server.
     */
    public function notificationHistory(): HasMany
    {
        return $this->hasMany(DiscordNotificationHistory::class);
    }

    /**
     * Get recent notifications (last 30 days).
     */
    public function recentNotifications(): HasMany
    {
        return $this->notificationHistory()
            ->where('sent_at', '>=', now()->subDays(30))
            ->orderBy('sent_at', 'desc');
    }

    /**
     * Get notification statistics.
     */
    public function getNotificationStats()
    {
        return $this->notificationHistory()
            ->selectRaw('delivery_status, COUNT(*) as count')
            ->groupBy('delivery_status')
            ->get()
            ->keyBy('delivery_status');
    }

    /**
     * Check if server is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->config && $this->config->notification_channel_id !== null;
    }

    /**
     * Get subscription count.
     */
    public function getSubscriptionCount(): int
    {
        return $this->activeGameSubscriptions()->count();
    }

    /**
     * Get tag subscription count.
     */
    public function getTagSubscriptionCount(): int
    {
        return $this->activeTagSubscriptions()->count();
    }
}
