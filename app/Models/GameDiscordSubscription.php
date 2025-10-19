<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameDiscordSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'discord_server_id',
        'subscribed_at',
        'is_active',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the game being subscribed to.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the Discord server.
     */
    public function discordServer(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    /**
     * Scope to active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to inactive subscriptions.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}

