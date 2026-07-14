<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Discord\DiscordCatalogMessageSyncService;
use Illuminate\Console\Command;

class SyncDiscordCatalogMessages extends Command
{
    protected $signature = 'discord:sync-catalog-messages {--game-id=}';

    protected $description = 'Queue edits for Discord catalog messages whose rendered game metadata changed';

    public function handle(DiscordCatalogMessageSyncService $sync): int
    {
        $gameId = $this->option('game-id');
        $queued = $gameId ? $sync->queueForGame((int) $gameId) : $sync->queueAll();
        $this->info("Queued {$queued} Discord catalog message edit(s).");

        return self::SUCCESS;
    }
}
