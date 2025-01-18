<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordUser extends Model
{
    protected $table = 'discord_users';

    protected $fillable = [
        'discord_id',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
