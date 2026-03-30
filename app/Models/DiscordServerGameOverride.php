<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordServerGameOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'discord_server_id',
        'game_id',
        'is_ignored',
        'channel_id',
        'new_game_embed',
        'update_embed',
    ];

    protected $casts = [
        'is_ignored' => 'boolean',
        'new_game_embed' => 'array',
        'update_embed' => 'array',
    ];

    public function discordServer(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
