<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordServerMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'discord_server_id',
        'user_id',
        'discord_user_id',
        'discord_username',
        'is_admin',
        'joined_at',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'joined_at' => 'datetime',
    ];

    /**
     * Get the Discord server.
     */
    public function discordServer(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    /**
     * Get the associated fvn.li user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to admin members.
     */
    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    /**
     * Scope to non-admin members.
     */
    public function scopeNonAdmins($query)
    {
        return $query->where('is_admin', false);
    }

    /**
     * Scope to members linked to fvn.li users.
     */
    public function scopeLinked($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope to members not linked to fvn.li users.
     */
    public function scopeUnlinked($query)
    {
        return $query->whereNull('user_id');
    }
}

