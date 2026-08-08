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
        'discord_dm_status',
        'discord_dm_status_reason',
        'discord_dm_verified_at',
        'discord_dm_last_failed_at',
        'discord_user_installed_at',
    ];

    protected $casts = [
        'discord_notifications_enabled' => 'boolean',
        'browser_notifications_enabled' => 'boolean',
        'notification_digest' => 'string',
        'discord_dm_verified_at' => 'datetime',
        'discord_dm_last_failed_at' => 'datetime',
        'discord_user_installed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markDiscordUnverified(): void
    {
        $this->update([
            'discord_dm_status' => 'unverified',
            'discord_dm_status_reason' => null,
            'discord_dm_verified_at' => null,
        ]);
    }

    public function markDiscordDeliverable(): void
    {
        $this->update([
            'discord_dm_status' => 'deliverable',
            'discord_dm_status_reason' => null,
            'discord_dm_verified_at' => now(),
        ]);
    }

    public function markDiscordUndeliverable(string $reason): void
    {
        $this->update([
            'discord_dm_status' => 'undeliverable',
            'discord_dm_status_reason' => $reason,
            'discord_dm_last_failed_at' => now(),
        ]);
    }

    /**
     * Record that the account authorized the user install. Deliverability is
     * still proven by an actual delivery, so the status returns to unverified.
     */
    public function markDiscordUserInstalled(): void
    {
        $this->update([
            'discord_user_installed_at' => now(),
            'discord_dm_status' => 'unverified',
            'discord_dm_status_reason' => null,
            'discord_dm_verified_at' => null,
        ]);
    }

    /**
     * Removing the app revokes the only route the bot has to this account.
     */
    public function markDiscordUninstalled(): void
    {
        $this->update([
            'discord_user_installed_at' => null,
            'discord_dm_status' => 'undeliverable',
            'discord_dm_status_reason' => 'not_authorized',
            'discord_dm_verified_at' => null,
            'discord_dm_last_failed_at' => now(),
        ]);
    }
}
