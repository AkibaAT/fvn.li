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

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function discordServer(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}
