<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreferences extends Model
{
    protected $fillable = [
        'user_id',
        'discord_notifications_enabled',
        'browser_notifications_enabled',
        'notification_digest',
    ];

    protected $casts = [
        'discord_notifications_enabled' => 'boolean',
        'browser_notifications_enabled' => 'boolean',
        'notification_digest' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
