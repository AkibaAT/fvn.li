<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EdgeCache;
use Illuminate\Console\Command;

class PurgeEdgeCacheCommand extends Command
{
    protected $signature = 'edge-cache:purge';

    protected $description = 'Purge every page from the Cloudflare cache';

    public function handle(): int
    {
        if (! EdgeCache::isEnabled()) {
            $this->info('Cloudflare purging is not configured; nothing to purge.');

            return Command::SUCCESS;
        }

        EdgeCache::purgeEverything();
        $this->info('Purged the Cloudflare cache.');

        return Command::SUCCESS;
    }
}
