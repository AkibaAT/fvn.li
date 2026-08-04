<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordServerTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'discord_server_id',
        'tag_name',
        'is_subscribed',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
    ];

    public function discordServer(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    /**
     * Scope to subscribed tags.
     */
    public function scopeSubscribed($query)
    {
        return $query->where('is_subscribed', true);
    }

    /**
     * Scope to unsubscribed tags.
     */
    public function scopeUnsubscribed($query)
    {
        return $query->where('is_subscribed', false);
    }
}
