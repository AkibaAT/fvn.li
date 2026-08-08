<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscordChannelAnnouncement;
use App\Models\NotificationQueue;
use Illuminate\Console\Command;

class ReapNotifications extends Command
{
    protected $signature = 'notifications:reap';

    protected $description = 'Fail stale notification rows that can no longer be delivered';

    public function handle(): int
    {
        $expired = NotificationQueue::query()
            ->where('status', 'pending')
            ->where('scheduled_at', '<', now()->subDays(14))
            ->update(['status' => 'failed', 'processed_at' => now(), 'error' => 'notification_expired']);

        $hidden = DiscordChannelAnnouncement::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays(7))
            ->whereHas('game', fn ($query) => $query->where('is_visible', false))
            ->update(['status' => 'failed', 'processed_at' => now(), 'error' => 'game_not_visible']);

        $this->info("Reaped {$expired} expired notifications and {$hidden} hidden-game announcements.");

        return self::SUCCESS;
    }
}
